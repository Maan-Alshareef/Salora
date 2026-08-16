<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EmailOtp;
use App\Models\OwnerRequest;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\EmailOtpService;
use App\Services\InvalidOtpException;
use App\Services\OtpCooldownException;
use App\Services\OtpDeliveryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerRequestController extends BaseApiController
{
    public function __construct(private readonly EmailOtpService $otpService)
    {
    }

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:180',
        ]);

        $email = mb_strtolower(trim($data['email']));
        if ($email === mb_strtolower((string) $request->user()->email)) {
            return $this->fail(
                'يجب استخدام بريد مختلف لحساب العمل؛ حساب العميل يبقى مستقلاً.',
                422,
                ['email' => ['استخدم بريداً غير بريد حساب العميل الحالي.'], 'code' => 'separate_business_email_required']
            );
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            return $this->fail('هذا البريد مرتبط بحساب موجود بالفعل.', 422, [
                'email' => ['اختر بريداً جديداً لإنشاء حساب العمل المستقل.'],
                'code' => 'business_email_already_used',
            ]);
        }

        if (OwnerRequest::where('email', $email)->where('status', 'pending')->exists()) {
            return $this->fail('يوجد طلب قيد المراجعة لهذا البريد بالفعل.', 409, [
                'code' => 'join_request_already_pending',
            ]);
        }

        try {
            $meta = $this->otpService->issueForEmail(
                $email,
                EmailOtp::PURPOSE_JOIN_REQUEST,
                $request->user(),
                $request->ip(),
            );
        } catch (OtpCooldownException $exception) {
            return $this->fail('يرجى الانتظار قبل طلب رمز جديد.', 429, [
                'code' => 'otp_cooldown',
                'retry_after_seconds' => $exception->retryAfterSeconds,
            ]);
        } catch (OtpDeliveryException) {
            return $this->fail('تعذر إرسال رمز التحقق إلى البريد حالياً. حاول لاحقاً.', 503, [
                'code' => 'mail_delivery_failed',
            ]);
        }

        return $this->ok($meta, 'تم إرسال رمز التحقق إلى بريد حساب العمل.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_type' => 'required|in:owner,provider',
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:180',
            'otp' => 'required|digits:6',
            'phone' => ['required', 'regex:/^\d{10}$/'],
            'hall_name' => 'nullable|string|max:180',
            'city' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
            'service_category_id' => [
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereIn('applies_to', ['provider', 'both'])
                ),
            ],
            'service_category' => 'nullable|string|max:160',
            'service_description' => 'nullable|string|max:2000',
            'sample_work_url' => 'nullable|url|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $data['email'] = mb_strtolower(trim($data['email']));
        $data['full_name'] = trim($data['full_name']);
        $data['phone'] = trim($data['phone']);

        if ($data['email'] === mb_strtolower((string) $request->user()->email)) {
            return $this->fail('يجب استخدام بريد مختلف لحساب العمل.', 422, [
                'email' => ['حساب العميل وحساب العمل يجب أن يكونا منفصلين.'],
                'code' => 'separate_business_email_required',
            ]);
        }

        if (User::withTrashed()->where('email', $data['email'])->exists()) {
            return $this->fail('هذا البريد مرتبط بحساب موجود بالفعل.', 422, [
                'email' => ['اختر بريداً جديداً لحساب العمل.'],
                'code' => 'business_email_already_used',
            ]);
        }

        if (OwnerRequest::where('email', $data['email'])->where('status', 'pending')->exists()) {
            return $this->fail('يوجد طلب قيد المراجعة لهذا البريد بالفعل.', 409, [
                'code' => 'join_request_already_pending',
            ]);
        }

        if ($data['request_type'] === 'provider') {
            if (empty($data['service_category_id'])) {
                return $this->fail('تصنيف الخدمة مطلوب لطلب مقدم الخدمة.', 422, [
                    'service_category_id' => ['اختر تصنيف الخدمة.'],
                ]);
            }
            $category = ServiceCategory::findOrFail($data['service_category_id']);
            $data['service_category'] = $category->name_ar ?: $category->name_en;
        } else {
            $data['service_category_id'] = null;
            $data['service_category'] = null;
            $data['service_description'] = null;
            $data['sample_work_url'] = null;
        }

        try {
            $this->otpService->verify($data['email'], EmailOtp::PURPOSE_JOIN_REQUEST, $data['otp']);
        } catch (InvalidOtpException) {
            return $this->fail('رمز التحقق غير صحيح أو منتهي الصلاحية.', 422, [
                'otp' => ['تحقق من الرمز أو اطلب رمزاً جديداً.'],
                'code' => 'invalid_otp',
            ]);
        }

        unset($data['otp']);
        $joinRequest = OwnerRequest::create([
            ...$data,
            'applicant_user_id' => $request->user()->id,
            'email_verified_at' => now(),
            'status' => 'pending',
        ]);

        ActivityLogger::log(
            'submitted_join_request',
            'owner_request',
            $joinRequest->id,
            $data['request_type'] === 'provider'
                ? 'Customer submitted a separate provider-account request with verified email.'
                : 'Customer submitted a separate owner-account request with verified email.'
        );

        $message = $data['request_type'] === 'provider'
            ? 'تم إرسال طلب مقدم الخدمة بعد توثيق البريد. سيُنشئ الأدمن حساب عمل مستقلاً عند الموافقة.'
            : 'تم إرسال طلب مدير الصالة بعد توثيق البريد. سيُنشئ الأدمن حساب عمل مستقلاً عند الموافقة.';

        return $this->ok(
            $joinRequest->load('serviceCategory:id,name_ar,name_en'),
            $message,
            201
        );
    }

    public function mine(Request $request)
    {
        $requests = OwnerRequest::with([
            'serviceCategory:id,name_ar,name_en,image_url',
            'admin:id,name',
            'owner:id,name,email,phone,role',
        ])
            ->where('applicant_user_id', $request->user()->id)
            ->latest()
            ->get();

        return $this->ok($requests);
    }
}
