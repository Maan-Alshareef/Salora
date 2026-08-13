<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'event_type_id', 'name', 'event_date', 'start_time', 'end_time',
        'guests_count', 'budget_syp', 'budget_usd', 'city', 'notes', 'status',
    ];

    protected $casts = [
        'event_date' => 'date',
        'budget_syp' => 'decimal:2',
        'budget_usd' => 'decimal:2',
    ];

    public function customer(){ return $this->belongsTo(User::class, 'customer_id')->withTrashed(); }
    public function eventType(){ return $this->belongsTo(EventType::class); }
    public function todoItems(){ return $this->hasMany(EventTodoItem::class)->orderBy('sort_order')->orderBy('id'); }
    public function bookings(){ return $this->hasMany(Booking::class); }
    public function invitation(){ return $this->hasOne(GeneratedInvitation::class); }
}
