<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountStatusMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $user->reactivateIfSuspensionExpired();
        $user->refresh();

        if ($user->status === 'active') {
            $passwordChangeAllowed = $request->is(
                'api/auth/me',
                'api/auth/change-password',
                'api/auth/logout'
            );

            if (
                in_array($user->role, ['owner', 'provider'], true)
                && $user->must_change_password
                && !$passwordChangeAllowed
            ) {
                return response()->json([
                    'message' => 'يجب تغيير كلمة المرور المؤقتة قبل استخدام وظائف الحساب.',
                    'errors' => ['code' => 'must_change_password'],
                ], 403);
            }

            return $next($request);
        }

        $user->currentAccessToken()?->delete();

        $code = $user->status === 'suspended' ? 'account_suspended' : 'account_inactive';
        $message = $user->status === 'suspended'
            ? 'الحساب مجمّد مؤقتاً.'
            : 'الحساب غير نشط.';

        return response()->json([
            'message' => $message,
            'errors' => [
                'code' => $code,
                'suspended_until' => $user->suspended_until?->toIso8601String(),
                'reason' => $user->suspension_reason,
            ],
        ], 403);
    }
}
