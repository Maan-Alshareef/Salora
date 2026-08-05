<?php
namespace App\Http\Controllers\Api\Provider;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
class ProviderProfileController extends BaseApiController
{
 public function show(Request $request){$profile=ProviderProfile::firstOrCreate(['user_id'=>$request->user()->id],['business_name'=>$request->user()->name,'contact_phone'=>$request->user()->phone,'whatsapp_phone'=>$request->user()->phone,'allow_phone'=>true,'allow_whatsapp'=>true]);return $this->ok(['user'=>$request->user()->fresh(),'profile'=>$profile]);}
 public function update(Request $request){$data=$request->validate(['business_name'=>'nullable|string|max:180','city'=>'nullable|string|max:120','bio'=>'nullable|string|max:2000','coverage_areas'=>'nullable|array|max:50','coverage_areas.*'=>'string|max:120','working_hours'=>'nullable|array','days_off'=>'nullable|array|max:60','days_off.*'=>'date','contact_phone'=>'nullable|string|max:40','whatsapp_phone'=>'nullable|string|max:40','allow_phone'=>'sometimes|boolean','allow_whatsapp'=>'sometimes|boolean']);$profile=ProviderProfile::updateOrCreate(['user_id'=>$request->user()->id],$data);return $this->ok(['user'=>$request->user()->fresh(),'profile'=>$profile->fresh()],'تم تحديث بيانات مقدم الخدمة وخيارات ظهور رقم التواصل.');}
}
