<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\SaloraStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends BaseApiController
{
    private const ACTIVE_BOOKING_STATUSES = [
        SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
        SaloraStatus::BOOKING_PENDING_PAYMENT,
        SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
        SaloraStatus::BOOKING_CONFIRMED,
        SaloraStatus::BOOKING_MODIFICATION_REQUESTED,
        SaloraStatus::BOOKING_CANCELLATION_REQUESTED,
    ];

    public function index(Request $request)
    {
        $this->reactivateExpiredSuspensions();

        $includeDeleted = $request->boolean('include_deleted') || $request->query('status') === 'deleted';
        $query = $includeDeleted ? User::withTrashed() : User::query();
        $query->latest('id');

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            if ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif ($status === 'locked') {
                $query->where('locked_until', '>', now());
            } else {
                $query->whereNull('deleted_at')->where('status', $status);
            }
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        return $this->ok($query->paginate(min(max((int) $request->query('per_page', 50), 1), 100)));
    }

    public function show(User $user)
    {
        $user->reactivateIfSuspensionExpired();
        $user->refresh();

        return $this->ok($user->loadCount([
            'bookings',
            'ownedBookings',
            'ownedVenues',
            'services',
            'providerServiceRequests',
            'events',
            'reviews',
            'complaints',
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => ['required', 'regex:/^[0-9]{10}$/', 'unique:users,phone'],
            'role' => 'required|in:admin,owner,customer,provider',
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $isBusiness = in_array($data['role'], ['owner', 'provider'], true);
        $temporaryPassword = trim((string) ($data['password'] ?? ''));
        if ($temporaryPassword === '') {
            if (!$isBusiness) {
                return $this->fail('كلمة المرور مطلوبة لحساب العميل أو الأدمن.', 422);
            }
            $temporaryPassword = Str::password(14, true, true, true, false);
        }

        $user = User::create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => trim($data['phone']),
            'role' => $data['role'],
            'password' => $temporaryPassword,
            'status' => 'active',
            'business_status' => $isBusiness ? 'incomplete' : 'approved',
            // The administrator verifies the business identity while creating the account.
            'email_verified_at' => now(),
            'must_change_password' => $isBusiness,
        ]);

        ActivityLogger::log(
            'created_user',
            'user',
            $user->id,
            'Administrator created a '.$user->role.' account. The temporary password was not stored in plaintext.'
        );

        $createdUser = $user->fresh();

        return $this->ok(array_merge($createdUser->toArray(), [
            // Keep the nested object for backward compatibility with older clients.
            'user' => $createdUser,
            'temporary_password' => $isBusiness ? $temporaryPassword : null,
        ]), $isBusiness ? 'تم إنشاء حساب العمل بكلمة مرور مؤقتة ويجب تغييرها عند أول دخول.' : 'تم إنشاء الحساب بنجاح.', 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'sometimes',
                'required',
                'regex:/^[0-9]{10}$/',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'role' => 'sometimes|required|in:admin,owner,customer,provider',
            'password' => ['sometimes', 'required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if (array_key_exists('role', $data) && $user->role === 'admin' && $data['role'] !== 'admin') {
            if ((int) $user->id === (int) $request->user()->id) {
                return $this->fail('لا يمكنك إزالة صلاحية مدير النظام من حسابك الحالي.', 422, ['code' => 'self_admin_role_change']);
            }
            if ($this->isLastAvailableAdmin($user)) {
                return $this->fail('لا يمكن تغيير دور آخر مدير نظام فعال.', 422, ['code' => 'last_admin']);
            }
        }

        if (array_key_exists('email', $data)) {
            $newEmail = mb_strtolower(trim($data['email']));
            if ($newEmail !== $user->email) {
                $data['email'] = $newEmail;
                $data['email_verified_at'] = now();
            }
        }
        if (array_key_exists('name', $data)) $data['name'] = trim($data['name']);
        if (array_key_exists('phone', $data)) $data['phone'] = trim($data['phone']);

        $targetRole = $data['role'] ?? $user->role;
        if (array_key_exists('password', $data) && in_array($targetRole, ['owner', 'provider'], true)) {
            // An administrator-set password is temporary for business accounts.
            $data['must_change_password'] = true;
        }

        $user->update($data);
        ActivityLogger::log(
            'updated_user',
            'user',
            $user->id,
            'Administrator updated user fields: '.implode(', ', array_keys($data))
        );

        return $this->ok($user->fresh(), 'تم تحديث الحساب بنجاح.');
    }

    public function deletionImpact(Request $request, User $user)
    {
        return $this->ok($this->buildDeletionImpact($user, $request->user()));
    }

    public function suspend(Request $request, User $user)
    {
        if ($error = $this->guardAdministrativeStateChange($request->user(), $user, 'تجميد')) {
            return $error;
        }

        $data = $request->validate([
            'suspended_until' => 'required|date|after:now',
            'reason' => 'required|string|max:1000',
        ]);

        $user->forceFill([
            'status' => 'suspended',
            'suspended_until' => $data['suspended_until'],
            'suspension_reason' => trim($data['reason']),
            'suspended_by' => $request->user()->id,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ])->save();
        $user->tokens()->delete();

        ActivityLogger::log(
            'suspended_user',
            'user',
            $user->id,
            'Account suspended until '.$user->suspended_until?->toIso8601String().'. Reason: '.$user->suspension_reason
        );

        return $this->ok($user->fresh(), 'تم تجميد الحساب مؤقتاً.');
    }

    public function activate(Request $request, User $user)
    {
        if ($user->trashed()) {
            return $this->fail('الحساب محذوف. استخدم استعادة الحساب أولاً.', 422, ['code' => 'account_deleted']);
        }

        $user->forceFill([
            'status' => 'active',
            'suspended_until' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ])->save();

        ActivityLogger::log('activated_user', 'user', $user->id, 'Administrator activated the account and cleared security locks.');
        return $this->ok($user->fresh(), 'تم تنشيط الحساب وفك القفل.');
    }

    public function deactivate(Request $request, User $user)
    {
        if ($error = $this->guardAdministrativeStateChange($request->user(), $user, 'تعطيل')) {
            return $error;
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $user->forceFill([
            'status' => 'inactive',
            'suspended_until' => null,
            'suspension_reason' => $data['reason'] ?? null,
            'suspended_by' => $request->user()->id,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ])->save();
        $user->tokens()->delete();

        ActivityLogger::log('deactivated_user', 'user', $user->id, trim((string) ($data['reason'] ?? '')));
        return $this->ok($user->fresh(), 'تم تعطيل الحساب إلى أجل غير محدد.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($error = $this->guardAdministrativeStateChange($request->user(), $user, 'حذف')) {
            return $error;
        }

        $impact = $this->buildDeletionImpact($user, $request->user());
        if ($impact['can_delete'] !== true) {
            return $this->fail(
                'لا يمكن حذف الحساب قبل معالجة الارتباطات النشطة الموضحة.',
                422,
                ['code' => 'deletion_blocked', 'impact' => $impact]
            );
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->forceFill([
                'status' => 'inactive',
                'suspended_until' => null,
                'locked_until' => null,
                'failed_login_attempts' => 0,
            ])->save();
            $user->delete();
        });

        ActivityLogger::log('soft_deleted_user', 'user', $user->id, 'Administrator safely deleted the account after dependency checks.');
        return $this->ok(['id' => $user->id, 'deleted_at' => $user->deleted_at], 'تم حذف الحساب بأمان مع الاحتفاظ بالسجلات التاريخية.');
    }

    public function restore(Request $request, int $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        if (!$user->trashed()) {
            return $this->ok($user, 'الحساب غير محذوف.');
        }

        $user->restore();
        $user->forceFill([
            'status' => 'active',
            'suspended_until' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ])->save();

        ActivityLogger::log('restored_user', 'user', $user->id, 'Administrator restored a soft-deleted account.');
        return $this->ok($user->fresh(), 'تمت استعادة الحساب وتنشيطه.');
    }


    private function reactivateExpiredSuspensions(): void
    {
        User::query()
            ->where('status', 'suspended')
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->update([
                'status' => 'active',
                'suspended_until' => null,
                'suspension_reason' => null,
                'suspended_by' => null,
                'updated_at' => now(),
            ]);
    }

    private function guardAdministrativeStateChange(User $actor, User $target, string $action)
    {
        if ((int) $actor->id === (int) $target->id) {
            return $this->fail("لا يمكنك {$action} حساب مدير النظام الذي تستخدمه حالياً.", 422, ['code' => 'self_account_action']);
        }

        if ($target->role === 'admin' && $this->isLastAvailableAdmin($target)) {
            return $this->fail("لا يمكن {$action} آخر مدير نظام فعال.", 422, ['code' => 'last_admin']);
        }

        return null;
    }

    private function isLastAvailableAdmin(User $target): bool
    {
        if ($target->role !== 'admin' || $target->status !== 'active' || $target->trashed()) {
            return false;
        }

        return User::query()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->count() <= 1;
    }

    private function buildDeletionImpact(User $user, ?User $actor = null): array
    {
        $counts = [
            'future_customer_bookings' => $user->bookings()
                ->whereDate('event_date', '>=', today())
                ->whereIn('booking_status', self::ACTIVE_BOOKING_STATUSES)
                ->count(),
            'active_customer_bookings' => $user->bookings()
                ->whereIn('booking_status', self::ACTIVE_BOOKING_STATUSES)
                ->count(),
            'active_owner_bookings' => $user->ownedBookings()
                ->whereIn('booking_status', self::ACTIVE_BOOKING_STATUSES)
                ->count(),
            'owned_venues' => $user->ownedVenues()->count(),
            'provider_services' => $user->services()->count(),
            'active_provider_requests' => $user->providerServiceRequests()
                ->whereIn('status', ['pending', 'accepted'])
                ->count(),
            'events' => $user->events()->count(),
            'reviews' => $user->reviews()->count(),
            'complaints' => $user->complaints()->count(),
        ];

        $blockers = [];
        if ($actor && (int) $actor->id === (int) $user->id) $blockers[] = 'self_account';
        if ($user->role === 'admin' && $this->isLastAvailableAdmin($user)) $blockers[] = 'last_admin';
        if ($counts['active_customer_bookings'] > 0) $blockers[] = 'active_customer_bookings';
        if ($counts['active_owner_bookings'] > 0) $blockers[] = 'active_owner_bookings';
        if ($counts['owned_venues'] > 0) $blockers[] = 'owned_venues_require_transfer_or_disable';
        if ($counts['provider_services'] > 0) $blockers[] = 'provider_services_require_transfer_or_disable';
        if ($counts['active_provider_requests'] > 0) $blockers[] = 'active_provider_requests';

        return [
            'user_id' => $user->id,
            'can_delete' => $blockers === [],
            'blockers' => $blockers,
            'counts' => $counts,
            'strategy' => 'soft_delete',
            'historical_records_preserved' => true,
        ];
    }
}
