<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingChangeRequest extends Model
{
    protected $fillable = [
        'booking_id', 'customer_id', 'type', 'requested_changes', 'reason', 'status',
        'reviewed_by', 'decision_reason', 'decided_at',
    ];

    protected $casts = [
        'requested_changes' => 'array',
        'decided_at' => 'datetime',
    ];

    public function booking(){ return $this->belongsTo(Booking::class); }
    public function customer(){ return $this->belongsTo(User::class, 'customer_id')->withTrashed(); }
    public function reviewer(){ return $this->belongsTo(User::class, 'reviewed_by')->withTrashed(); }
}
