<?php
namespace App\Http\Controllers\Api;

use App\Models\EmailOtp;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\InvalidOtpException;
use App\Services\NotificationService;
use App\Services\OtpCooldownException;
use App\Services\OtpDeliveryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AuthController extends BaseApiController
{
    public function __construct(private readonly EmailOtpService $otpService){}

    public function login(Request $request)
    {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string','client_type'=>'sometimes|string|in:mobile,dashboard,web','expected_role'=>'sometimes|string|max:40']);$email=mb_strtolower(trim($data['email']));$user=User::where('email',$email)->first();
        if($user?->locked_until?->isPast()){$user->forceFill(['failed_login_attempts'=>0,'locked_until'=>null])->save();$user->refresh();}
        if($user?->locked_until?->isFuture())return $this->fail('تم قفل تسجيل الدخول مؤقتاً بسبب المحاولات المتكررة.',429,['code'=>'account_locked','retry_after_seconds'=>max(1,now()->diffInSeconds($user->locked_until,false)),'locked_until'=>$user->locked_until->toIso8601String()]);
        if(!$user||!Hash::check($data['password'],$user->password)){
            if($user){$attempts=min(5,(int)$user->failed_login_attempts+1);$locked=$attempts>=5?now()->addMinutes(10):null;$user->forceFill(['failed_login_attempts'=>$attempts,'locked_until'=>$locked])->save();if($locked)return $this->fail('تم قفل تسجيل الدخول لمدة 10 دقائق.',429,['code'=>'account_locked','retry_after_seconds'=>600,'locked_until'=>$locked->toIso8601String()]);}
            return $this->fail('البريد الإلكتروني أو كلمة المرور غير صحيحة.',422,['code'=>'invalid_credentials']);
        }
        $clientType = strtolower((string) ($data['client_type'] ?? ''));
        if ($clientType === 'mobile' && in_array((string) $user->role, ['admin', 'owner'], true)) {
            return $this->fail('هذا الحساب غير مسموح له بالدخول إلى تطبيق الموبايل.',403,['code'=>'mobile_role_not_allowed','role'=>$user->role]);
        }
        $user->reactivateIfSuspensionExpired();$user->refresh();
        if($user->status!=='active')return $this->fail($user->status==='suspended'?'الحساب مجمّد مؤقتاً.':'الحساب غير نشط.',403,['code'=>$user->status==='suspended'?'account_suspended':'account_inactive','suspended_until'=>$user->suspended_until?->toIso8601String(),'reason'=>$user->suspension_reason]);
        if(!$user->email_verified_at)return $this->fail('يجب توثيق البريد الإلكتروني قبل تسجيل الدخول.',403,['code'=>'email_not_verified','email'=>$email]);
        $user->tokens()->delete();$user->forceFill(['last_login_at'=>now(),'failed_login_attempts'=>0,'locked_until'=>null])->save();
        try{NotificationService::send($user->id,'تسجيل دخول جديد','تم تسجيل الدخول إلى حسابك في Salora بتاريخ '.now()->format('Y-m-d H:i').'.','security_login',['ip'=>$request->ip()]);}catch(\Throwable $e){Log::warning('Login notification failed',['user_id'=>$user->id,'error'=>$e->getMessage()]);}
        return $this->ok(['token'=>$this->createToken($user),'user'=>$user->fresh(['providerProfile'])],'تم تسجيل الدخول بنجاح.');
    }

    public function register(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|max:190','phone'=>['required','regex:/^\\d{10}$/'],'password'=>['required','confirmed',Password::min(8)->letters()->mixedCase()->numbers()->symbols()]]);
        $email=mb_strtolower(trim($data['email']));$phone=trim($data['phone']);$existing=User::withTrashed()->where('email',$email)->first();
        if($existing){if(!$existing->trashed()&&!$existing->email_verified_at&&$existing->role==='customer')return $this->fail('الحساب موجود لكنه بانتظار توثيق البريد الإلكتروني.',409,['code'=>'email_verification_required','email'=>$email]);return $this->fail('البريد الإلكتروني مستخدم مسبقاً.',422,['email'=>['البريد الإلكتروني مستخدم مسبقاً.']]);}
        if(User::withTrashed()->where('phone',$phone)->exists())return $this->fail('رقم الهاتف مستخدم مسبقاً.',422,['phone'=>['رقم الهاتف مستخدم مسبقاً.']]);
        $user=User::create(['name'=>trim($data['name']),'email'=>$email,'phone'=>$phone,'password'=>$data['password'],'role'=>'customer','status'=>'active','business_status'=>'approved','email_verified_at'=>null]);
        try{$meta=$this->otpService->issue($user,EmailOtp::PURPOSE_VERIFY_EMAIL,$request->ip());$sent=true;}catch(OtpDeliveryException){$meta=['expires_in_seconds'=>600,'resend_after_seconds'=>0];$sent=false;}
        return $this->ok(['verification_required'=>true,'email'=>$email,'masked_email'=>$this->maskEmail($email),'mail_sent'=>$sent,...$meta],$sent?'تم إنشاء الحساب وإرسال رمز التوثيق.':'تم إنشاء الحساب وتعذر إرسال الرمز حالياً. استخدم إعادة الإرسال.',$sent?201:202);
    }

    public function verifyEmail(Request $request){$data=$request->validate(['email'=>'required|email','otp'=>'required|digits:6']);$email=mb_strtolower(trim($data['email']));$user=User::where('email',$email)->first();if(!$user)return $this->fail('رمز التحقق غير صحيح أو منتهي الصلاحية.',422,['code'=>'invalid_otp']);if($user->email_verified_at)return $this->fail('البريد موثّق مسبقاً.',409,['code'=>'email_already_verified']);try{$this->otpService->verify($email,EmailOtp::PURPOSE_VERIFY_EMAIL,$data['otp']);}catch(InvalidOtpException){return $this->fail('رمز التحقق غير صحيح أو منتهي الصلاحية.',422,['code'=>'invalid_otp']);}$user->forceFill(['email_verified_at'=>now()])->save();return $this->ok(['token'=>$this->createToken($user),'user'=>$user->fresh()],'تم توثيق البريد وتفعيل الحساب.');}
    public function resendEmailVerification(Request $request){$data=$request->validate(['email'=>'required|email']);$email=mb_strtolower(trim($data['email']));$user=User::where('email',$email)->first();if(!$user||$user->email_verified_at)return $this->ok(['expires_in_seconds'=>600,'resend_after_seconds'=>60],'إذا كان الحساب يحتاج التوثيق فسيتم إرسال رمز.');try{$meta=$this->otpService->issue($user,EmailOtp::PURPOSE_VERIFY_EMAIL,$request->ip());}catch(OtpCooldownException $e){return $this->fail('يرجى الانتظار قبل طلب رمز جديد.',429,['code'=>'otp_cooldown','retry_after_seconds'=>$e->retryAfterSeconds]);}catch(OtpDeliveryException){return $this->fail('تعذر إرسال البريد حالياً.',503,['code'=>'mail_delivery_failed']);}return $this->ok($meta,'تم إرسال رمز جديد.');}
    public function me(Request $request){return $this->ok($request->user()->fresh(['providerProfile']));}
    public function updateProfile(Request $request){$u=$request->user();$data=$request->validate(['name'=>'sometimes|required|string|max:120','phone'=>['sometimes','required','regex:/^\\d{10}$/','unique:users,phone,'.$u->id]]);$u->update($data);return $this->ok($u->fresh(['providerProfile']),'تم تحديث الملف الشخصي.');}
    public function uploadAvatar(Request $request){$request->validate(['image'=>'required|image|mimes:jpeg,jpg,png,webp|max:4096']);$u=$request->user();$path=$request->file('image')->store('avatars/'.$u->id,'public');$old=trim((string)$u->avatar);$u->update(['avatar'=>$path]);if($old!==''&&!str_starts_with($old,'http'))Storage::disk('public')->delete($old);return $this->ok($u->fresh(),'تم تحديث الصورة الشخصية.');}
    public function deleteAvatar(Request $request){$u=$request->user();$old=trim((string)$u->avatar);$u->update(['avatar'=>null]);if($old!==''&&!str_starts_with($old,'http'))Storage::disk('public')->delete($old);return $this->ok($u->fresh(),'تم حذف الصورة الشخصية.');}

    public function requestEmailChange(Request $request)
    {
        $u=$request->user();$data=$request->validate(['email'=>'required|email|max:190']);$email=mb_strtolower(trim($data['email']));
        if($email===mb_strtolower($u->email))return $this->fail('البريد الجديد مطابق للبريد الحالي.',422);
        if(User::withTrashed()->where('email',$email)->whereKeyNot($u->id)->exists())return $this->fail('البريد مستخدم بحساب آخر.',422,['email'=>['البريد مستخدم مسبقاً.']]);
        try{$meta=$this->otpService->issueForEmail($email,EmailOtp::PURPOSE_CHANGE_EMAIL,$u,$request->ip());}catch(OtpCooldownException $e){return $this->fail('يرجى الانتظار قبل طلب رمز جديد.',429,['retry_after_seconds'=>$e->retryAfterSeconds]);}catch(OtpDeliveryException){return $this->fail('تعذر إرسال الرمز إلى البريد الجديد.',503);}
        $u->update(['pending_email'=>$email,'pending_email_requested_at'=>now()]);return $this->ok(['pending_email'=>$email,...$meta],'تم إرسال رمز التحقق إلى البريد الجديد.');
    }
    public function verifyEmailChange(Request $request)
    {
        $u=$request->user();$data=$request->validate(['otp'=>'required|digits:6']);$new=trim((string)$u->pending_email);if($new==='')return $this->fail('لا يوجد طلب تغيير بريد فعال.',422);
        try{$this->otpService->verify($new,EmailOtp::PURPOSE_CHANGE_EMAIL,$data['otp']);}catch(InvalidOtpException){return $this->fail('رمز التحقق غير صحيح أو منتهي.',422,['code'=>'invalid_otp']);}
        if(User::withTrashed()->where('email',$new)->whereKeyNot($u->id)->exists())return $this->fail('البريد أصبح مستخدماً بحساب آخر.',409);
        $old=$u->email;$u->forceFill(['email'=>$new,'email_verified_at'=>now(),'pending_email'=>null,'pending_email_requested_at'=>null])->save();$u->tokens()->where('id','!=',$u->currentAccessToken()?->id)->delete();
        try{Mail::raw('تم تغيير بريد حساب Salora من '.$old.' إلى '.$new.'. إذا لم تطلب ذلك تواصل مع الدعم فوراً.',fn($m)=>$m->to($old)->subject('تنبيه تغيير البريد في Salora'));}catch(\Throwable $e){Log::warning('Old email alert failed',['user_id'=>$u->id,'error'=>$e->getMessage()]);}
        NotificationService::send($u->id,'تم تغيير البريد الإلكتروني','تم توثيق البريد الجديد وتسجيل الخروج من الأجهزة الأخرى.','email_changed');
        return $this->ok($u->fresh(),'تم تغيير البريد بنجاح.');
    }
    public function cancelEmailChange(Request $request){$request->user()->update(['pending_email'=>null,'pending_email_requested_at'=>null]);return $this->ok(null,'تم إلغاء طلب تغيير البريد.');}

    public function logout(Request $request){$request->user()->currentAccessToken()?->delete();return $this->ok(null,'تم تسجيل الخروج.');}
    public function changePassword(Request $request){$data=$request->validate(['current_password'=>'required|string','password'=>['required','confirmed',Password::min(8)->letters()->mixedCase()->numbers()->symbols()]]);$u=$request->user();if(!Hash::check($data['current_password'],$u->password))return $this->fail('كلمة المرور الحالية غير صحيحة.',422,['current_password'=>['كلمة المرور الحالية غير صحيحة.']]);$u->update(['password'=>$data['password'],'must_change_password'=>false,'failed_login_attempts'=>0,'locked_until'=>null]);$u->tokens()->where('id','!=',$u->currentAccessToken()?->id)->delete();NotificationService::send($u->id,'تم تغيير كلمة المرور','تم تغيير كلمة المرور وتسجيل الخروج من الأجهزة الأخرى.','password_changed');return $this->ok($u->fresh(),'تم تغيير كلمة المرور.');}
    public function requestPasswordReset(Request $request){$data=$request->validate(['email'=>'required|email']);$email=mb_strtolower(trim($data['email']));$user=User::where('email',$email)->first();$response=['expires_in_seconds'=>600,'resend_after_seconds'=>60];if($user){try{$response=$this->otpService->issue($user,EmailOtp::PURPOSE_PASSWORD_RESET,$request->ip());}catch(OtpCooldownException $e){$response['resend_after_seconds']=$e->retryAfterSeconds;}catch(OtpDeliveryException){}}return $this->ok($response,'إذا كان الحساب موجوداً فقد تم إرسال رمز الاستعادة.');}
    public function resetPassword(Request $request){$data=$request->validate(['email'=>'required|email','otp'=>'required|digits:6','password'=>['required','confirmed',Password::min(8)->letters()->mixedCase()->numbers()->symbols()]]);$email=mb_strtolower(trim($data['email']));$u=User::where('email',$email)->first();if(!$u)return $this->fail('رمز الاستعادة غير صحيح أو منتهي.',422,['code'=>'invalid_otp']);try{$this->otpService->verify($email,EmailOtp::PURPOSE_PASSWORD_RESET,$data['otp']);}catch(InvalidOtpException){return $this->fail('رمز الاستعادة غير صحيح أو منتهي.',422,['code'=>'invalid_otp']);}$u->update(['password'=>$data['password'],'must_change_password'=>false,'failed_login_attempts'=>0,'locked_until'=>null]);$u->tokens()->delete();NotificationService::send($u->id,'تمت إعادة تعيين كلمة المرور','تم إغلاق جميع الجلسات القديمة.','password_reset');return $this->ok(null,'تمت إعادة تعيين كلمة المرور.');}
    private function createToken(User $u):string{return $u->createToken('salora-api-token',['*'],now()->addDays(7))->plainTextToken;}
    private function maskEmail(string $email):string{[$local,$domain]=array_pad(explode('@',$email,2),2,'');$v=mb_substr($local,0,min(2,mb_strlen($local)));return $v.str_repeat('*',max(2,mb_strlen($local)-mb_strlen($v))).'@'.$domain;}
}
