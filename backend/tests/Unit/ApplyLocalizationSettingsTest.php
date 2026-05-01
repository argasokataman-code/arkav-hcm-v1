<?php

namespace Tests\Unit;

use App\Http\Middleware\ApplyLocalizationSettings;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApplyLocalizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_applies_timezone_and_locale_from_settings(): void
    {
        Setting::set('localization_timezone', 'Asia/Jakarta', 'localization');
        Setting::set('localization_language', 'id', 'localization');

        $middleware = new ApplyLocalizationSettings();
        $request = Request::create('/health', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('id', app()->getLocale());
    }

    public function test_middleware_falls_back_when_stored_timezone_is_invalid(): void
    {
        config(['app.timezone' => 'Europe/London']);

        Setting::set('localization_timezone', 'GMT+7', 'localization');
        Setting::set('localization_language', 'en', 'localization');

        $middleware = new ApplyLocalizationSettings();
        $request = Request::create('/health', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Europe/London', config('app.timezone'));
        $this->assertSame('Europe/London', date_default_timezone_get());
    }
}
