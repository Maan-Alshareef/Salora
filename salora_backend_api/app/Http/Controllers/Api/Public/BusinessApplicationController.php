<?php
namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EmailOtp;
use App\Models\OwnerRequest;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\InvalidOtpException;
use App\Services\OtpCooldownException;
use App\Services\OtpDeliveryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessApplicationController extends BaseApiController
{
    public function __construct(private readonly EmailOtpService $otpService) {}

    public function requestOtp(Request $request)
    {
        $data=$request->validate(['email'=>'required|email|max:190']);
        $email=mb_strtolower(trim($data['email']));
        if(User::withTrashed()->where('email',$email)->exists()) return $this->fail('هذا البريد مرتبط بحساب موجود.',422,['email'=>['استخدم بريداً جديداً لحساب العمل.']]);
        if(OwnerRequest::where('email',$email)->where('status','pending')->exists()) return $this->fail('يوجد طلب قيد المراجعة لهذا البريد.',409);
        try{$meta=$this->otpService->issueForEmail($email,EmailOtp::PURPOSE_JOIN_REQUEST,null,$request->ip());}
        catch(OtpCooldownException $e){return $this->fail('يرجى الانتظار قبل طلب رمز جديد.',429,['retry_after_seconds'=>$e->retryAfterSeconds]);}
        catch(OtpDeliveryException){return $this->fail('تعذر إرسال رمز التحقق حالياً.',503);}
        return $this->ok($meta,'تم إرسال رمز التحقق إلى بريد حساب العمل.');
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'request_type'=>'required|in:owner,provider','full_name'=>'required|string|max:150',
            'email'=>'required|email|max:190','otp'=>'required|digits:6','phone'=>['required','regex:/^\\d{10}$/'],
            'hall_name'=>'nullable|string|max:180','city'=>'nullable|string|max:120','address'=>'nullable|string|max:255',
            'service_category_id'=>['nullable','integer',Rule::exists('service_categories','id')->where(fn($q)=>$q->where('is_active',true)->whereIn('applies_to',['provider','both']))],
            'service_description'=>'nullable|string|max:2000','sample_work_url'=>'nullable|url|max:1000','notes'=>'nullable|string|max:2000',
        ]);
        $data['email']=mb_strtolower(trim($data['email']));$data['phone']=trim($data['phone']);$data['full_name']=trim($data['full_name']);
        if(User::withTrashed()->where('email',$data['email'])->exists()) return $this->fail('هذا البريد مستخدم بحساب موجود.',422);
        if(User::withTrashed()->where('phone',$data['phone'])->exists()) return $this->fail('رقم الهاتف مستخدم بحساب موجود.',422);
        if(OwnerRequest::where('email',$data['email'])->where('status','pending')->exists()) return $this->fail('يوجد طلب قيد المراجعة لهذا البريد.',409);
        if($data['request_type']==='provider' && empty($data['service_category_id'])) return $this->fail('تصنيف الخدمة مطلوب.',422,['service_category_id'=>['اختر تصنيف الخدمة.']]);
        try{$this->otpService->verify($data['email'],EmailOtp::PURPOSE_JOIN_REQUEST,$data['otp']);}
        catch(InvalidOtpException){return $this->fail('رمز التحقق غير صحيح أو منتهي.',422,['otp'=>['تحقق من الرمز.']]);}
        unset($data['otp']);
        if(!empty($data['service_category_id'])){$cat=ServiceCategory::find($data['service_category_id']);$data['service_category']=$cat?($cat->name_ar?:$cat->name_en):null;}
        $application=OwnerRequest::create([...$data,'applicant_user_id'=>null,'application_source'=>'direct','email_verified_at'=>now(),'status'=>'pending']);
        return $this->ok($application->load('serviceCategory:id,name_ar,name_en'),'تم إرسال طلب الانضمام. سينشئ الأدمن حساب عمل مستقلاً عند الموافقة.',201);
    }
}
