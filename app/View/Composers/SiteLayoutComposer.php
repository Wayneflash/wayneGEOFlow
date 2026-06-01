<?php

namespace App\View\Composers;

use App\Models\Category;
use App\Support\Site\PublicSiteTenant;
use App\Support\Site\SiteSettingsBag;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * 为前台 Blade 布局注入站点名称、分类导航等公共变量。
 */
final class SiteLayoutComposer
{
    public function compose(View $view): void
    {
        $tenantId = PublicSiteTenant::currentTenantId();
        $map = SiteSettingsBag::all($tenantId);
        $siteName = (string) ($map['site_name'] ?? config('geoflow.site_name', config('app.name')));
        $siteLogo = (string) ($map['site_logo'] ?? '');
        $siteFavicon = (string) ($map['site_favicon'] ?? '');
        $copyright = (string) ($map['copyright_info'] ?? '');
        $analyticsCode = (string) ($map['analytics_code'] ?? '');

        $categories = collect();
        if (Schema::hasTable('categories')) {
            $categories = PublicSiteTenant::scopeTenantColumn(Category::query(), $tenantId)
                ->whereHas('articles', function ($q) use ($tenantId): void {
                    PublicSiteTenant::scopeTenantColumn($q, $tenantId)->published();
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->withCount([
                    'articles as published_count' => function ($q) use ($tenantId): void {
                        PublicSiteTenant::scopeTenantColumn($q, $tenantId)->published();
                    },
                ])
                ->get();
        }

        $view->with([
            'siteName' => $siteName,
            'siteLogo' => $siteLogo,
            'siteFavicon' => $siteFavicon,
            'footerCopyright' => $copyright,
            'headAnalyticsCode' => $analyticsCode,
            'navCategories' => $categories,
            'publicTenantId' => $tenantId,
        ]);
    }
}
