<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeSyrianPhone
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->exists('phone') && filled($request->input('phone'))) {
            $phone = strtr((string) $request->input('phone'), [
                '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
                '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
                '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            ]);
            $phone = preg_replace('/\D+/', '', $phone) ?? '';
            $request->merge(['phone' => $phone]);

            if (!preg_match('/^09\d{8}$/', $phone)) {
                return response()->json([
                    'message' => 'أدخل رقم هاتف سوري صحيحاً مكوّناً من 10 أرقام ويبدأ بـ 09.',
                    'errors' => ['phone' => ['أدخل رقم هاتف سوري صحيحاً مكوّناً من 10 أرقام ويبدأ بـ 09.']],
                ], 422);
            }
        }

        return $next($request);
    }
}
