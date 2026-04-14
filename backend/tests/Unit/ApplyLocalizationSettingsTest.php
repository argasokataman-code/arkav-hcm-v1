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
}
