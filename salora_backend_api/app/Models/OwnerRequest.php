<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OwnerRequest extends Model
{
    protected $fillable=['applicant_user_id','request_type','application_source','full_name','email','email_verified_at','phone','hall_name','city','address','service_category','service_category_id','service_description','sample_work_url','notes','status','admin_id','created_owner_id','reviewed_at','rejection_reason'];
    protected $casts=['reviewed_at'=>'datetime','email_verified_at'=>'datetime'];
    public function serviceCategory(){return $this->belongsTo(ServiceCategory::class,'service_category_id');} public function applicant(){return $this->belongsTo(User::class,'applicant_user_id')->withTrashed();} public function admin(){return $this->belongsTo(User::class,'admin_id')->withTrashed();} public function owner(){return $this->belongsTo(User::class,'created_owner_id')->withTrashed();}
}
