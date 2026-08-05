<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenueOffer extends Model
{
    protected $fillable = [
        'venue_id',
        'title',
        'offer_type',
        'scheduled_discount_type',
        'percentage',
        'fixed_amount_syp',
        'starts_on',
        'ends_on',
        'days_of_week',
        'start_time',
        'end_time',
        'minimum_booking_minutes',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed_amount_syp' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'days_of_week' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
