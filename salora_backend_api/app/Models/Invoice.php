<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'receipt_number',
        'verification_token',
        'booking_id',
        'customer_id',
        'payee_id',
        'source_type',
        'source_id',
        'subtotal_syp',
        'subtotal_usd',
        'discount_syp',
        'discount_usd',
        'total_syp',
        'total_usd',
        'commission_syp',
        'commission_usd',
        'net_syp',
        'net_usd',
        'currency',
        'status',
        'due_at',
        'payment_deadline_at',
        'payment_reminder_sent_at',
        'review_deadline_at',
        'review_reminder_sent_at',
        'review_overdue_notified_at',
        'paid_at',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = [
        'subtotal_syp' => 'decimal:2',
        'subtotal_usd' => 'decimal:2',
        'discount_syp' => 'decimal:2',
        'discount_usd' => 'decimal:2',
        'total_syp' => 'decimal:2',
        'total_usd' => 'decimal:2',
        'commission_syp' => 'decimal:2',
        'commission_usd' => 'decimal:2',
        'net_syp' => 'decimal:2',
        'net_usd' => 'decimal:2',
        'due_at' => 'datetime',
        'payment_deadline_at' => 'datetime',
        'payment_reminder_sent_at' => 'datetime',
        'review_deadline_at' => 'datetime',
        'review_reminder_sent_at' => 'datetime',
        'review_overdue_notified_at' => 'datetime',
        'paid_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected $appends = ['verification_url'];

    public function getVerificationUrlAttribute(): ?string
    {
        return $this->verification_token
            ? url('/api/receipts/verify/'.$this->verification_token)
            : null;
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function providerServiceRequest()
    {
        return $this->belongsTo(ProviderServiceRequest::class, 'source_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function payee()
    {
        return $this->belongsTo(User::class, 'payee_id')->withTrashed();
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by')->withTrashed();
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function paymentProofs()
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function latestPaymentProof()
    {
        return $this->hasOne(PaymentProof::class)->latestOfMany();
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class);
    }
}
