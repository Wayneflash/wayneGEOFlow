#!/usr/bin/env sh
set -eu

cd /var/www/html

# 在并发容器之间用 bind-mount 里的目录做互斥锁。
# 历史实现是直接 `while ! mkdir`，一旦持有者异常退出没清理 lock 目录，
# 后续所有容器会永远卡在等锁。这里加 stale 检测：
#   1. 写一个 .pid 进去记录持有者 PID
#   2. 若 lock 已存在，看 PID 是否还活着、目录 mtime 是否过老
#   3. 满足任一条件 → 视为 stale，强制清理后重试
# 同时设绝对超时，避免极端情况下永远卡死。
GEOFLOW_LOCK_MAX_AGE=300       # 秒；持有者若已死，或 mtime 超过这个，视为 stale
GEOFLOW_LOCK_MAX_WAIT=1800     # 秒；总等待上限（30 分钟）

acquire_geoflow_lock() {
  _lock_dir="$1"
  _purpose="$2"
  _started=$(date +%s)
  while ! mkdir "${_lock_dir}" 2>/dev/null; do
    _now=$(date +%s)
    _elapsed=$((_now - _started))
    if [ "${_elapsed}" -ge "${GEOFLOW_LOCK_MAX_WAIT}" ]; then
      echo "[entrypoint] gave up waiting for lock (${_lock_dir}) after ${_elapsed}s"
      return 1
    fi
    # 检查 stale：PID 死了 或 mtime 太老
    _stale=0
    if [ -f "${_lock_dir}/.pid" ]; then
      _holder_pid=$(cat "${_lock_dir}/.pid" 2>/dev/null || echo "")
      if [ -n "${_holder_pid}" ] && ! kill -0 "${_holder_pid}" 2>/dev/null; then
        _stale=1
        echo "[entrypoint] lock holder PID=${_holder_pid} no longer alive, treating as stale"
      fi
    fi
    if [ "${_stale}" -eq 0 ] && [ -d "${_lock_dir}" ]; then
      _mtime=0
      # GNU stat: %Y (mtime epoch); BSD/macOS stat: %m
      _mtime=$(stat -c %Y "${_lock_dir}" 2>/dev/null || stat -f %m "${_lock_dir}" 2>/dev/null || echo 0)
      if [ "${_mtime}" -gt 0 ] && [ $((_now - _mtime)) -gt "${GEOFLOW_LOCK_MAX_AGE}" ]; then
        _stale=1
        echo "[entrypoint] lock is stale (mtime age=$((_now - _mtime))s > ${GEOFLOW_LOCK_MAX_AGE}s)"
      fi
    fi
    if [ "${_stale}" -eq 1 ]; then
      rm -rf "${_lock_dir}" 2>/dev/null || true
      continue
    fi
    echo "[entrypoint] waiting for another container to finish ${_purpose}"
    sleep 2
  done
  # 拿到锁后记录持有者 PID
  echo "$$" > "${_lock_dir}/.pid"
  echo "$$ $(date -u +%FT%TZ)" > "${_lock_dir}/.owner"
}

release_geoflow_lock() {
  _lock_dir="$1"
  rm -f "${_lock_dir}/.pid" "${_lock_dir}/.owner" 2>/dev/null || true
  rmdir "${_lock_dir}" 2>/dev/null || true
}

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

# Docker 环境变量优先级高于 .env。空值或无效 APP_KEY 会覆盖 .env 中的有效密钥，
# 并导致 composer 脚本或 artisan 首次启动失败，因此尽早移除无效环境变量。
if [ -z "${APP_KEY:-}" ] || ! printf '%s' "${APP_KEY:-}" | grep -q '^base64:'; then
  unset APP_KEY
fi

COMPOSER_NEED_POST_INSTALL=false
COMPOSER_ON_START="${COMPOSER_ON_START:-true}"
RUN_COMPOSER=false
if [ ! -f vendor/autoload.php ]; then
  RUN_COMPOSER=true
elif [ "${COMPOSER_ON_START}" = "true" ]; then
  RUN_COMPOSER=true
fi

