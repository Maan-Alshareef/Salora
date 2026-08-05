<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailOtpService
{
    public const EXPIRY_MINUTES = 10;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    /**
     * Issue and send a new OTP to a registered user.
     */
    public function issue(User $user, string $purpose, ?string $requestIp = null): array
    {
        return $this->issueForEmail($user->email, $purpose, $user, $requestIp);
    }

    /**
     * Issue and send a new OTP to an arbitrary email address.
     * This is used for business-account applications where the requested
     * owner/provider account must use an email different from the customer account.
     */
    public function issueForEmail(
        string $email,
        string $purpose,
        ?User $user = null,
        ?string $requestIp = null,
    ): array {
        $normalizedEmail = mb_strtolower(trim($email));
        $latest = EmailOtp::query()
            ->where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if ($latest?->resend_available_at?->isFuture()) {
            $retryAfter = max(1, now()->diffInSeconds($latest->resend_available_at, false));
            throw new OtpCooldownException($retryAfter);
        }

        EmailOtp::query()
            ->where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);
        $otp = EmailOtp::create([
            'user_id' => $user?->id,
            'email' => $normalizedEmail,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'resend_available_at' => now()->addSeconds(self::RESEND_COOLDOWN_SECONDS),
            'request_ip' => $requestIp,
        ]);

        try {
            Mail::to($normalizedEmail)->send(new OtpCodeMail($code, $purpose, self::EXPIRY_MINUTES));
        } catch (\Throwable $exception) {
            $otp->update(['used_at' => now()]);
            Log::error('Salora OTP email delivery failed.', [
                'email' => $normalizedEmail,
                'purpose' => $purpose,
                'exception' => $exception->getMessage(),
            ]);
            throw new OtpDeliveryException(previous: $exception);
        }

        $result = [
            'email' => $normalizedEmail,
            'masked_email' => $this->maskEmail($normalizedEmail),
            'mail_sent' => true,
            'expires_in_seconds' => self::EXPIRY_MINUTES * 60,
            'resend_after_seconds' => self::RESEND_COOLDOWN_SECONDS,
        ];

        if (app()->environment(['local', 'testing']) && (bool) config('salora.otp.expose_in_local', false)) {
            $result['demo_otp'] = $code;
        }

        return $result;
    }

    public function verify(string $email, string $purpose, string $code): EmailOtp
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $otp = EmailOtp::query()
            ->where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$otp || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            throw new InvalidOtpException();
        }

        if (!Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            if ($otp->fresh()->attempts >= self::MAX_ATTEMPTS) {
                $otp->update(['used_at' => now()]);
            }
            throw new InvalidOtpException();
        }

        $otp->update(['used_at' => now()]);
        return $otp->fresh();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '') return $email;
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
