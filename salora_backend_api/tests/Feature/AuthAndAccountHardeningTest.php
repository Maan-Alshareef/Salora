<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\Booking;
use App\Models\EventType;
use App\Models\User;
use App\Models\Venue;
use App\Support\SaloraStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndAccountHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_otp_and_resets_password(): void
    {
        Mail::fake();
        $user = $this->user('customer', 'reset@example.test');

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('data.expires_in_seconds', 600);

        $code = null;
        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use ($user, &$code): bool {
            $code = $mail->code;
            return $mail->hasTo($user->email);
        });

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'otp' => $code,
            'password' => 'Changed@456',
            'password_confirmation' => 'Changed@456',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Changed@456',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_account_is_locked_for_ten_minutes_after_five_failed_logins(): void
    {
        $user = $this->user('customer', 'locked@example.test');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'Wrong@123',
            ])->assertStatus(422)
                ->assertJsonPath('errors.code', 'invalid_credentials')
                ->assertJsonMissingPath('errors.remaining_attempts');
        }

        // The fifth failed attempt performs the lock immediately.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Wrong@123',
        ])->assertStatus(429)
            ->assertJsonPath('errors.code', 'account_locked')
            ->assertJsonPath('errors.retry_after_seconds', 600);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Strong@123',
        ])->assertStatus(429)
            ->assertJsonPath('errors.code', 'account_locked');

        $this->travel(11)->minutes();
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Strong@123',
        ])->assertOk();
    }

    public function test_required_password_change_state_survives_session_refresh(): void
    {
        $owner = $this->user('owner', 'owner-refresh@example.test', mustChange: true);

        $login = $this->postJson('/api/auth/login', [
            'email' => $owner->email,
            'password' => 'Strong@123',
        ])->assertOk()->assertJsonPath('data.user.must_change_password', true);

        $token = $login->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);

        $this->getJson('/api/owner/venues', $headers)
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'must_change_password');

        $this->getJson('/api/notifications', $headers)
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'must_change_password');

        $this->putJson('/api/auth/profile', ['name' => 'Blocked Until Password Change'], $headers)
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'must_change_password');

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Strong@123',
            'password' => 'OwnerNew@456',
            'password_confirmation' => 'OwnerNew@456',
        ], $headers)->assertOk()->assertJsonPath('data.must_change_password', false);

        $this->getJson('/api/owner/venues', $headers)->assertOk();
    }

    public function test_profile_avatar_is_uploaded_and_replaced_server_side(): void
    {
        Storage::fake('public');
        $user = $this->user('customer', 'avatar@example.test');
        Sanctum::actingAs($user);

        // Use a valid PNG fixture without requiring the optional PHP GD extension.
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nkwAAAAASUVORK5CYII=');

        $first = $this->post('/api/auth/profile/avatar', [
            'image' => UploadedFile::fake()->createWithContent('first.png', $pixel),
        ], ['Accept' => 'application/json'])->assertOk();

        $firstPath = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($firstPath);
        $first->assertJsonPath('data.avatar_url', '/storage/'.$firstPath);

        $this->post('/api/auth/profile/avatar', [
            'image' => UploadedFile::fake()->createWithContent('second.png', $pixel),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($user->fresh()->avatar);
    }

    public function test_admin_can_suspend_activate_deactivate_and_safely_delete_accounts(): void
    {
        $admin = $this->user('admin', 'admin@example.test');
        $customer = $this->user('customer', 'customer-actions@example.test');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users/'.$customer->id.'/suspend', [
            'suspended_until' => now()->addDays(3)->toIso8601String(),
            'reason' => 'اختبار التجميد المؤقت',
        ])->assertOk()->assertJsonPath('data.status', 'suspended');

        $this->postJson('/api/admin/users/'.$customer->id.'/activate', [])
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->postJson('/api/admin/users/'.$customer->id.'/deactivate', ['reason' => 'تعطيل إداري'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->postJson('/api/admin/users/'.$customer->id.'/activate', [])->assertOk();
        $this->getJson('/api/admin/users/'.$customer->id.'/deletion-impact')
            ->assertOk()->assertJsonPath('data.can_delete', true);

        $this->deleteJson('/api/admin/users/'.$customer->id)
            ->assertOk()->assertJsonPath('data.id', $customer->id);
        $this->assertSoftDeleted('users', ['id' => $customer->id]);

        $this->postJson('/api/admin/users/'.$customer->id.'/restore', [])
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_admin_delete_is_blocked_when_customer_has_active_booking(): void
    {
        $admin = $this->user('admin', 'admin-block@example.test');
        $owner = $this->user('owner', 'owner-block@example.test');
        $customer = $this->user('customer', 'customer-block@example.test');
        $type = EventType::create(['name_en' => 'Wedding', 'name_ar' => 'زفاف', 'is_active' => true]);
        $venue = Venue::create([
            'owner_id' => $owner->id,
            'name_ar' => 'صالة',
            'name_en' => 'Hall',
            'city' => 'دمشق',
            'address' => 'المزة',
            'capacity' => 100,
            'price_syp' => 100000,
            'status' => 'approved',
        ]);
        Booking::create([
            'booking_number' => 'BLOCK-001',
            'customer_id' => $customer->id,
            'owner_id' => $owner->id,
            'venue_id' => $venue->id,
            'event_type_id' => $type->id,
            'event_name' => 'حجز فعال',
            'event_date' => now()->addWeek()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'guests_count' => 50,
            'booking_status' => SaloraStatus::BOOKING_CONFIRMED,
            'payment_status' => SaloraStatus::PAYMENT_APPROVED,
        ]);

        Sanctum::actingAs($admin);
        $this->deleteJson('/api/admin/users/'.$customer->id)
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'deletion_blocked')
            ->assertJsonPath('errors.impact.can_delete', false);
    }

    public function test_expired_temporary_suspension_is_reactivated_automatically(): void
    {
        $admin = $this->user('admin', 'admin-expired@example.test');
        $customer = $this->user('customer', 'expired-suspension@example.test');
        $customer->forceFill([
            'status' => 'suspended',
            'suspended_until' => now()->subMinute(),
            'suspension_reason' => 'انتهت مدة الاختبار',
            'suspended_by' => $admin->id,
        ])->save();

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/users?per_page=100')->assertOk();

        $customer->refresh();
        $this->assertSame('active', $customer->status);
        $this->assertNull($customer->suspended_until);
    }

    public function test_admin_reset_of_business_password_requires_another_first_login_change(): void
    {
        $admin = $this->user('admin', 'admin-reset-business@example.test');
        $owner = $this->user('owner', 'owner-reset-business@example.test');
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/users/'.$owner->id, [
            'password' => 'Temporary@987',
            'password_confirmation' => 'Temporary@987',
        ])->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->assertTrue($owner->fresh()->must_change_password);
    }

    public function test_admin_created_business_account_is_verified_and_requires_password_change(): void
    {
        $admin = $this->user('admin', 'admin-create@example.test');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New Hall Owner',
            'email' => 'new-owner@example.test',
            'phone' => '0999111222',
            'role' => 'owner',
            'password' => 'TempPass@789',
            'password_confirmation' => 'TempPass@789',
        ])->assertCreated()
            ->assertJsonPath('data.must_change_password', true);

        $created = User::findOrFail($response->json('data.id'));
        $this->assertNotNull($created->email_verified_at);
    }

    private function user(string $role, string $email, bool $mustChange = false): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $email,
            'phone' => '09'.str_pad((string) (User::withTrashed()->count() + 1), 8, '0', STR_PAD_LEFT),
            'password' => 'Strong@123',
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => $mustChange,
        ]);
    }
}
