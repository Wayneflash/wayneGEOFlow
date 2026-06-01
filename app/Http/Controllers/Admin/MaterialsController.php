<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Support\AdminWeb;
use Illuminate\View\View;

/**
 * 素材管理首页控制器。
 */
class MaterialsController extends Controller
{
    /**
     * 展示素材管理总览页。
     */
    public function index(): View
    {
        return view('admin.materials.index', [
            'pageTitle' => __('admin.materials.page_title'),
            'activeMenu' => 'materials',
            'adminSiteName' => AdminWeb::siteName(),
            'stats' => $this->loadStats(),
        ]);
    }

    /**
     * 加载素材管理统计数据。
     *
     * @return array{
     *     keyword_libraries:int,
     *     total_keywords:int,
     *     title_libraries:int,
     *     total_titles:int,
     *     image_libraries:int,
     *     total_images:int,
     *     knowledge_bases:int,
     *     authors:int
     * }
     */
    private function loadStats(): array
    {
        $keywordLibraryIds = KeywordLibrary::query()->visibleToAdmin()->select('id');
        $titleLibraryIds = TitleLibrary::query()->visibleToAdmin()->select('id');
        $imageLibraryIds = ImageLibrary::query()->visibleToAdmin()->select('id');

        return [
            'keyword_libraries' => KeywordLibrary::query()->visibleToAdmin()->count(),
            'total_keywords' => Keyword::query()->whereIn('library_id', $keywordLibraryIds)->count(),
            'title_libraries' => TitleLibrary::query()->visibleToAdmin()->count(),
            'total_titles' => Title::query()->whereIn('library_id', $titleLibraryIds)->count(),
            'image_libraries' => ImageLibrary::query()->visibleToAdmin()->count(),
            'total_images' => Image::query()->whereIn('library_id', $imageLibraryIds)->count(),
            'knowledge_bases' => KnowledgeBase::query()->visibleToAdmin()->count(),
            'authors' => Author::query()->visibleToAdmin()->count(),
        ];
    }
}
