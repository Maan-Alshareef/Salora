<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCommission extends Model
{
    public const STATUS_UNCOLLECTED = 'uncollected';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COLLECTED = 'collected';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_WAIVED = 'waived';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'source_type', 'source_id', 'source_reference', 'source_title',
        'business_user_id', 'business_role', 'customer_id', 'booking_id',
        'gross_syp', 'gross_usd', 'commission_rate', 'commission_syp', 'commission_usd',
        'net_syp', 'net_usd', 'status', 'collected_syp', 'collected_usd',
        'approved_at', 'collected_at', 'collection_method', 'collection_reference',
        'notes', 'collected_by',
    ];

    protected $casts = [
        'gross_syp' => 'decimal:2',
        'gross_usd' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_syp' => 'decimal:2',
        'commission_usd' => 'decimal:2',
        'net_syp' => 'decimal:2',
        'net_usd' => 'decimal:2',
        'collected_syp' => 'decimal:2',
        'collected_usd' => 'decimal:2',
        'approved_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function businessUser()
    {
        return $this->belongsTo(User::class, 'business_user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}