if [ "${RUN_COMPOSER}" = "true" ]; then
  mkdir -p storage/framework
  COMPOSER_LOCK_DIR="storage/framework/.geoflow-composer-install.lock"
  acquire_geoflow_lock "${COMPOSER_LOCK_DIR}" "composer install" || exit 1
  trap 'release_geoflow_lock "${COMPOSER_LOCK_DIR}"' EXIT INT TERM
  # Packagist 中国镜像，加速 composer install。
  COMPOSER_PACKAGIST_MIRROR="${COMPOSER_PACKAGIST_MIRROR:-https://mirrors.aliyun.com/composer/}"
  COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer}"
  export COMPOSER_HOME
  mkdir -p "${COMPOSER_HOME}"
  if ! composer config -g repo.packagist composer "${COMPOSER_PACKAGIST_MIRROR}"; then
    echo "[entrypoint] warning: failed to configure composer mirror, continue with default source"
  fi
  echo "[entrypoint] composer install (COMPOSER_ON_START=${COMPOSER_ON_START}, vendor missing=$([ ! -f vendor/autoload.php ] && echo yes || echo no))"
  # 无有效 APP_KEY 时 composer 脚本会调 artisan（package:discover），易失败且留不下 vendor/autoload.php
  if grep -Eq '^APP_KEY=base64:' .env 2>/dev/null; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
  else
    composer install --no-interaction --prefer-dist --no-scripts --optimize-autoloader
    COMPOSER_NEED_POST_INSTALL=true
  fi

  rmdir "${COMPOSER_LOCK_DIR}" 2>/dev/null || true
  trap - EXIT INT TERM
fi

# 自动初始化 APP_KEY（仅在 .env 里缺失时生成，避免每次重置密钥）
if [ "${AUTO_GENERATE_APP_KEY:-false}" = "true" ]; then
  if ! grep -Eq '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force --no-interaction
  fi
fi

if [ "${COMPOSER_NEED_POST_INSTALL}" = "true" ]; then
  composer dump-autoload --optimize --no-interaction
fi

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs /tmp
chmod 1777 /tmp 2>/dev/null || true
if [ ! -e public/storage ]; then
  php artisan storage:link --force --no-interaction
fi

if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
  DB_HOST_VALUE="${DB_HOST:-postgres}"
  DB_PORT_VALUE="${DB_PORT:-5432}"
  DB_USER_VALUE="${DB_USERNAME:-postgres}"
  DB_NAME_VALUE="${DB_DATABASE:-postgres}"

  echo "[entrypoint] waiting for postgres at ${DB_HOST_VALUE}:${DB_PORT_VALUE}"
  until pg_isready -h "${DB_HOST_VALUE}" -p "${DB_PORT_VALUE}" -U "${DB_USER_VALUE}" -d "${DB_NAME_VALUE}" >/dev/null 2>&1; do
    sleep 2
  done
fi

# 仅首次初始化（compose init 服务）：库尚不可连或尚无 migrations 表时 migrate + seed
if [ "${AUTO_INIT_ONCE:-false}" = "true" ]; then
  if php artisan migrate:status --no-interaction >/dev/null 2>&1; then
    echo "[entrypoint] database already initialized, skip init migrate/seed"
  else
    echo "[entrypoint] first startup initialization: migrate + seed"
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction
  fi
fi

# 每次容器启动执行迁移（拉代码/换新镜像后默认需要；设为 false 可关闭）
if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
  mkdir -p storage/framework
  MIGRATE_LOCK_DIR="storage/framework/.geoflow-migrate.lock"
  acquire_geoflow_lock "${MIGRATE_LOCK_DIR}" "database migrations" || exit 1
  trap 'release_geoflow_lock "${MIGRATE_LOCK_DIR}"' EXIT INT TERM
  echo "[entrypoint] php artisan migrate --force"
  php artisan migrate --force --no-interaction
  release_geoflow_lock "${MIGRATE_LOCK_DIR}"
  trap - EXIT INT TERM
fi

# 每次启动是否跑 seed（默认关；仅在你明确要重置演示数据时打开）
if [ "${AUTO_SEED:-false}" = "true" ]; then
  echo "[entrypoint] php artisan db:seed --force"
  php artisan db:seed --force --no-interaction
fi

# 缓存 config / events / routes / views（需有效 APP_KEY；设为 false 可跳过，便于本地排障）
if [ "${AUTO_OPTIMIZE:-false}" = "true" ]; then
  if grep -Eq '^APP_KEY=base64:' .env 2>/dev/null; then
    echo "[entrypoint] php artisan optimize"
    php artisan optimize --no-interaction || echo "[entrypoint] warning: php artisan optimize failed, continuing"
  else
    echo "[entrypoint] skip php artisan optimize (no valid APP_KEY in .env)"
  fi
fi

exec "$@"
