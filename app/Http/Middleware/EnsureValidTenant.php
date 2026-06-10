<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->current_team_id === null) {
            return redirect()->route('dashboard');
        }

        if (! $user->belongsToTeam($user->currentTeam)) {
            abort(403, 'You do not belong to the selected team.');
        }

        return $next($request);
    }
}
