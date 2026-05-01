<?php

namespace App\Http\Middleware;

use App\Support\RuntimeLocalization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyLocalizationSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        RuntimeLocalization::apply();

        return $next($request);
    }
}