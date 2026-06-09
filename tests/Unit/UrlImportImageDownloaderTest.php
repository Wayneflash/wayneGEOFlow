<?php

namespace Tests\Unit;

use App\Services\GeoFlow\UrlImportImageDownloader;
use Tests\TestCase;

class UrlImportImageDownloaderTest extends TestCase
{
    private UrlImportImageDownloader $downloader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->downloader = new UrlImportImageDownloader;
    }

    public function test_it_prioritizes_product_path_images_over_generic_banner(): void
    {
        $raw = [
            [
                'url' => 'https://example.com/assets/home-banner.jpg',
                'area' => 'hero',
                'width' => 1200,
                'height' => 400,
                'alt' => '首页轮播',
                'section_path' => '首页',
                'paragraph' => '',
                'link_href' => '',
            ],
            [
                'url' => 'https://example.com/upload/products/gateway-x.jpg',
                'area' => 'main',
                'width' => 640,
                'height' => 480,
                'alt' => '工业网关',
                'section_path' => '产品中心',
                'paragraph' => '面向工业现场的数据采集网关。',
                'link_href' => '/products/gateway-x',
            ],
            [
                'url' => 'https://example.com/static/footer.png',
                'area' => 'unknown',
                'width' => 200,
                'height' => 80,
                'alt' => '',
                'section_path' => '',
                'paragraph' => '',
                'link_href' => '',
            ],
        ];

        $eligible = $this->downloader->extractEligibleImages($raw, 'https://example.com/');

        $this->assertNotEmpty($eligible);
        $this->assertSame('https://example.com/upload/products/gateway-x.jpg', $eligible[0]['url']);
    }

    public function test_it_prioritizes_solution_page_images(): void
    {
        $raw = [
            [
                'url' => 'https://example.com/images/hero.jpg',
                'area' => 'hero',
                'width' => 1000,
                'height' => 500,
                'alt' => '',
                'section_path' => '',
                'paragraph' => '',
                'link_href' => '',
            ],
            [
                'url' => 'https://example.com/media/solution-smart-factory.png',
                'area' => 'main',
                'width' => 800,
                'height' => 600,
                'alt' => '智慧工厂方案',
                'section_path' => '智慧工厂解决方案',
                'paragraph' => '',
                'link_href' => '/solutions/smart-factory',
            ],
        ];

        $eligible = $this->downloader->extractEligibleImages(
            $raw,
            'https://example.com/solutions/smart-factory'
        );

        $this->assertSame('https://example.com/media/solution-smart-factory.png', $eligible[0]['url']);
    }
}
