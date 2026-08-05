<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProviderProfile extends Model
{
    protected $fillable=['user_id','business_name','city','coverage_areas','working_hours','days_off','bio','contact_phone','whatsapp_phone','allow_phone','allow_whatsapp','show_phone','show_whatsapp'];
    protected $casts=['coverage_areas'=>'array','working_hours'=>'array','days_off'=>'array','allow_phone'=>'boolean','allow_whatsapp'=>'boolean','show_phone'=>'boolean','show_whatsapp'=>'boolean'];
    public function user(){return $this->belongsTo(User::class)->withTrashed();}
}
