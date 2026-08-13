<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\EventType;
use App\Models\User;
use App\Models\Venue;
use App\Services\SaloraBookingV2Service;
use App\Support\SaloraStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FullBookingModificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_venue_gets_seven_default_working_hour_rows(): void
    {
        $owner = $this->user('owner', 'default-hours-owner@example.test');
        $type = EventType::create([
            'name_ar' => 'زفاف',
            'name_en' => 'Wedding',
            'is_active' => true,
        ]);

        $venue = $this->venue($owner, $type);

        $this->assertSame(
            7,
            DB::table('venue_working_hours')->where('venue_id', $venue->id)->count()
        );
        $this->assertSame(
            7,
            DB::table('venue_working_hours')
                ->where('venue_id', $venue->id)
                ->where('is_closed', false)
                ->where('open_time', '09:00')
                ->where('close_time', '23:00')
                ->count()
        );
    }

    public function test_booking_change_updates_day_time_guests_offer_price_and_releases_old_slot(): void
    {
        $owner = $this->user('owner', 'edit-owner@example.test');
        $customer = $this->user('customer', 'edit-customer@example.test');
        $type = EventType::create([
            'name_ar' => 'مؤتمر',
            'name_en' => 'Conference',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $type);

        $oldStart = now()->addDays(20)->startOfDay()->setTime(10, 0);
        $oldEnd = $oldStart->copy()->addHours(2);
        $newStart = now()->addDays(21)->startOfDay()->setTime(14, 0);
        $newEnd = $newStart->copy()->addHours(2);

        $booking = Booking::create([
            'booking_number' => 'EDIT-'.strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $customer->id,
            'venue_id' => $venue->id,
            'owner_id' => $owner->id,
            'event_type_id' => $type->id,
            'event_name' => 'حجز قابل للتعديل',
            'event_date' => $oldStart->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'guests_count' => 100,
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
            'payment_status' => SaloraStatus::PAYMENT_UNPAID,
            'subtotal_syp' => 200000,
            'discount_syp' => 0,
            'total_syp' => 200000,
            'currency' => 'SYP',
        ]);

        DB::table('bookings')->where('id', $booking->id)->update([
            'start_at' => $oldStart->toDateTimeString(),
            'end_at' => $oldEnd->toDateTimeString(),
            'duration_minutes' => 120,
            'hourly_price_snapshot_syp' => 100000,
            'price_before_discount_syp' => 200000,
            'final_price_syp' => 200000,
            'owner_retained_syp' => 200000,
            'commission_rate' => 10,
            'commission_syp' => 20000,
        ]);

        $offerId = DB::table('venue_offers')->insertGetId([
            'venue_id' => $venue->id,
            'title' => 'عرض اليوم الجديد',
            'offer_type' => 'percentage',
            'percentage' => 25,
            'starts_on' => $newStart->toDateString(),
            'ends_on' => $newStart->toDateString(),
            'is_active' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(SaloraBookingV2Service::class);
        $quote = $service->applyApprovedChange($booking->id, [
            'venue_id' => $venue->id,
            'start_at' => $newStart->toIso8601String(),
            'end_at' => $newEnd->toIso8601String(),
            'guests_count' => 150,
        ], $customer->id);

        $booking = $booking->fresh();

        $this->assertSame($newStart->toDateString(), $booking->event_date->toDateString());
        $this->assertSame('14:00', substr((string) $booking->start_time, 0, 5));
        $this->assertSame('16:00', substr((string) $booking->end_time, 0, 5));
        $this->assertSame(150, (int) $booking->guests_count);
        $this->assertSame($offerId, (int) $booking->offer_id);
        $this->assertSame(50000.0, (float) $booking->discount_syp);
        $this->assertSame(150000.0, (float) $booking->final_price_syp);
        $this->assertSame(150000.0, (float) $booking->total_syp);
        $this->assertSame(15000.0, (float) $booking->commission_syp);
        $this->assertSame(150000.0, (float) $quote['final_price_syp']);

        $oldSlotQuote = $service->quote($venue->id, $oldStart, $oldEnd);
        $this->assertTrue((bool) $oldSlotQuote['available']);

        $this->expectException(ValidationException::class);
        $service->quote($venue->id, $newStart, $newEnd);
    }

    public function test_change_request_keeps_old_slot_until_owner_approval_and_freezes_offer(): void
    {
        $owner = $this->user('owner', 'request-owner@example.test');
        $customer = $this->user('customer', 'request-customer@example.test');
        $type = EventType::create([
            'name_ar' => 'زفاف',
            'name_en' => 'Wedding',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $type);

        $oldStart = now()->addDays(20)->startOfDay()->setTime(10, 0);
        $oldEnd = $oldStart->copy()->addHours(2);
        $newStart = now()->addDays(21)->startOfDay()->setTime(14, 0);
        $newEnd = $newStart->copy()->addHours(2);

        $booking = Booking::create([
            'booking_number' => 'REQ-'.strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $customer->id,
            'venue_id' => $venue->id,
            'owner_id' => $owner->id,
            'event_type_id' => $type->id,
            'event_name' => 'طلب تعديل رسمي',
            'event_date' => $oldStart->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'guests_count' => 100,
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
            'payment_status' => SaloraStatus::PAYMENT_UNPAID,
            'subtotal_syp' => 200000,
            'discount_syp' => 0,
            'total_syp' => 200000,
            'currency' => 'SYP',
        ]);
        DB::table('bookings')->where('id', $booking->id)->update([
            'start_at' => $oldStart->toDateTimeString(),
            'end_at' => $oldEnd->toDateTimeString(),
            'duration_minutes' => 120,
            'hourly_price_snapshot_syp' => 100000,
            'price_before_discount_syp' => 200000,
            'final_price_syp' => 200000,
            'owner_retained_syp' => 200000,
            'commission_rate' => 10,
            'commission_syp' => 20000,
        ]);

        $offerId = DB::table('venue_offers')->insertGetId([
            'venue_id' => $venue->id,
            'title' => 'عرض مثبت للطلب',
            'offer_type' => 'percentage',
            'percentage' => 20,
            'starts_on' => $newStart->toDateString(),
            'ends_on' => $newStart->toDateString(),
            'is_active' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestResponse = $this->actingAs($customer, 'sanctum')->postJson(
            '/api/salora-v2/bookings/'.$booking->id.'/change-requests',
            [
                'start_at' => $newStart->toIso8601String(),
                'end_at' => $newEnd->toIso8601String(),
                'guests_count' => 130,
            ]
        );
        $requestResponse->assertCreated();
        $requestId = (int) $requestResponse->json('request_id');

        $booking->refresh();
        $this->assertSame($oldStart->toDateString(), $booking->event_date->toDateString());
        $this->assertSame('10:00', substr((string) $booking->start_time, 0, 5));
        $this->assertSame(100, (int) $booking->guests_count);
        $this->assertDatabaseHas('booking_change_requests', [
            'id' => $requestId,
            'booking_id' => $booking->id,
            'status' => 'pending',
        ]);

        // Offer can be disabled after the customer submits the request. The quote
        // shown to the customer must remain the approved commercial snapshot.
        DB::table('venue_offers')->where('id', $offerId)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        $approveResponse = $this->actingAs($owner, 'sanctum')->postJson(
            '/api/salora-v2/bookings/'.$booking->id.'/change-requests/'.$requestId.'/approve',
            []
        );
        $approveResponse->assertOk();

        $booking->refresh();
        $this->assertSame($newStart->toDateString(), $booking->event_date->toDateString());
        $this->assertSame('14:00', substr((string) $booking->start_time, 0, 5));
        $this->assertSame('16:00', substr((string) $booking->end_time, 0, 5));
        $this->assertSame(130, (int) $booking->guests_count);
        $this->assertSame($offerId, (int) $booking->offer_id);
        $this->assertSame(40000.0, (float) $booking->discount_syp);
        $this->assertSame(160000.0, (float) $booking->final_price_syp);
        $this->assertDatabaseHas('booking_change_requests', [
            'id' => $requestId,
            'status' => 'approved',
        ]);

        $service = app(SaloraBookingV2Service::class);
        $oldSlot = $service->quote($venue->id, $oldStart, $oldEnd);
        $this->assertTrue((bool) $oldSlot['available']);
    }

    public function test_paid_booking_change_creates_separate_payment_adjustment(): void
    {
        $owner = $this->user('owner', 'paid-edit-owner@example.test');
        $customer = $this->user('customer', 'paid-edit-customer@example.test');
        $type = EventType::create([
            'name_ar' => 'مؤتمر',
            'name_en' => 'Conference',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $type);

        $oldStart = now()->addDays(20)->startOfDay()->setTime(10, 0);
        $oldEnd = $oldStart->copy()->addHours(2);
        $newStart = now()->addDays(21)->startOfDay()->setTime(14, 0);
        $newEnd = $newStart->copy()->addHours(3);

        $booking = Booking::create([
            'booking_number' => 'PAID-'.strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $customer->id,
            'venue_id' => $venue->id,
            'owner_id' => $owner->id,
            'event_type_id' => $type->id,
            'event_name' => 'حجز مدفوع قابل للتعديل',
            'event_date' => $oldStart->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'guests_count' => 100,
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
            'payment_status' => SaloraStatus::PAYMENT_APPROVED,
            'subtotal_syp' => 200000,
            'discount_syp' => 0,
            'total_syp' => 200000,
            'currency' => 'SYP',
        ]);
        DB::table('bookings')->where('id', $booking->id)->update([
            'start_at' => $oldStart->toDateTimeString(),
            'end_at' => $oldEnd->toDateTimeString(),
            'duration_minutes' => 120,
            'hourly_price_snapshot_syp' => 100000,
            'price_before_discount_syp' => 200000,
            'final_price_syp' => 200000,
            'owner_retained_syp' => 200000,
            'commission_rate' => 10,
            'commission_syp' => 20000,
        ]);

        $invoiceId = DB::table('invoices')->insertGetId([
            'invoice_number' => 'INV-'.strtoupper(bin2hex(random_bytes(4))),
            'booking_id' => $booking->id,
            'source_type' => 'venue_booking',
            'source_id' => $booking->id,
            'customer_id' => $customer->id,
            'payee_id' => $owner->id,
            'subtotal_syp' => 200000,
            'subtotal_usd' => 0,
            'discount_syp' => 0,
            'discount_usd' => 0,
            'total_syp' => 200000,
            'total_usd' => 0,
            'currency' => 'SYP',
            'status' => 'paid',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payment_proofs')->insert([
            'booking_id' => $booking->id,
            'invoice_id' => $invoiceId,
            'customer_id' => $customer->id,
            'amount_syp' => 200000,
            'amount_usd' => 0,
            'payment_method' => 'bank_transfer',
            'sender_name' => 'عميل الاختبار',
            'transaction_reference' => 'TRX-'.strtoupper(bin2hex(random_bytes(3))),
            'status' => 'approved',
            'attempt_no' => 1,
            'uploaded_at' => now(),
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestResponse = $this->actingAs($customer, 'sanctum')->postJson(
            '/api/salora-v2/bookings/'.$booking->id.'/change-requests',
            [
                'start_at' => $newStart->toIso8601String(),
                'end_at' => $newEnd->toIso8601String(),
                'guests_count' => 100,
            ]
        );
        $requestResponse->assertCreated();
        $requestId = (int) $requestResponse->json('request_id');

        $this->actingAs($owner, 'sanctum')->postJson(
            '/api/salora-v2/bookings/'.$booking->id.'/change-requests/'.$requestId.'/approve',
            []
        )->assertOk();

        $this->assertDatabaseHas('salora_booking_payment_adjustments', [
            'booking_id' => $booking->id,
            'type' => 'additional_payment',
            'amount_syp' => 100000,
            'status' => 'pending_payment',
        ]);
        $this->assertDatabaseHas('payment_proofs', [
            'booking_id' => $booking->id,
            'amount_syp' => 200000,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'total_syp' => 300000,
        ]);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $email,
            'phone' => '09'.str_pad((string) (User::count() + 1), 8, '0', STR_PAD_LEFT),
            'password' => 'Strong@123',
            'role' => $role,
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    private function venue(User $owner, EventType $type): Venue
    {
        $venue = Venue::create([
            'owner_id' => $owner->id,
            'name_ar' => 'صالة تعديل كاملة',
            'name_en' => 'Full Edit Hall',
            'city' => 'دمشق',
            'address' => 'شارع الاختبار',
            'capacity' => 500,
            'price_syp' => 100000,
            'price_usd' => 0,
            'currency_base' => 'SYP',
            'status' => 'approved',
            'hourly_price_syp' => 100000,
            'minimum_booking_minutes' => 120,
            'maximum_booking_minutes' => 480,
            'cleanup_minutes' => 0,
        ]);
        $venue->eventTypes()->attach($type->id);
        return $venue;
    }
}
