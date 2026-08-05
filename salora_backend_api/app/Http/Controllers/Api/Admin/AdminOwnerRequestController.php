<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Mail\BusinessAccountCreatedMail;
use App\Mail\JoinRequestRejectedMail;
use App\Models\OwnerRequest;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AdminOwnerRequestController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = OwnerRequest::with([
            'applicant:id,name,email,phone,avatar,status',
            'serviceCategory:id,parent_id,name_ar,name_en,image_url',
            'admin:id,name',
            'owner:id,name,email,phone,role,avatar,status,must_change_password',
        ])->latest();

        if ($request->filled('request_type')) {
            $query->where('request_type', $request->query('request_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $this->ok($query->get());
    }

    public function approve(Request $request, OwnerRequest $ownerRequest)
    {
        if ($ownerRequest->status !== 'pending') {
            return $this->fail('تمت مراجعة هذا الطلب مسبقاً.', 422);
        }

        $data = $request->validate([
            'temporary_password' => ['nullable', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $email = mb_strtolower(trim((string) $ownerRequest->email));
        if ($email === '') {
            return $this->fail('الطلب لا يحتوي على بريد إلكتروني.', 422);
        }
        if (!$ownerRequest->email_verified_at) {
            return $this->fail('لا يمكن إنشاء الحساب قبل توثيق بريد طلب الانضمام.', 422, [
                'code' => 'join_email_not_verified',
            ]);
        }
        if (User::withTrashed()->where('email', $email)->exists()) {
            return $this->fail('يوجد حساب آخر بهذا البريد. لن يتم تعديل الحساب الموجود.', 422);
        }

        $role = ($ownerRequest->request_type ?: 'owner') === 'provider' ? 'provider' : 'owner';
        $temporaryPassword = trim((string) ($data['temporary_password'] ?? ''));
        if ($temporaryPassword === '') {
            $temporaryPassword = Str::password(14, true, true, true, false);
        }

        $user = DB::transaction(function () use ($request, $ownerRequest, $temporaryPassword, $email, $role) {
            $lockedRequest = OwnerRequest::whereKey($ownerRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedRequest->status !== 'pending') {
                abort(409, 'Join request was reviewed by another administrator.');
            }

            $user = User::create([
                'name' => $lockedRequest->full_name,
                'email' => $email,
                'phone' => $lockedRequest->phone,
                'role' => $role,
                'password' => $temporaryPassword,
                'status' => 'active',
                'business_status' => 'incomplete',
                'email_verified_at' => $lockedRequest->email_verified_at ?: now(),
                'must_change_password' => true,
            ]);

            if ($role === 'provider') {
                ProviderProfile::create([
                    'user_id' => $user->id,
                    'city' => $lockedRequest->city,
                    'bio' => $lockedRequest->service_description,
                    'contact_phone' => $lockedRequest->phone,
                    'whatsapp_phone' => $lockedRequest->phone,
                    'allow_phone' => true,
                    'allow_whatsapp' => true,
                ]);
            }

            $lockedRequest->update([
                'status' => 'approved',
                'admin_id' => $request->user()->id,
                'created_owner_id' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $user;
        });

        $mailSent = true;
        try {
            Mail::to($email)->send(new BusinessAccountCreatedMail(
                $user->name,
                $email,
                $temporaryPassword,
                $role,
            ));
        } catch (\Throwable $exception) {
            $mailSent = false;
            Log::error('Business account credentials email failed.', [
                'owner_request_id' => $ownerRequest->id,
                'user_id' => $user->id,
                'email' => $email,
                'exception' => $exception->getMessage(),
            ]);
        }

        if ($ownerRequest->applicant_user_id) {
            NotificationService::send(
                $ownerRequest->applicant_user_id,
                'تم قبول طلب الانضمام',
                $role === 'provider'
                    ? 'تم إنشاء حساب مقدم الخدمة المستقل. تحقق من بريد العمل للحصول على بيانات الدخول.'
                    : 'تم إنشاء حساب مدير الصالة المستقل. تحقق من بريد العمل للحصول على بيانات الدخول.',
                'join_request_approved',
                ['request_id' => $ownerRequest->id, 'business_user_id' => $user->id, 'role' => $role]
            );
        }

        ActivityLogger::log(
            'created_account_from_join_request',
            'owner_request',
            $ownerRequest->id,
            'Admin created a separate '.$role.' account from a verified join request. Temporary password was not logged.'
        );

        return $this->ok([
            'request' => $ownerRequest->fresh([
                'applicant:id,name,email,phone',
                'serviceCategory:id,name_ar,name_en',
                'admin:id,name',
                'owner:id,name,email,phone,role,status,must_change_password',
            ]),
            'account' => $user->fresh('providerProfile'),
            'mail_sent' => $mailSent,
            // Returned once so the administrator can copy it if email delivery failed.
            'temporary_password' => $temporaryPassword,
        ], $mailSent
            ? 'تم إنشاء حساب العمل وإرسال بيانات الدخول إلى البريد. يجب تغيير كلمة المرور عند أول دخول.'
            : 'تم إنشاء الحساب، لكن تعذر إرسال البريد. انسخ كلمة المرور المؤقتة الظاهرة وشاركها بشكل آمن.'
        );
    }

    public function reject(Request $request, OwnerRequest $ownerRequest)
    {
        if ($ownerRequest->status !== 'pending') {
            return $this->fail('تمت مراجعة هذا الطلب مسبقاً.', 422);
        }

        $data = $request->validate(['reason' => 'required|string|max:500']);
        $ownerRequest->update([
            'status' => 'rejected',
            'admin_id' => $request->user()->id,
            'rejection_reason' => $data['reason'],
            'reviewed_at' => now(),
        ]);

        $mailSent = true;
        try {
            Mail::to($ownerRequest->email)->send(new JoinRequestRejectedMail(
                $ownerRequest->full_name,
                $ownerRequest->request_type ?: 'owner',
                $data['reason'],
            ));
        } catch (\Throwable $exception) {
            $mailSent = false;
            Log::warning('Join request rejection email failed.', [
                'owner_request_id' => $ownerRequest->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        if ($ownerRequest->applicant_user_id) {
            NotificationService::send(
                $ownerRequest->applicant_user_id,
                'لم تتم الموافقة على طلب الانضمام',
                $data['reason'],
                'join_request_rejected',
                ['request_id' => $ownerRequest->id]
            );
        }

        ActivityLogger::log('rejected_join_request', 'owner_request', $ownerRequest->id, $data['reason']);

        return $this->ok([
            'request' => $ownerRequest->fresh(['admin:id,name', 'serviceCategory:id,name_ar,name_en']),
            'mail_sent' => $mailSent,
        ], 'تم رفض طلب الانضمام وإشعار مقدم الطلب.');
    }
}
