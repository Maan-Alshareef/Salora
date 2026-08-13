<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPaymentAdjustment extends Model
{
    protected $table = 'salora_booking_payment_adjustments';
    protected $guarded = [];
    protected $casts = [
        'amount_syp' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'old_total_syp' => 'decimal:2',
        'new_total_syp' => 'decimal:2',
        'paid_syp' => 'decimal:2',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function paymentProof()
    {
        return $this->belongsTo(PaymentProof::class, 'payment_proof_id');
    }
}
