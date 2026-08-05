<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['customer_id','venue_id','service_id','booking_id','rating','comment','status','owner_reply'];
    public function customer(){ return $this->belongsTo(User::class, 'customer_id')->withTrashed(); }
    public function venue(){ return $this->belongsTo(Venue::class); }
    public function service(){ return $this->belongsTo(Service::class); }
    public function booking(){ return $this->belongsTo(Booking::class); }
}
