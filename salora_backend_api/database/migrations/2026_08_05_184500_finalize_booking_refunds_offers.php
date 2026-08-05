<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('offers')) {
                DB::table('offers')->delete();
            }
            if (Schema::hasTable('venue_offers')) {
                DB::table('venue_offers')->delete();
            }

            if (Schema::hasTable('invoices')) {
                DB::table('invoices')
                    ->where('status', 'unpaid')
                    ->update([
                        'due_at' => now()->addHours(6),
                        'payment_deadline_at' => now()->addHours(6),
                        'payment_reminder_sent_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasTable('bookings')) {
                DB::table('bookings')
                    ->whereIn('booking_status', ['pending_owner_review', 'pending_payment'])
                    ->update(['expires_at' => now()->addHours(6), 'updated_at' => now()]);
                DB::table('bookings')
                    ->where('booking_status', 'payment_under_review')
                    ->update(['expires_at' => null, 'updated_at' => now()]);
            }

            if (Schema::hasTable('payment_methods')) {
                DB::table('payment_methods')->where('slug', 'sham_cash')->update([
                    'instructions' => 'حوّل كامل المبلغ إلى الحساب الظاهر ثم ارفع صورة الإيصال الأصلية واكتب اسم المحوّل فقط.',
                    'updated_at' => now(),
                ]);
                DB::table('payment_methods')->where('slug', 'syriatel_cash')->update([
                    'instructions' => 'حوّل كامل المبلغ إلى المحفظة الظاهرة ثم ارفع صورة الإيصال الأصلية واكتب اسم المحوّل فقط.',
                    'updated_at' => now(),
                ]);
                DB::table('payment_methods')->where('slug', 'al_haram')->update([
                    'instructions' => 'نفّذ الحوالة إلى المستلم الظاهر ثم ارفع صورة الإيصال الأصلية واكتب اسم المحوّل فقط.',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Deliberately empty: old offers are backed up by the installer before this migration runs.
    }
};
