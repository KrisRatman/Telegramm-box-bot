<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class HttpsProxyUrlTest extends TestCase
{
    public function test_links_use_https_app_url_when_site_is_behind_proxy(): void
    {
        config(['app.url' => 'https://bot.example.workers.dev']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('https://bot.example.workers.dev/admin/login', url('/admin/login'));
    }

    public function test_links_follow_request_when_app_url_is_http(): void
    {
        config(['app.url' => 'http://localhost']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('http://', url('/admin/login'));
    }
}
