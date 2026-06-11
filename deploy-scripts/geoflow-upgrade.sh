#!/usr/bin/env bash
set -Eeuo pipefail

# GEOFlow 一键升级脚本
# 用法（在服务器项目根目录）：
#   bash deploy-scripts/geoflow-upgrade.sh
#
# 可选环境变量：
#   GEOFLOW_APP_DIR     项目目录，默认 /var/www/geoflow
#   GEOFLOW_BRANCH      git 分支，默认 main
#   GEOFLOW_REMOTE      git 远程，默认 origin（或 gitee 也行）
#   GEOFLOW_SKIP_BUILD  设为 1 跳过 npm 构建（仅后端改动时用）
#   GEOFLOW_SKIP_NPM_INSTALL  设为 1 跳过 npm install
#   GEOFLOW_COMPOSE_FILE      compose 文件，默认 docker-compose.yml
#   GEOFLOW_NPM_REGISTRY      npm 镜像，默认 https://registry.npmmirror.com

APP_DIR="${GEOFLOW_APP_DIR:-/var/www/geoflow}"
BRANCH="${GEOFLOW_BRANCH:-main}"
REMOTE="${GEOFLOW_REMOTE:-origin}"
COMPOSE_FILE="${GEOFLOW_COMPOSE_FILE:-docker-compose.yml}"
NPM_REGISTRY="${GEOFLOW_NPM_REGISTRY:-https://registry.npmmirror.com}"

log() {
  printf '\033[1;34m[upgrade]\033[0m %s\n' "$*"
}

warn() {
  printf '\033[1;33m[warn]\033[0m %s\n' "$*" >&2
}

fail() {
  printf '\033[1;31m[error]\033[0m %s\n' "$*" >&2
  exit 1
}

on_error() {
  fail "升级失败（line $1）。查看上方日志，必要时回滚 .env / git。"
}
trap 'on_error $LINENO' ERR

detect_docker_command() {
  if docker info >/dev/null 2>&1; then
    DOCKER_CMD=(docker)
  elif command -v sudo >/dev/null 2>&1 && sudo docker info >/dev/null 2>&1; then
    DOCKER_CMD=(sudo docker)
  else
    fail "Docker 不可用，请以 root 运行或加入 docker 组。"
  fi

  if "${DOCKER_CMD[@]}" compose version >/dev/null 2>&1; then
    COMPOSE_CMD=("${DOCKER_CMD[@]}" compose)
  elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD=(docker-compose)
  else
    fail "未找到 docker compose / docker-compose。"
  fi
}

cd "${APP_DIR}" || fail "项目目录不存在：${APP_DIR}"
[ -f "${COMPOSE_FILE}" ] || fail "找不到 ${APP_DIR}/${COMPOSE_FILE}"
[ -f .env ] || fail "找不到 .env"

detect_docker_command

# 0. 记录当前 commit 方便回滚
OLD_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
log "当前 commit: ${OLD_COMMIT}"

# 1. 备份 .env
BACKUP=".env.bak.$(date +%Y%m%d-%H%M%S)"
cp .env "${BACKUP}"
log ".env 已备份：${BACKUP}"

# 2. 拉代码
log "git fetch ${REMOTE} ${BRANCH}"
git fetch "${REMOTE}" "${BRANCH}"

NEW_COMMIT=$(git rev-parse --short "${REMOTE}/${BRANCH}")
if [ "${OLD_COMMIT}" = "${NEW_COMMIT}" ]; then
  warn "已经是最新版本 (${NEW_COMMIT})，但仍会重跑构建/迁移/重启。"
fi

log "git pull ${REMOTE} ${BRANCH}"
git pull "${REMOTE}" "${BRANCH}"

# 3. 前端构建（resources/ 改动时必跑）
RESOURCES_CHANGED=$(git diff --name-only "${OLD_COMMIT}" HEAD 2>/dev/null | grep -cE '^(resources/|package\.json|vite\.config|tailwind\.config)' || true)
RESOURCES_CHANGED=${RESOURCES_CHANGED:-0}

if [ "${GEOFLOW_SKIP_BUILD:-0}" = "1" ]; then
  log "跳过前端构建 (GEOFLOW_SKIP_BUILD=1)"
