<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Forbidden: this role cannot access this resource.'], 403);
        }

        if (in_array($user->role, ['owner', 'provider'], true) && $user->must_change_password) {
            return response()->json([
                'message' => 'يجب تغيير كلمة المرور المؤقتة قبل استخدام وظائف الحساب.',
                'errors' => ['code' => 'must_change_password'],
            ], 403);
        }

        return $next($request);
    }
}
