<?php

namespace App\Http\Middleware;

use App\Support\WebsiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyLocalizationSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = WebsiteSettings::localizationTimezone();
        $locale = WebsiteSettings::localizationLanguage();

        if ($timezone !== '') {
            config(['app.timezone' => $timezone]);
            @date_default_timezone_set($timezone);
        }

        if ($locale !== '') {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}