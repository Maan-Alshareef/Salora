<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'created_by', 'scope', 'venue_id', 'owner_id', 'title_ar', 'title_en', 'description_ar',
        'description_en', 'discount_type', 'discount_value', 'discount_currency', 'start_date',
        'end_date', 'status', 'rejection_reason',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'discount_value' => 'decimal:2'];
    public function creator(){ return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function owner(){ return $this->belongsTo(User::class, 'owner_id')->withTrashed(); }
    public function venue(){ return $this->belongsTo(Venue::class); }
}
