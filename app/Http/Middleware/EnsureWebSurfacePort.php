<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebSurfacePort
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $surface = $surface === 'admin' ? 'admin' : 'site';
        $targetPort = $this->targetPort($surface);
        $targetUrl = $this->targetRootForRequest($request, $targetPort);

        URL::forceRootUrl($targetUrl);

        if (! $this->shouldRedirect($request, $targetPort)) {
            return $next($request);
        }

        if ($surface === 'site' && $this->isRootPath($request)) {
            $adminBasePath = '/'.trim((string) config('geoflow.admin_base_path', '/geo_admin'), '/');

            return $this->withExpiredLegacySessionCookies(
                redirect()->away($this->targetRootForRequest($request, $this->targetPort('admin')).$adminBasePath.'/login')
            );
        }

        return $this->withExpiredLegacySessionCookies(
            redirect()->away(
                $this->targetUrlForRequest($request, $targetUrl),
                $surface === 'site' ? 301 : 302
            )
        );
    }

    private function shouldRedirect(Request $request, int $targetPort): bool
    {
        if (! $this->requestHasExplicitPort($request)) {
            return false;
        }

        return $request->getPort() !== $targetPort;
    }

    private function targetUrlForRequest(Request $request, string $targetUrl): string
    {
        $path = '/'.ltrim($request->getPathInfo(), '/');
        $query = $request->getQueryString();

        return $targetUrl.($path === '/' ? '/' : $path).($query ? '?'.$query : '');
    }

    private function isRootPath(Request $request): bool
    {
        return $request->getPathInfo() === '/';
    }

    private function requestHasExplicitPort(Request $request): bool
    {
        $host = (string) $request->headers->get('host', '');

        return preg_match('/:\d+$/', $host) === 1;
    }

    private function targetPort(string $surface): int
    {
        $url = $surface === 'admin'
            ? (string) config('app.url')
            : (string) config('geoflow.site_url');

        $port = parse_url($url, PHP_URL_PORT);

        return is_int($port) ? $port : ($surface === 'admin' ? 18080 : 18081);
    }

    private function targetRootForRequest(Request $request, int $targetPort): string
    {
        return $request->getScheme().'://'.$this->hostWithoutPort($request).':'.$targetPort;
    }

    private function hostWithoutPort(Request $request): string
    {
        $host = (string) $request->headers->get('host', $request->getHost());

        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');

            return $end === false ? $request->getHost() : substr($host, 0, $end + 1);
        }

        return preg_replace('/:\d+$/', '', $host) ?: $request->getHost();
    }

    private function withExpiredLegacySessionCookies(Response $response): Response
    {
        $currentCookie = (string) config('session.cookie');
        foreach (['geo-session', 'blog_secure_session'] as $legacyCookie) {
            if ($legacyCookie !== $currentCookie) {
                $response->headers->setCookie(Cookie::forget($legacyCookie));
            }
        }

        return $response;
    }
}
