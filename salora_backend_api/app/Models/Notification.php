<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Notification extends Model
{
    protected $fillable = ['user_id','title','body','type','is_read','data_json'];
    protected $casts = ['is_read'=>'boolean','data_json'=>'array'];
    public function user(){ return $this->belongsTo(User::class)->withTrashed(); }
}