elif [ "${RESOURCES_CHANGED}" -gt 0 ] || [ "${OLD_COMMIT}" = "unknown" ] || [ ! -d public/build ]; then
  log "检测到前端变更，开始 npm 构建（${RESOURCES_CHANGED} 个相关文件）"

  if command -v node >/dev/null 2>&1; then
    log "node: $(node -v)  npm: $(npm -v)"
  else
    fail "未安装 node。请：apt install -y nodejs npm  或装 nvm。"
  fi

  if [ "${GEOFLOW_SKIP_NPM_INSTALL:-0}" != "1" ]; then
    if [ ! -d node_modules ] || [ package-lock.json -nt node_modules ]; then
      log "npm install --registry=${NPM_REGISTRY}"
      npm install --registry="${NPM_REGISTRY}" --no-audit --no-fund
    else
      log "node_modules 是新的，跳过 npm install"
    fi
  fi

  log "npm run build"
  npm run build
else
  log "无前端变更，跳过构建"
fi

# 4. 检查 compose 文件是否变了（影响 queue 容器要不要 recreate）
COMPOSE_CHANGED=$(git diff --name-only "${OLD_COMMIT}" HEAD 2>/dev/null | grep -cE "^${COMPOSE_FILE}$" || true)
COMPOSE_CHANGED=${COMPOSE_CHANGED:-0}

if [ "${COMPOSE_CHANGED}" -gt 0 ]; then
  log "${COMPOSE_FILE} 有变更，重建相关容器"
  "${COMPOSE_CMD[@]}" -f "${COMPOSE_FILE}" up -d --remove-orphans
fi

# 5. 跑迁移
log "php artisan migrate --force"
"${DOCKER_CMD[@]}" exec geoflow-app php artisan migrate --force

# 6. 清缓存
log "清理 view / config 缓存"
"${DOCKER_CMD[@]}" exec geoflow-app php artisan view:clear || true
"${DOCKER_CMD[@]}" exec geoflow-app php artisan config:clear || true
"${DOCKER_CMD[@]}" exec geoflow-app php artisan route:clear || true

# 7. 重启应用容器（清 opcache 并加载新代码）
log "重启 app / scheduler / reverb"
"${COMPOSE_CMD[@]}" -f "${COMPOSE_FILE}" restart app scheduler reverb

# 8. 重启 queue（如 compose 没变更才需要单独重启；变更时上面已 up -d）
if [ "${COMPOSE_CHANGED}" -eq 0 ]; then
  log "重启 queue（保留 Horizon 启动方式）"
  "${COMPOSE_CMD[@]}" -f "${COMPOSE_FILE}" restart queue
fi

# 9. 等待 Horizon 起来
log "等待 Horizon 启动 (最多 30 秒)"
for i in $(seq 1 15); do
  if "${DOCKER_CMD[@]}" exec geoflow-app php artisan horizon:status 2>/dev/null | grep -q "running"; then
    log "Horizon 已运行"
    break
  fi
  sleep 2
done

# 10. HTTP 健康检查
log "HTTP 健康检查"
HTTP_CODE=$("${DOCKER_CMD[@]}" exec geoflow-app curl -sS -o /dev/null -w '%{http_code}' http://localhost:8080/geo_admin/login 2>/dev/null || echo "000")
if [ "${HTTP_CODE}" = "200" ] || [ "${HTTP_CODE}" = "302" ]; then
  log "HTTP ${HTTP_CODE} ✓"
else
  warn "HTTP 状态异常: ${HTTP_CODE}，请人工检查"
fi

# 11. 输出汇总
log "—— 升级完成 ——"
log "old commit: ${OLD_COMMIT}"
log "new commit: ${NEW_COMMIT}"
log "compose 变更: $([ ${COMPOSE_CHANGED} -gt 0 ] && echo yes || echo no)"
log "前端构建: $([ ${RESOURCES_CHANGED} -gt 0 ] && echo yes || echo no)"
log ".env 备份: ${BACKUP}"
log ""
log "如需回滚: git reset --hard ${OLD_COMMIT} && bash $0"
