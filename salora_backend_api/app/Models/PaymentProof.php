<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentProof extends Model
{
    protected $fillable=['booking_id','invoice_id','customer_id','image_url','amount_syp','amount_usd','payment_method','payment_method_id','payout_account_id','sender_name','transaction_reference','transferred_at','customer_notes','status','attempt_no','admin_id','owner_id','reviewer_id','reviewer_role','rejection_reason','uploaded_at','reviewed_at'];
    protected $casts=['uploaded_at'=>'datetime','reviewed_at'=>'datetime','transferred_at'=>'datetime','amount_syp'=>'decimal:2','amount_usd'=>'decimal:2','attempt_no'=>'integer'];
    protected $appends=['image_full_url'];
    public function getImageFullUrlAttribute(): string{return url('/api/payment-proofs/'.$this->id.'/image');}
    public function booking(){return $this->belongsTo(Booking::class);}
    public function invoice(){return $this->belongsTo(Invoice::class);}
    public function customer(){return $this->belongsTo(User::class,'customer_id')->withTrashed();}
    public function admin(){return $this->belongsTo(User::class,'admin_id')->withTrashed();}
    public function owner(){return $this->belongsTo(User::class,'owner_id')->withTrashed();}
    public function reviewer(){return $this->belongsTo(User::class,'reviewer_id')->withTrashed();}
    public function method(){return $this->belongsTo(PaymentMethod::class,'payment_method_id');}
    public function payoutAccount(){return $this->belongsTo(PayoutAccount::class);}
    public function transaction(){return $this->hasOne(PaymentTransaction::class);}
}
