<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->admin_locale;

        if (! in_array($locale, config('localization.supported'), true)) {
            $locale = config('localization.default');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
