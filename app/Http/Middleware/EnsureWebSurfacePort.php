<?php

namespace App\Http\Middleware;

use Closure;
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
        $targetUrl = $surface === 'admin'
            ? rtrim((string) config('app.url'), '/')
            : rtrim((string) config('geoflow.site_url'), '/');

        if ($targetUrl !== '') {
            URL::forceRootUrl($targetUrl);
        }

        if (! $this->shouldRedirect($request, $targetUrl)) {
            return $next($request);
        }

        if ($surface === 'site' && $this->isRootPath($request)) {
            $adminBasePath = '/'.trim((string) config('geoflow.admin_base_path', '/geo_admin'), '/');

            return redirect()->away(rtrim((string) config('app.url'), '/').$adminBasePath.'/login');
        }

        return redirect()->away(
            $this->targetUrlForRequest($request, $targetUrl),
            $surface === 'site' ? 301 : 302
        );
    }

    private function shouldRedirect(Request $request, string $targetUrl): bool
    {
        if (! $this->requestHasExplicitPort($request)) {
            return false;
        }

        $targetPort = parse_url($targetUrl, PHP_URL_PORT);
        if (! is_int($targetPort)) {
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
}
