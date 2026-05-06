<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyUserHasCompletedAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && filled($user->documento)
            && $this->usesLegacyPlaceholderEmail($user->email)
            && ! $request->routeIs('auth.legacy.complete-account')
        ) {
            return redirect()->route('auth.legacy.complete-account');
        }

        return $next($request);
    }

    protected function usesLegacyPlaceholderEmail(string $email): bool
    {
        return Str::endsWith(Str::lower($email), '@consultor.invalid');
    }
}
