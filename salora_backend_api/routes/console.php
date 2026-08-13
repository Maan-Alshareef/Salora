<?php

use App\Models\EmailOtp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('salora:ping', fn () => $this->info('Salora backend is ready.'));

Artisan::command('salora:test-email {email}', function (string $email): int {
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Please provide a valid recipient email address.');
        return self::FAILURE;
    }

    try {
        Mail::raw('Salora email configuration is working correctly.', function ($message) use ($email): void {
            $message->to($email)->subject('Salora email test');
        });
        $this->info('Test email sent to '.$email.'.');
        return self::SUCCESS;
    } catch (Throwable $exception) {
        $this->error('Email delivery failed: '.$exception->getMessage());
        return self::FAILURE;
    }
});


Artisan::command('salora:prune-email-otps', function (): int {
    $deleted = EmailOtp::query()
        ->where(function ($query): void {
            $query->whereNotNull('used_at')
                ->orWhere('expires_at', '<', now());
        })
        ->where('updated_at', '<', now()->subDay())
        ->delete();

    $this->info('Pruned '.$deleted.' expired or used OTP records.');
    return self::SUCCESS;
});

Schedule::command('salora:prune-email-otps')
    ->dailyAt('03:15')
    ->withoutOverlapping();
require __DIR__.'/salora_uc01_uc20_console.php';

