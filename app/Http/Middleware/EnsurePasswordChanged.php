<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password
            && ! in_array($request->route()?->getName(), ['account.password.edit', 'account.password.update'], true)) {
            return redirect()->route('account.password.edit');
        }

        return $next($request);
    }
}
