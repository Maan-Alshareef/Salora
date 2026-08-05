<?php
namespace App\Http\Controllers\Api;
use App\Models\PaymentMethod;
use App\Models\PayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class PayoutAccountController extends BaseApiController
{
    public function index(Request $request){return $this->ok(PayoutAccount::with('method')->where('user_id',$request->user()->id)->latest()->get());}
    public function methods(Request $request){$scope=$request->user()->role==='owner'?'for_venues':'for_providers';return $this->ok(PaymentMethod::whereIn('slug',['sham_cash','syriatel_cash','al_haram'])->where('is_active',true)->where($scope,true)->orderBy('sort_order')->get());}
    public function store(Request $request){
        $data=$request->validate(['payment_method_id'=>'required|exists:payment_methods,id','account_name'=>'required|string|max:160','account_number'=>'nullable|string|max:190','phone'=>'nullable|string|max:30','city'=>'nullable|string|max:120','branch'=>'nullable|string|max:190','instructions'=>'nullable|string|max:1500','is_default'=>'sometimes|boolean','is_active'=>'sometimes|boolean','qr'=>'nullable|image|mimes:png,jpg,jpeg,webp|max:2048']);
        $method=PaymentMethod::whereKey($data['payment_method_id'])->whereIn('slug',['sham_cash','syriatel_cash','al_haram'])->where('is_active',true)->firstOrFail();
        if(in_array($method->slug,['sham_cash','syriatel_cash'],true)&&empty($data['account_number'])&&empty($data['phone'])) return $this->fail('رقم المحفظة أو الهاتف مطلوب.',422);
        return DB::transaction(function()use($request,$data){if($request->boolean('is_default'))PayoutAccount::where('user_id',$request->user()->id)->update(['is_default'=>false]);if($request->hasFile('qr'))$data['qr_path']=$request->file('qr')->store('payout-qr','public');$account=PayoutAccount::create([...$data,'user_id'=>$request->user()->id,'is_active'=>$data['is_active']??true]);return $this->ok($account->load('method'),'تمت إضافة حساب الاستلام.',201);});
    }
    public function update(Request $request,PayoutAccount $payoutAccount){abort_unless((int)$payoutAccount->user_id===(int)$request->user()->id,403);$data=$request->validate(['account_name'=>'sometimes|required|string|max:160','account_number'=>'nullable|string|max:190','phone'=>'nullable|string|max:30','city'=>'nullable|string|max:120','branch'=>'nullable|string|max:190','instructions'=>'nullable|string|max:1500','is_default'=>'sometimes|boolean','is_active'=>'sometimes|boolean','qr'=>'nullable|image|mimes:png,jpg,jpeg,webp|max:2048']);return DB::transaction(function()use($request,$payoutAccount,$data){if($request->boolean('is_default'))PayoutAccount::where('user_id',$request->user()->id)->whereKeyNot($payoutAccount->id)->update(['is_default'=>false]);if($request->hasFile('qr')){if($payoutAccount->qr_path)Storage::disk('public')->delete($payoutAccount->qr_path);$data['qr_path']=$request->file('qr')->store('payout-qr','public');}unset($data['qr']);$payoutAccount->update($data);return $this->ok($payoutAccount->fresh('method'),'تم تحديث الحساب.');});}
    public function destroy(Request $request,PayoutAccount $payoutAccount){abort_unless((int)$payoutAccount->user_id===(int)$request->user()->id,403);$payoutAccount->update(['is_active'=>false,'is_default'=>false]);return $this->ok(null,'تم تعطيل حساب الاستلام بأمان.');}
}
