<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOAuthUserHasDocumento
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && filled($user->auth_provider) && blank($user->documento) && ! $request->routeIs('auth.oauth.link-documento')) {
            return redirect()->route('auth.oauth.link-documento');
        }

        return $next($request);
    }
}
