<?php
namespace App\Models;

use App\Models\Concerns\TracksPlatformCommission;
use Illuminate\Database\Eloquent\Model;
class ProviderServiceRequest extends Model
{
    use TracksPlatformCommission;
    protected $fillable=['booking_id','customer_id','provider_id','service_id','invoice_id','service_name','service_category','price_syp','price_usd','payment_type','status','payment_status','customer_notes','provider_reply','provider_decision_at','payment_deadline_at'];
    protected $casts=['provider_decision_at'=>'datetime','payment_deadline_at'=>'datetime','price_syp'=>'decimal:2','price_usd'=>'decimal:2'];
    public function booking(){return $this->belongsTo(Booking::class);} public function customer(){return $this->belongsTo(User::class,'customer_id')->withTrashed();} public function provider(){return $this->belongsTo(User::class,'provider_id')->withTrashed();} public function service(){return $this->belongsTo(Service::class);} public function invoice(){return $this->belongsTo(Invoice::class);}
}
