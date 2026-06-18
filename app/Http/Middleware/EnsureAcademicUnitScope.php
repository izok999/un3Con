<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademicUnitScope
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ?User $user */
        $user = $request->user();

        if ($user && $user->isAcademicUnitAdmin() && $user->academicUnitScopes()->doesntExist()) {
            abort(403, 'El administrador de unidad académica no tiene sedes asignadas.');
        }

        return $next($request);
    }
}
