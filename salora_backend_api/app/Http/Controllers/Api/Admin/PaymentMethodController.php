<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
class PaymentMethodController extends BaseApiController
{
    private const ALLOWED=['sham_cash','syriatel_cash','al_haram'];
    public function index(){return $this->ok(PaymentMethod::orderBy('sort_order')->get());}
    public function update(Request $request,PaymentMethod $paymentMethod){
        abort_unless(in_array($paymentMethod->slug,self::ALLOWED,true),404);
        $data=$request->validate(['name_ar'=>'sometimes|required|string|max:120','name_en'=>'sometimes|required|string|max:120','instructions'=>'nullable|string|max:2000','for_venues'=>'sometimes|boolean','for_providers'=>'sometimes|boolean','is_active'=>'sometimes|boolean','sort_order'=>'sometimes|integer|min:0|max:100','logo'=>'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:2048']);
        if($request->hasFile('logo')){$old=$paymentMethod->logo_path;$data['logo_path']='/storage/'.$request->file('logo')->store('payment-methods','public');if($old&&str_starts_with($old,'/storage/'))Storage::disk('public')->delete(substr($old,9));}
        unset($data['logo']);$paymentMethod->update($data);ActivityLogger::log('updated_payment_method','payment_method',$paymentMethod->id);
        return $this->ok($paymentMethod->fresh(),'تم تحديث وسيلة الدفع.');
    }
    public function toggle(Request $request,PaymentMethod $paymentMethod){abort_unless(in_array($paymentMethod->slug,self::ALLOWED,true),404);$paymentMethod->update(['is_active'=>$request->boolean('is_active')]);return $this->ok($paymentMethod->fresh(),'تم تحديث الحالة.');}
}
