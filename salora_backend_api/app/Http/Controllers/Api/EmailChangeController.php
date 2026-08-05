<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class EmailChangeController extends Controller
{
    public function requestCode(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $newEmail = strtolower(trim((string) ($request->input('new_email') ?: $request->input('email'))));

        Validator::make(
            ['new_email' => $newEmail],
            [
                'new_email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
            ],
            [
                'new_email.required' => 'يرجى إدخال البريد الإلكتروني الجديد.',
                'new_email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
                'new_email.unique' => 'البريد الإلكتروني مستخدم بحساب آخر.',
            ]
        )->validate();

        if (strcasecmp($newEmail, (string) $user->email) === 0) {
            return response()->json(['message' => 'البريد الجديد مطابق للبريد الحالي.'], 422);
        }

        $existing = DB::table('salora_email_change_requests')
            ->where('user_id', $user->id)
            ->first();

        if ($existing?->last_sent_at) {
            $lastSent = \Illuminate\Support\Carbon::parse($existing->last_sent_at);
            $remaining = 60 - $lastSent->diffInSeconds(now());
            if ($remaining > 0) {
                return response()->json([
                    'message' => "يمكن إعادة إرسال الرمز بعد {$remaining} ثانية.",
                    'retry_after' => $remaining,
                ], 429);
            }
        }

        $code = (string) random_int(100000, 999999);

        DB::table('salora_email_change_requests')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'new_email' => $newEmail,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
                'last_sent_at' => now(),
                'used_at' => null,
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?: now(),
            ]
        );

        try {
            Mail::raw(
                "رمز تأكيد تغيير البريد في Salora هو: {$code}\n\nصلاحية الرمز 10 دقائق. لا تشارك الرمز مع أي شخص.",
                function ($message) use ($newEmail) {
                    $message->to($newEmail)->subject('رمز تأكيد تغيير البريد - Salora');
                }
            );
        } catch (Throwable $exception) {
            DB::table('salora_email_change_requests')->where('user_id', $user->id)->delete();
            report($exception);

            return response()->json([
                'message' => 'تعذر إرسال الرمز حالياً. تحقق من إعدادات البريد ثم أعد المحاولة.',
            ], 422);
        }

        return response()->json([
            'message' => 'تم إرسال رمز OTP إلى البريد الجديد. تحقق من صندوق الوارد أو الرسائل غير المرغوب بها.',
            'email' => $newEmail,
            'masked_email' => $this->maskEmail($newEmail),
            'expires_in' => 600,
            'expires_in_seconds' => 600,
            'resend_after' => 60,
            'resend_after_seconds' => 60,
            'mail_sent' => true,
        ]);
    }

    public function verifyCode(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $requestedEmail = strtolower(trim((string) ($request->input('new_email') ?: $request->input('email'))));
        $code = trim((string) ($request->input('code') ?: $request->input('otp')));

        Validator::make(
            ['code' => $code],
            ['code' => ['required', 'digits:6']],
            [
                'code.required' => 'يرجى إدخال رمز التأكيد.',
                'code.digits' => 'رمز التأكيد يجب أن يتكون من 6 أرقام.',
            ]
        )->validate();

        $query = DB::table('salora_email_change_requests')
            ->where('user_id', $user->id)
            ->whereNull('used_at');

        if ($requestedEmail !== '') {
            $query->where('new_email', $requestedEmail);
        }

        $pending = $query->first();

        if (!$pending) {
            return response()->json(['message' => 'لا يوجد طلب تغيير بريد فعال. أرسل رمزاً جديداً أولاً.'], 422);
        }

        $newEmail = strtolower(trim((string) $pending->new_email));

        Validator::make(
            ['new_email' => $newEmail],
            ['new_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)]],
            ['new_email.unique' => 'البريد الإلكتروني أصبح مستخدماً بحساب آخر.']
        )->validate();

        if (now()->greaterThan(\Illuminate\Support\Carbon::parse($pending->expires_at))) {
            return response()->json(['message' => 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.'], 422);
        }

        if ((int) $pending->attempts >= 5) {
            return response()->json(['message' => 'تم تجاوز عدد المحاولات. اطلب رمزاً جديداً.'], 422);
        }

        if (!Hash::check($code, $pending->code_hash)) {
            $attempts = (int) $pending->attempts + 1;
            DB::table('salora_email_change_requests')
                ->where('user_id', $user->id)
                ->update(['attempts' => $attempts, 'updated_at' => now()]);

            return response()->json([
                'message' => 'رمز التأكيد غير صحيح.',
                'remaining_attempts' => max(0, 5 - $attempts),
            ], 422);
        }

        $oldEmail = (string) $user->email;

        DB::transaction(function () use ($user, $newEmail) {
            DB::table('users')->where('id', $user->id)->update([
                'email' => $newEmail,
                'email_verified_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('salora_email_change_requests')->where('user_id', $user->id)->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if ($oldEmail !== '' && strcasecmp($oldEmail, $newEmail) !== 0) {
            try {
                Mail::raw(
                    "تم تغيير البريد الإلكتروني المرتبط بحساب Salora إلى {$newEmail}. إذا لم تقم بهذا الإجراء فتواصل مع الإدارة فوراً.",
                    function ($message) use ($oldEmail) {
                        $message->to($oldEmail)->subject('تم تغيير بريد حساب Salora');
                    }
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $freshUser = $user->fresh();

        return response()->json([
            'message' => 'تم تغيير البريد الإلكتروني وتأكيده بنجاح.',
            'email' => $freshUser->email,
            'user' => $freshUser,
        ]);
    }

    public function cancel(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        DB::table('salora_email_change_requests')->where('user_id', $user->id)->delete();

        return response()->json(['message' => 'تم إلغاء طلب تغيير البريد.']);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($local === '' || $domain === '') {
            return $email;
        }

        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible . str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
    }
}