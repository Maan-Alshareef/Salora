<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('booking_change_requests')) return;

        $legacy = DB::table('booking_change_requests')
            ->where('type', 'cancellation')
            ->where('status', 'pending')
            ->get(['id', 'booking_id']);

        foreach ($legacy as $request) {
            $requestUpdates = ['status' => 'cancelled'];
            if (Schema::hasColumn('booking_change_requests', 'decision_reason')) {
                $requestUpdates['decision_reason'] = 'تم إيقاف مسار طلب موافقة المالك على الإلغاء واعتماد الإلغاء المباشر حسب السياسة.';
            }
            if (Schema::hasColumn('booking_change_requests', 'decided_at')) {
                $requestUpdates['decided_at'] = now();
            }
            if (Schema::hasColumn('booking_change_requests', 'updated_at')) {
                $requestUpdates['updated_at'] = now();
            }
            DB::table('booking_change_requests')->where('id', $request->id)->update($requestUpdates);

            if (!Schema::hasTable('bookings') || !Schema::hasColumn('bookings', 'booking_status')) continue;
            $booking = DB::table('bookings')->where('id', $request->booking_id)->first();
            if (!$booking || !in_array((string) ($booking->booking_status ?? ''), ['cancellation_requested', 'pending_cancellation'], true)) continue;

            $bookingUpdates = ['booking_status' => 'confirmed'];
            if (Schema::hasColumn('bookings', 'cancellation_status')) $bookingUpdates['cancellation_status'] = null;
            if (Schema::hasColumn('bookings', 'edit_locked_at')) $bookingUpdates['edit_locked_at'] = null;
            if (Schema::hasColumn('bookings', 'updated_at')) $bookingUpdates['updated_at'] = now();
            DB::table('bookings')->where('id', $request->booking_id)->update($bookingUpdates);
        }
    }

    public function down(): void
    {
        // Historical normalization is intentionally not reversed.
    }
};
