<?php

namespace App\Support\GeoFlow;

use Throwable;

/**
 * 出站 HTTPS 证书校验策略：生产可严格校验，本地/Docker 缺 CA 时自动降级。
 */
final class OutboundHttpSsl
{
    public static function verifyEnabled(): bool
    {
        return (bool) config('geoflow.url_import_verify_ssl', true);
    }

    /**
     * @return array{verify: bool}
     */
    public static function httpOptions(?bool $verify = null): array
    {
        return ['verify' => $verify ?? self::verifyEnabled()];
    }

    /**
     * @return array<int, bool|int>
     */
    public static function curlOptions(?bool $verify = null): array
    {
        $verify = $verify ?? self::verifyEnabled();
        if (! $verify) {
            // 勿设 CURLOPT_SSL_VERIFYHOST => 0，Guzzle 会误报「option 0 无效」
            return [CURLOPT_SSL_VERIFYPEER => false];
        }

        return [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
    }

    public static function isSslFailure(Throwable|string $exception): bool
    {
        $message = strtolower($exception instanceof Throwable ? $exception->getMessage() : $exception);

        return str_contains($message, 'ssl')
            || str_contains($message, 'tls')
            || str_contains($message, 'certificate')
            || str_contains($message, 'curl error 35')
            || str_contains($message, 'curl error 60');
    }

    /**
     * @return list<array{verify:bool}>
     */
    public static function httpAttempts(): array
    {
        $verify = self::verifyEnabled();
        $attempts = [['verify' => $verify]];

        if ($verify) {
            $attempts[] = ['verify' => false];
        }

        return $attempts;
    }
}
