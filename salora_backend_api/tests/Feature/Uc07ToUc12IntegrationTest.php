<?php

namespace Tests\Feature;

use App\Mail\BusinessAccountCreatedMail;
use App\Mail\OtpCodeMail;
use App\Models\Booking;
use App\Models\EmailOtp;
use App\Models\EventType;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\VenueRevision;
use App\Support\SaloraStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Uc07ToUc12IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_join_request_creates_a_separate_verified_business_account_after_admin_approval(): void
    {
        Mail::fake();
        $customer = $this->user('customer', 'customer-join@example.test');
        $admin = $this->user('admin', 'admin-join@example.test');
        $category = ServiceCategory::create([
            'name_ar' => 'التصوير',
            'name_en' => 'Join Photography',
            'applies_to' => 'provider',
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/customer/join-requests/request-otp', [
            'email' => 'business-provider@example.test',
        ])->assertOk()->assertJsonPath('data.mail_sent', true);

        $otp = null;
        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$otp): bool {
            if (!$mail->hasTo('business-provider@example.test') || $mail->purpose !== EmailOtp::PURPOSE_JOIN_REQUEST) {
                return false;
            }
            $otp = $mail->code;
            return true;
        });
        $this->assertNotNull($otp);

        $join = $this->postJson('/api/customer/join-requests', [
            'request_type' => 'provider',
            'full_name' => 'مقدم خدمة مستقل',
            'email' => 'business-provider@example.test',
            'otp' => $otp,
            'phone' => '0999555444',
            'city' => 'دمشق',
            'service_category_id' => $category->id,
            'service_description' => 'تصوير المناسبات داخل دمشق.',
        ])->assertCreated()
            ->assertJsonPath('data.applicant_user_id', $customer->id)
            ->assertJsonPath('data.status', 'pending');

        $requestId = (int) $join->json('data.id');
        $this->assertSame('customer', $customer->fresh()->role);
        $this->assertDatabaseHas('owner_requests', [
            'id' => $requestId,
            'applicant_user_id' => $customer->id,
            'email' => 'business-provider@example.test',
            'request_type' => 'provider',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);
        $approval = $this->postJson('/api/admin/owner-requests/'.$requestId.'/approve', [
            'temporary_password' => 'Temporary@789',
            'temporary_password_confirmation' => 'Temporary@789',
        ])->assertOk()
            ->assertJsonPath('data.account.role', 'provider')
            ->assertJsonPath('data.account.must_change_password', true)
            ->assertJsonPath('data.request.status', 'approved');

        $businessUserId = (int) $approval->json('data.account.id');
        $this->assertNotSame($customer->id, $businessUserId);
        $this->assertSame('customer', $customer->fresh()->role);
        $this->assertDatabaseHas('users', [
            'id' => $businessUserId,
            'email' => 'business-provider@example.test',
            'role' => 'provider',
            'status' => 'active',
            'must_change_password' => true,
        ]);
        $this->assertNotNull(User::findOrFail($businessUserId)->email_verified_at);
        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $businessUserId,
            'city' => 'دمشق',
            'contact_phone' => '0999555444',
        ]);
        Mail::assertSent(BusinessAccountCreatedMail::class, fn (BusinessAccountCreatedMail $mail) => $mail->hasTo('business-provider@example.test'));
    }

    public function test_join_request_rejects_the_customer_email_and_any_existing_account_email(): void
    {
        $customer = $this->user('customer', 'same-customer@example.test');
        $this->user('owner', 'existing-business@example.test');
        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/join-requests/request-otp', [
            'email' => $customer->email,
        ])->assertStatus(422)->assertJsonPath('errors.code', 'separate_business_email_required');

        $this->postJson('/api/customer/join-requests/request-otp', [
            'email' => 'existing-business@example.test',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'business_email_already_used');
    }

    public function test_provider_service_supports_one_to_six_images_and_appears_in_the_authenticated_directory(): void
    {
        Mail::fake();
        Storage::fake('public');
        $provider = $this->user('provider', 'gallery-provider@example.test');
        ProviderProfile::create([
            'user_id' => $provider->id,
            'city' => 'دمشق',
            'bio' => 'مصور مناسبات محترف.',
            'contact_phone' => '0999444333',
            'whatsapp_phone' => '0999444333',
            'allow_phone' => true,
            'allow_whatsapp' => true,
        ]);
        $admin = $this->user('admin', 'gallery-admin@example.test');
        $customer = $this->user('customer', 'gallery-customer@example.test');
        $category = ServiceCategory::create([
            'name_ar' => 'تصوير أعراس',
            'name_en' => 'Wedding Gallery Photography',
            'applies_to' => 'provider',
            'is_active' => true,
        ]);
        $eventType = EventType::create([
            'name_ar' => 'زفاف',
            'name_en' => 'Gallery Wedding',
            'is_active' => true,
        ]);

        Sanctum::actingAs($provider);
        $created = $this->postJson('/api/provider/services', [
            'name_ar' => 'تصوير زفاف كامل',
            'name_en' => 'Complete wedding photography',
            'description_ar' => 'تغطية فوتوغرافية كاملة للحفل بجودة عالية.',
            'category_id' => $category->id,
            'price_syp' => 1200000,
            'event_type_ids' => [$eventType->id],
        ])->assertCreated()->assertJsonPath('data.approval_status', 'pending');
        $serviceId = (int) $created->json('data.id');

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/services/'.$serviceId.'/approve')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'service_image_required');

        Sanctum::actingAs($provider);
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nkwAAAAASUVORK5CYII=');
        $images = [];
        for ($index = 1; $index <= 6; $index++) {
            $images[] = UploadedFile::fake()->createWithContent("work-$index.png", $pixel);
        }
        $this->post('/api/provider/services/'.$serviceId.'/images', [
            'images' => $images,
            'make_first_main' => true,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(6, 'data.service.images')
            ->assertJsonPath('data.remaining_slots', 0);

        $this->post('/api/provider/services/'.$serviceId.'/images', [
            'images' => [UploadedFile::fake()->createWithContent('seventh.png', $pixel)],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'service_images_limit');

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/services/'.$serviceId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.is_active', true);

        Sanctum::actingAs($customer);
        $directory = $this->getJson('/api/providers?category_id='.$category->id.'&per_page=100')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $provider->id)
            ->assertJsonPath('data.data.0.contact_phone', '0999444333')
            ->assertJsonPath('data.data.0.whatsapp_phone', '0999444333')
            ->assertJsonCount(6, 'data.data.0.services.0.images');

        $this->assertSame($provider->id, (int) $directory->json('data.data.0.id'));
        $this->assertDatabaseCount('service_images', 6);
    }

    public function test_admin_can_build_hierarchical_categories_and_linked_categories_are_disabled_instead_of_deleted(): void
    {
        $admin = $this->user('admin', 'category-admin@example.test');
        Sanctum::actingAs($admin);

        $parent = $this->postJson('/api/admin/service-categories', [
            'name_ar' => 'التصوير',
            'name_en' => 'Hierarchical Photography',
            'applies_to' => 'provider',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertCreated();
        $parentId = (int) $parent->json('data.id');

        $child = $this->postJson('/api/admin/service-categories', [
            'parent_id' => $parentId,
            'name_ar' => 'تصوير فيديو',
            'name_en' => 'Hierarchical Video',
            'applies_to' => 'provider',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertCreated();
        $childId = (int) $child->json('data.id');

        $this->deleteJson('/api/admin/service-categories/'.$parentId)
            ->assertOk()
            ->assertJsonPath('data.children_count', 1);
        $this->assertDatabaseHas('service_categories', ['id' => $parentId, 'is_active' => false]);

        $provider = $this->user('provider', 'category-provider@example.test');
        Service::create([
            'name_ar' => 'خدمة فيديو',
            'name_en' => 'Linked video service',
            'description_ar' => 'خدمة تصوير فيديو مرتبطة بالتصنيف.',
            'type' => 'external_vendor',
            'category' => 'Hierarchical Video',
            'category_id' => $childId,
            'price_syp' => 500000,
            'pricing_unit' => 'per_event',
            'provider_id' => $provider->id,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->deleteJson('/api/admin/service-categories/'.$childId)
            ->assertOk()
            ->assertJsonPath('data.services_count', 1);
        $this->assertDatabaseHas('service_categories', ['id' => $childId, 'is_active' => false]);
    }

    public function test_approved_venue_edits_are_versioned_and_only_published_after_admin_approval(): void
    {
        $owner = $this->user('owner', 'revision-owner@example.test');
        $admin = $this->user('admin', 'revision-admin@example.test');
        $eventType = EventType::create([
            'name_ar' => 'مؤتمر',
            'name_en' => 'Revision Conference',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $eventType, [
            'name_ar' => 'النسخة المنشورة',
            'name_en' => 'Published version',
            'price_syp' => 1000000,
        ]);
        VenueImage::create([
            'venue_id' => $venue->id,
            'image_url' => 'https://example.test/old.jpg',
            'is_main' => true,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->putJson('/api/owner/venues/'.$venue->id, [
            'name_ar' => 'النسخة الجديدة',
            'price_syp' => 1500000,
            'opening_hours' => [
                'saturday' => ['enabled' => true, 'open' => '09:00', 'close' => '23:00'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.venue.name_ar', 'النسخة المنشورة')
            ->assertJsonPath('data.revision.status', 'pending');

        $revisionId = (int) $response->json('data.revision.id');
        $venue->refresh();
        $this->assertSame('النسخة المنشورة', $venue->name_ar);
        $this->assertSame('1000000.00', $venue->price_syp);
        $this->assertDatabaseHas('venue_revisions', ['id' => $revisionId, 'status' => 'pending']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/venue-revisions?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.current_snapshot.name_ar', 'النسخة المنشورة')
            ->assertJsonPath('data.0.proposed_snapshot.name_ar', 'النسخة الجديدة');

        $this->postJson('/api/admin/venue-revisions/'.$revisionId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.proposed_snapshot.name_ar', 'النسخة الجديدة');

        $venue->refresh();
        $this->assertSame('النسخة الجديدة', $venue->name_ar);
        $this->assertSame('1500000.00', $venue->price_syp);
        $this->assertTrue((bool) data_get($venue->opening_hours, 'saturday.enabled'));
    }

    public function test_availability_exposes_blocked_intervals_expires_stale_holds_and_rejects_overlap(): void
    {
        $owner = $this->user('owner', 'availability-owner@example.test');
        $firstCustomer = $this->user('customer', 'availability-first@example.test');
        $secondCustomer = $this->user('customer', 'availability-second@example.test');
        $eventType = EventType::create([
            'name_ar' => 'زفاف',
            'name_en' => 'Availability Wedding',
            'is_active' => true,
        ]);
        $venue = $this->venue($owner, $eventType, [
            'opening_hours' => [
                strtolower(now()->addDays(30)->format('l')) => [
                    'enabled' => true,
                    'open' => '09:00',
                    'close' => '23:30',
                ],
            ],
        ]);
        $date = now()->addDays(30)->toDateString();

        $blocking = $this->booking($firstCustomer, $owner, $venue, $eventType, [
            'event_date' => $date,
            'start_time' => '18:00',
            'end_time' => '22:00',
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
        ]);
        $stale = $this->booking($firstCustomer, $owner, $venue, $eventType, [
            'event_date' => $date,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'booking_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($secondCustomer);
        $this->getJson('/api/venues/'.$venue->id.'/availability?date='.$date)
            ->assertOk()
            ->assertJsonPath('data.hold_hours', 6)
            ->assertJsonCount(1, 'data.unavailable_intervals')
            ->assertJsonPath('data.unavailable_intervals.0.booking_id', $blocking->id);
        $this->assertSame(SaloraStatus::BOOKING_EXPIRED, $stale->fresh()->booking_status);

        $this->postJson('/api/customer/bookings', [
            'venue_id' => $venue->id,
            'event_type_id' => $eventType->id,
            'event_name' => 'طلب متداخل',
            'event_date' => $date,
            'start_time' => '20:00',
            'end_time' => '23:00',
            'guests_count' => 100,
            'currency' => 'SYP',
        ])->assertStatus(409)
            ->assertJsonPath('errors.code', 'venue_time_conflict')
            ->assertJsonCount(1, 'errors.unavailable_intervals');

        $this->postJson('/api/customer/bookings', [
            'venue_id' => $venue->id,
            'event_type_id' => $eventType->id,
            'event_name' => 'خارج وقت العمل',
            'event_date' => $date,
            'start_time' => '07:00',
            'end_time' => '08:00',
            'guests_count' => 100,
            'currency' => 'SYP',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'outside_opening_hours');
    }

    public function test_browsing_endpoints_require_login_because_guest_mode_is_disabled(): void
    {
        $this->getJson('/api/venues')->assertUnauthorized();
        $this->getJson('/api/providers')->assertUnauthorized();
        $this->getJson('/api/service-categories')->assertUnauthorized();
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role).' UC User',
            'email' => $email,
            'phone' => '09'.str_pad((string) (User::withTrashed()->count() + 1), 8, '0', STR_PAD_LEFT),
            'password' => 'Strong@123',
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    private function venue(User $owner, EventType $eventType, array $overrides = []): Venue
    {
        $venue = Venue::create(array_merge([
            'owner_id' => $owner->id,
            'name_ar' => 'صالة تكامل',
            'name_en' => 'Integration Hall',
            'description_ar' => 'صالة لاختبارات التكامل.',
            'description_en' => 'Integration test hall.',
            'city' => 'دمشق',
            'address' => 'المزة',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'capacity' => 500,
            'price_syp' => 1000000,
            'price_usd' => 0,
            'currency_base' => 'SYP',
            'status' => 'approved',
        ], $overrides));
        $venue->eventTypes()->attach($eventType->id);
        return $venue;
    }

    private function booking(User $customer, User $owner, Venue $venue, EventType $eventType, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_number' => 'UC0712-'.strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $customer->id,
            'venue_id' => $venue->id,
            'owner_id' => $owner->id,
            'event_type_id' => $eventType->id,
            'event_name' => 'مناسبة اختبار',
            'event_date' => now()->addDays(30)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'guests_count' => 100,
            'booking_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            'payment_status' => SaloraStatus::PAYMENT_UNPAID,
            'subtotal_syp' => 1000000,
            'total_syp' => 1000000,
            'currency' => 'SYP',
            'expires_at' => now()->addHours(6),
        ], $overrides));
    }
}
