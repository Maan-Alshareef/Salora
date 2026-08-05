<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'invoice_id', 'payment_proof_id', 'method', 'reference', 'amount', 'currency',
        'status', 'metadata', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function invoice(){ return $this->belongsTo(Invoice::class); }
    public function paymentProof(){ return $this->belongsTo(PaymentProof::class); }
}
