<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingService extends Model
{
    protected $fillable = ['booking_id','service_id','service_name','service_type','quantity','unit_price_syp','unit_price_usd','total_syp','total_usd'];
    protected $casts = ['unit_price_syp'=>'decimal:2','unit_price_usd'=>'decimal:2','total_syp'=>'decimal:2','total_usd'=>'decimal:2'];
    public function booking(){ return $this->belongsTo(Booking::class); }
    public function service(){ return $this->belongsTo(Service::class); }
}
