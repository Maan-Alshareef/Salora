<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'reference_number', 'customer_id', 'booking_id', 'venue_id', 'owner_id', 'category',
        'subject', 'message', 'attachments', 'status', 'priority', 'admin_reply', 'owner_reply',
    ];

    protected $casts = ['attachments' => 'array'];
    public function customer(){ return $this->belongsTo(User::class, 'customer_id')->withTrashed(); }
    public function booking(){ return $this->belongsTo(Booking::class); }
    public function venue(){ return $this->belongsTo(Venue::class); }
    public function owner(){ return $this->belongsTo(User::class, 'owner_id')->withTrashed(); }
}
