<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Mail\OtpCodeMail;
use App\Models\EventType;
use App\Models\PaymentProof;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\ProviderServiceRequest;
use App\Services\InvoiceService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TodoTemplate;
use App\Models\User;
use App\Models\Venue;
use App\Support\SaloraStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UniversityCoreFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_requires_email_otp_before_token_is_issued(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Student Customer',
            'email' => 'student@example.test',
            'phone' => '0999000001',
            'password' => 'Strong@123',
            'password_confirmation' => 'Strong@123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.verification_required', true)
            ->assertJsonPath('data.email', 'student@example.test')
            ->assertJsonMissingPath('data.token');

        $code = null;
        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code): bool {
            $code = $mail->code;
            return $mail->hasTo('student@example.test');
        });
        $this->assertNotNull($code);

        $this->postJson('/api/auth/verify-email', [
            'email' => 'student@example.test',
            'otp' => $code,
        ])->assertOk()
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role', 'avatar_url']]]);

        $this->assertDatabaseHas('users', ['email' => 'student@example.test', 'role' => 'customer']);
        $this->assertNotNull(User::where('email', 'student@example.test')->value('email_verified_at'));
    }

    public function test_business_account_must_change_its_first_password(): void
    {
        $owner = $this->user('owner', true, 'owner@example.test');
        Sanctum::actingAs($owner);

        $this->getJson('/api/owner/venues')
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'must_change_password');
    }

    public function test_event_creation_generates_the_configured_todo_list(): void
    {
        $customer = $this->user('customer', false, 'customer@example.test');
        $type = EventType::create(['name_ar' => 'زفاف', 'name_en' => 'Wedding', 'is_active' => true]);
        TodoTemplate::create([
            'event_type_id' => $type->id,
            'task_ar' => 'حجز صالة',
            'task_en' => 'Book a venue',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Sanctum::actingAs($customer);

        $date = now()->addDays(30)->toDateString();
        $response = $this->postJson('/api/customer/events', [
            'event_type_id' => $type->id,
            'name' => 'حفل التخرج',
            'event_date' => $date,
            'guests_count' => 150,
            'budget_syp' => 5000000,
            'city' => 'دمشق',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'حفل التخرج')
            ->assertJsonPath('data.todo_items.0.title', 'حجز صالة');
        $this->assertDatabaseHas('events', ['customer_id' => $customer->id, 'name' => 'حفل التخرج']);
        $this->assertDatabaseHas('event_todo_items', ['title' => 'حجز صالة']);
    }

    public function test_overlapping_active_venue_booking_is_rejected(): void
    {
        $owner = $this->user('owner', false, 'hall@example.test');
        $customer = $this->user('customer', false, 'booker@example.test');
        $type = EventType::create(['name_ar' => 'مؤتمر', 'name_en' => 'Conference', 'is_active' => true]);
        $venue = $this->venue($owner, $type);
        $date = now()->addDays(45)->toDateString();
        $this->booking($customer, $owner, $venue, $type, [
            'event_date' => $date,
            'start_time' => '18:00',
            'end_time' => '22:00',
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'venue_id' => $venue->id,
            'event_type_id' => $type->id,
            'event_name' => 'طلب متداخل',
            'event_date' => $date,
            'start_time' => '20:00',
            'end_time' => '23:00',
            'guests_count' => 100,
            'currency' => 'SYP',
        ])->assertStatus(409)
            ->assertJsonPath('errors.code', 'venue_time_conflict')
            ->assertJsonPath('message', 'هذا الموعد محجوز أو يتعارض مع حجز آخر. اختر وقتاً مختلفاً.');
    }

    public function test_new_booking_goes_directly_to_payment_and_creates_its_invoice(): void
    {
        $owner = $this->user('owner', false, 'direct-owner@example.test');
        $customer = $this->user('customer', false, 'direct-customer@example.test');
        $type = EventType::create(['name_ar' => 'زفاف', 'name_en' => 'Wedding', 'is_active' => true]);
        $venue = $this->venue($owner, $type);
        $date = now()->addDays(60)->toDateString();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/customer/bookings', [
            'venue_id' => $venue->id,
            'event_type_id' => $type->id,
            'event_name' => 'حجز مباشر للدفع',
            'event_date' => $date,
            'start_time' => '18:00',
            'end_time' => '22:00',
            'guests_count' => 120,
            'currency' => 'SYP',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.booking_status', SaloraStatus::BOOKING_PENDING_PAYMENT)
            ->assertJsonPath('data.payment_status', SaloraStatus::PAYMENT_UNPAID)
            ->assertJsonPath('data.invoice.status', 'unpaid');

        $bookingId = (int) $response->json('data.id');
        $invoiceId = (int) $response->json('data.invoice.id');
        $this->assertGreaterThan(0, $invoiceId);
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'booking_status' => SaloraStatus::BOOKING_PENDING_PAYMENT,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'booking_id' => $bookingId,
            'status' => 'unpaid',
        ]);
        $this->assertDatabaseMissing('bookings', [
            'id' => $bookingId,
            'booking_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
        ]);
    }

    public function test_owner_accepting_payment_proof_is_the_only_final_booking_confirmation(): void
    {
        $owner = $this->user('owner', false, 'confirm-owner@example.test');
        $customer = $this->user('customer', false, 'confirm-customer@example.test');
        $type = EventType::create(['name_ar' => 'اجتماع', 'name_en' => 'Meeting', 'is_active' => true]);
        $venue = $this->venue($owner, $type);
        $booking = $this->booking($customer, $owner, $venue, $type, [
            'booking_status' => SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
            'payment_status' => SaloraStatus::PAYMENT_PROOF_UPLOADED,
            'expires_at' => null,
        ]);
        $invoice = app(InvoiceService::class)->createForBooking($booking);
        $proof = PaymentProof::create([
            'booking_id' => $booking->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'owner_id' => $owner->id,
            'image_url' => 'payment-proofs/test-proof.png',
            'amount_syp' => $booking->total_syp,
            'amount_usd' => 0,
            'payment_method' => 'manual_transfer',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/owner/payments/'.$proof->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
            'payment_status' => SaloraStatus::PAYMENT_APPROVED,
        ]);
        $this->assertDatabaseHas('payment_proofs', [
            'id' => $proof->id,
            'status' => 'approved',
            'reviewer_id' => $owner->id,
            'reviewer_role' => 'owner',
        ]);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
            'to_status' => SaloraStatus::BOOKING_CONFIRMED,
            'changed_by' => $owner->id,
        ]);
    }

    public function test_payment_proof_file_is_private_and_scoped_to_the_booking(): void
    {
        Storage::fake('local');
        $owner = $this->user('owner', false, 'proof-owner@example.test');
        $customer = $this->user('customer', false, 'proof-customer@example.test');
        $other = $this->user('customer', false, 'other@example.test');
        $type = EventType::create(['name_ar' => 'اجتماع', 'name_en' => 'Meeting', 'is_active' => true]);
        $venue = $this->venue($owner, $type);
        $booking = $this->booking($customer, $owner, $venue, $type);
        Storage::disk('local')->put('payment-proofs/proof.png', 'private-proof');
        $proof = PaymentProof::create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'owner_id' => $owner->id,
            'image_url' => 'payment-proofs/proof.png',
            'payment_method' => 'manual_transfer',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($other);
        $this->get('/api/payment-proofs/'.$proof->id.'/image')->assertForbidden();

        Sanctum::actingAs($customer);
        $response = $this->get('/api/payment-proofs/'.$proof->id.'/image')
            ->assertOk();

        // Header directive order may be normalized by Symfony; compare semantics.
        $this->assertEqualsCanonicalizing(
            ['private', 'no-store', 'max-age=0'],
            array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')))
        );
    }

    public function test_review_requires_a_completed_owned_booking_and_cannot_be_duplicated(): void
    {
        $owner = $this->user('owner', false, 'review-owner@example.test');
        $customer = $this->user('customer', false, 'reviewer@example.test');
        $type = EventType::create(['name_ar' => 'تخرج', 'name_en' => 'Graduation', 'is_active' => true]);
        $venue = $this->venue($owner, $type);
        $pending = $this->booking($customer, $owner, $venue, $type);
        Sanctum::actingAs($customer);

        $payload = ['booking_id' => $pending->id, 'venue_id' => $venue->id, 'rating' => 5, 'comment' => 'ممتاز'];
        $this->postJson('/api/customer/reviews', $payload)->assertStatus(422);

        $pending->update(['booking_status' => SaloraStatus::BOOKING_COMPLETED]);
        $this->postJson('/api/customer/reviews', $payload)->assertCreated();
        $this->postJson('/api/customer/reviews', $payload)->assertStatus(422);
        $this->assertDatabaseHas('reviews', [
            'customer_id' => $customer->id,
            'booking_id' => $pending->id,
            'venue_id' => $venue->id,
            'rating' => 5,
        ]);
    }


    public function test_owner_venue_persists_event_types_when_empty_ids_and_names_are_sent(): void
    {
        $owner = $this->user('owner', false, 'venue-owner@example.test');
        $type = EventType::create([
            'name_ar' => 'زفاف',
            'name_en' => 'Wedding',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/owner/venues', [
            'name_ar' => 'صالة الصور',
            'name_en' => 'Gallery Hall',
            'city' => 'دمشق',
            'address' => 'المزة',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'capacity' => 250,
            'price_syp' => 2500000,
            'currency_base' => 'SYP',
            'event_type_ids' => [],
            'event_types' => ['Wedding'],
            'vendor_categories' => ['📸 تصوير'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.event_types.0.id', $type->id)
            ->assertJsonPath('data.vendor_categories.0', '📸 تصوير');
        $venueId = $response->json('data.id');
        $this->assertDatabaseHas('venue_event_types', [
            'venue_id' => $venueId,
            'event_type_id' => $type->id,
        ]);
    }

    public function test_owner_can_upload_multiple_venue_images(): void
    {
        Storage::fake('public');
        $owner = $this->user('owner', false, 'images-owner@example.test');
        $type = EventType::create(['name_ar' => 'زفاف', 'name_en' => 'Wedding', 'is_active' => true]);
        $venue = $this->venue($owner, $type);
        $venue->update(['status' => 'pending']);
        Sanctum::actingAs($owner);

        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nkwAAAAASUVORK5CYII=');
        $response = $this->post('/api/owner/venues/'.$venue->id.'/images', [
            'images' => [
                UploadedFile::fake()->createWithContent('hall-1.png', $pixel),
                UploadedFile::fake()->createWithContent('hall-2.png', $pixel),
            ],
            'is_main' => true,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(2, 'data.images');
        $this->assertDatabaseCount('venue_images', 2);
        $this->assertDatabaseHas('venue_images', ['venue_id' => $venue->id, 'is_main' => true]);
    }

    public function test_provider_service_requires_a_provider_category_and_event_types(): void
    {
        $provider = $this->user('provider', false, 'provider-category@example.test');
        $eventType = EventType::create(['name_ar' => 'تخرج', 'name_en' => 'Graduation', 'is_active' => true]);
        $hallCategory = ServiceCategory::create([
            'name_ar' => 'خدمات الصالة',
            'name_en' => 'Hall services test',
            'applies_to' => 'hall',
            'is_active' => true,
        ]);
        $providerCategory = ServiceCategory::create([
            'name_ar' => 'التصوير',
            'name_en' => 'Photography test',
            'applies_to' => 'provider',
            'is_active' => true,
        ]);
        Sanctum::actingAs($provider);

        $payload = [
            'name_ar' => 'تصوير تخرج',
            'name_en' => 'Graduation photography',
            'description_ar' => 'تغطية الحفل كاملة.',
            'price_syp' => 900000,
            'pricing_unit' => 'per_event',
            'event_type_ids' => [$eventType->id],
        ];

        $this->postJson('/api/provider/services', [...$payload, 'category_id' => $hallCategory->id])
            ->assertStatus(422);

        $response = $this->postJson('/api/provider/services', [...$payload, 'category_id' => $providerCategory->id]);
        $response->assertCreated()
            ->assertJsonPath('data.category_id', $providerCategory->id)
            ->assertJsonPath('data.available_for.0', 'Graduation')
            ->assertJsonPath('data.approval_status', 'pending');

        $this->assertDatabaseHas('services', [
            'provider_id' => $provider->id,
            'category_id' => $providerCategory->id,
            'type' => 'external_vendor',
            'pricing_unit' => 'per_event',
            'approval_status' => 'pending',
        ]);
    }

    public function test_provider_service_can_be_linked_only_to_an_active_booking(): void
    {
        $owner = $this->user('owner', false, 'service-owner@example.test');
        $provider = $this->user('provider', false, 'service-provider@example.test');
        $customer = $this->user('customer', false, 'service-customer@example.test');
        $type = EventType::create(['name_ar' => 'زفاف', 'name_en' => 'Wedding', 'is_active' => true]);
        $category = ServiceCategory::create([
            'name_ar' => 'التصوير',
            'name_en' => 'Photography test',
            'applies_to' => 'provider',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $type);
        $booking = $this->booking($customer, $owner, $venue, $type);
        $service = Service::create([
            'name_ar' => 'تصوير زفاف',
            'name_en' => 'Wedding photography',
            'description_ar' => 'تغطية فوتوغرافية.',
            'type' => 'external_vendor',
            'category' => 'Photography',
            'category_id' => $category->id,
            'price_syp' => 1000000,
            'price_usd' => 0,
            'pricing_unit' => 'per_event',
            'provider_id' => $provider->id,
            'is_active' => true,
            'approval_status' => 'approved',
            'available_for' => ['Wedding'],
        ]);
        Sanctum::actingAs($customer);

        $endpoint = '/api/customer/bookings/'.$booking->id.'/provider-services';
        $payload = ['provider_service_ids' => [$service->id], 'notes' => 'تغطية كاملة'];
        $this->postJson($endpoint, $payload)->assertStatus(422);

        $originalTotal = $booking->total_syp;
        $booking->update(['booking_status' => SaloraStatus::BOOKING_CONFIRMED]);
        $this->postJson($endpoint, $payload)
            ->assertOk()
            ->assertJsonPath('data.provider_requests.0.service_id', $service->id);

        $this->assertDatabaseHas('provider_service_requests', [
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'provider_id' => $provider->id,
            'status' => 'pending',
        ]);
        $this->assertSame((string) $originalTotal, (string) $booking->fresh()->total_syp);
    }

    public function test_venue_provider_category_policy_is_enforced_for_active_bookings(): void
    {
        $owner = $this->user('owner', false, 'category-owner@example.test');
        $provider = $this->user('provider', false, 'category-provider@example.test');
        $customer = $this->user('customer', false, 'category-customer@example.test');
        $type = EventType::create(['name_ar' => 'زفاف', 'name_en' => 'Wedding', 'is_active' => true]);
        $category = ServiceCategory::create([
            'name_ar' => 'التجهيزات',
            'name_en' => 'Equipment test',
            'applies_to' => 'provider',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $type);
        $venue->update(['vendor_categories' => ['📸 تصوير']]);
        $booking = $this->booking($customer, $owner, $venue, $type, [
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
        ]);
        $service = Service::create([
            'name_ar' => 'إضاءة احترافية',
            'name_en' => 'Professional lighting',
            'description_ar' => 'تجهيز إضاءة وصوت متكامل للمناسبة.',
            'type' => 'external_vendor',
            'category' => 'Equipment',
            'category_id' => $category->id,
            'price_syp' => 750000,
            'price_usd' => 0,
            'pricing_unit' => 'per_event',
            'provider_id' => $provider->id,
            'is_active' => true,
            'approval_status' => 'approved',
            'available_for' => ['Wedding'],
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings/'.$booking->id.'/provider-services', [
            'provider_service_ids' => [$service->id],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The booked venue does not allow the provider category for Professional lighting.');
    }


    public function test_hall_and_provider_invoices_can_coexist_and_provider_accept_is_idempotent(): void
    {
        $owner = $this->user('owner', false, 'invoice-owner@example.test');
        $provider = $this->user('provider', false, 'invoice-provider@example.test');
        $customer = $this->user('customer', false, 'invoice-customer@example.test');
        $type = EventType::create([
            'name_ar' => 'زفاف',
            'name_en' => 'Wedding',
            'is_active' => true,
        ]);
        $category = ServiceCategory::create([
            'name_ar' => 'التصوير',
            'name_en' => 'Payment photography test',
            'applies_to' => 'provider',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $type);
        $booking = $this->booking($customer, $owner, $venue, $type, [
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
        ]);
        $service = Service::create([
            'name_ar' => 'تصوير متكامل',
            'name_en' => 'Full photography',
            'description_ar' => 'تغطية كاملة للمناسبة.',
            'type' => 'external_vendor',
            'category' => 'Photography',
            'category_id' => $category->id,
            'price_syp' => 850000,
            'price_usd' => 0,
            'pricing_unit' => 'per_event',
            'provider_id' => $provider->id,
            'is_active' => true,
            'approval_status' => 'approved',
            'available_for' => ['Wedding'],
        ]);

        $hallInvoice = app(InvoiceService::class)->createForBooking($booking);
        $request = ProviderServiceRequest::create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'service_name' => $service->name_ar,
            'service_category' => 'Photography',
            'price_syp' => 850000,
            'price_usd' => 0,
            'payment_type' => 'manual_transfer',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/requests/'.$request->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.invoice.source_type', 'provider_service');

        $providerInvoice = Invoice::query()
            ->where('source_type', 'provider_service')
            ->where('source_id', $request->id)
            ->firstOrFail();

        $this->assertNotSame($hallInvoice->id, $providerInvoice->id);
        $this->assertSame(2, Invoice::where('booking_id', $booking->id)->count());

        $this->postJson('/api/provider/requests/'.$request->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.invoice.id', $providerInvoice->id);

        $this->assertSame(2, Invoice::where('booking_id', $booking->id)->count());
        $this->assertSame(
            1,
            Notification::where('user_id', $customer->id)
                ->where('type', 'provider_service_accepted')
                ->count(),
        );
    }

    private function user(string $role, bool $mustChangePassword, string $email): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $email,
            'phone' => '09'.str_pad((string) User::count(), 8, '0', STR_PAD_LEFT),
            'password' => 'Strong@123',
            'role' => $role,
            'status' => 'active',
            'must_change_password' => $mustChangePassword,
        ]);
    }

    private function venue(User $owner, EventType $type): Venue
    {
        $venue = Venue::create([
            'owner_id' => $owner->id,
            'name_ar' => 'صالة الاختبار',
            'name_en' => 'Test Hall',
            'city' => 'دمشق',
            'address' => 'شارع الجامعة',
            'capacity' => 500,
            'price_syp' => 3000000,
            'price_usd' => 0,
            'currency_base' => 'SYP',
            'status' => 'approved',
        ]);
        $venue->eventTypes()->attach($type->id);
        return $venue;
    }

    private function booking(User $customer, User $owner, Venue $venue, EventType $type, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_number' => 'TEST-'.strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $customer->id,
            'venue_id' => $venue->id,
            'owner_id' => $owner->id,
            'event_type_id' => $type->id,
            'event_name' => 'مناسبة اختبارية',
            'event_date' => now()->addDays(20)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'guests_count' => 100,
            'booking_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            'payment_status' => SaloraStatus::PAYMENT_UNPAID,
            'subtotal_syp' => 3000000,
            'total_syp' => 3000000,
            'currency' => 'SYP',
        ], $overrides));
    }
}
