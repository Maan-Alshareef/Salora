<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InvitationTemplate extends Model
{
    protected $fillable = ['event_type_id','title_ar','title_en','body_ar','body_en','theme','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function eventType(){ return $this->belongsTo(EventType::class); }
}
