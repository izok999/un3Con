<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @var string[] */
    protected const array SUPPORTED_LOCALES = ['es', 'en', 'pt', 'gn'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? session('locale', config('app.locale'));

        if (in_array($locale, self::SUPPORTED_LOCALES, strict: true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
