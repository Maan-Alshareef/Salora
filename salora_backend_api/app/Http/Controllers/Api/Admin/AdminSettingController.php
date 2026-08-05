<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
use Illuminate\Http\Request;
class AdminSettingController extends BaseApiController
{
    public function index(){ return $this->ok(Setting::orderBy('key')->get()); }
    public function update(Request $r){ $data=$r->validate(['key'=>'required|string','value'=>'required','type'=>'nullable|string']); $setting=Setting::updateOrCreate(['key'=>$data['key']], ['value'=>$data['value'], 'type'=>$data['type'] ?? 'string']); return $this->ok($setting,'Setting saved.'); }
}
