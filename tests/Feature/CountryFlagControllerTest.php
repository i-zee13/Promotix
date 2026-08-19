<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CountryFlagControllerTest extends TestCase
{
    public function test_invalid_code_is_not_found(): void
    {
        $this->get('/media/flags/usa')->assertNotFound();
        $this->get('/media/flags/1')->assertNotFound();
    }

    public function test_successful_cdn_fetch_is_cached_as_png(): void
    {
        Http::fake([
            'flagcdn.com/*' => Http::response(str_repeat('P', 64), 200, ['Content-Type' => 'image/png']),
        ]);

        $path = storage_path('app/flags/us.png');
        if (is_file($path)) {
            unlink($path);
        }

        $this->get('/media/flags/us')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertFileExists($path);
        unlink($path);
    }

    public function test_cdn_failure_returns_svg_badge(): void
    {
        Http::fake([
            'flagcdn.com/*' => Http::response('nope', 404),
        ]);

        $path = storage_path('app/flags/zz.png');
        if (is_file($path)) {
            unlink($path);
        }

        $this->get('/media/flags/zz')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertSee('ZZ', false);
    }
}
