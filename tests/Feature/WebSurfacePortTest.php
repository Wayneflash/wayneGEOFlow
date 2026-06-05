<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebSurfacePortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost:18080',
            'geoflow.site_url' => 'http://localhost:18081',
        ]);
    }

    public function test_admin_port_root_redirects_to_admin_login(): void
    {
        $this->get('http://localhost:18080/')
            ->assertRedirect('http://localhost:18080/geo_admin/login');
    }

    public function test_front_site_paths_on_admin_port_redirect_permanently_to_site_port(): void
    {
        $this->get('http://localhost:18080/article/example-slug?utm=old')
            ->assertStatus(301)
            ->assertRedirect('http://localhost:18081/article/example-slug?utm=old');
    }

    public function test_admin_paths_on_site_port_redirect_to_admin_port(): void
    {
        $this->get('http://localhost:18081/geo_admin/login')
            ->assertStatus(302)
            ->assertRedirect('http://localhost:18080/geo_admin/login');
    }
}
