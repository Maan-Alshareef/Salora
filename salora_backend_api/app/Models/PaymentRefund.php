<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $fillable = ['invoice_id','booking_id','customer_id','payee_id','requested_by_role','reason','refund_percent','amount_syp','amount_usd','status','due_at','payment_method_id','transaction_reference','proof_path','transferred_at','customer_confirmed_at','disputed_at','resolved_by','resolution_notes'];
    protected $casts = ['refund_percent'=>'decimal:2','amount_syp'=>'decimal:2','amount_usd'=>'decimal:2','due_at'=>'datetime','transferred_at'=>'datetime','customer_confirmed_at'=>'datetime','disputed_at'=>'datetime'];
    protected $appends = ['proof_url'];
    public function getProofUrlAttribute(): ?string { return $this->proof_path ? url('/api/refunds/'.$this->id.'/proof') : null; }
    public function invoice(){ return $this->belongsTo(Invoice::class); }
    public function booking(){ return $this->belongsTo(Booking::class); }
    public function customer(){ return $this->belongsTo(User::class,'customer_id')->withTrashed(); }
    public function payee(){ return $this->belongsTo(User::class,'payee_id')->withTrashed(); }
    public function method(){ return $this->belongsTo(PaymentMethod::class,'payment_method_id'); }
    public function resolver(){ return $this->belongsTo(User::class,'resolved_by')->withTrashed(); }
}
