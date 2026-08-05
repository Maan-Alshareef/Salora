<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Venue extends Model
{
    protected $fillable=['owner_id','name_ar','name_en','description_ar','description_en','cancellation_policy','booking_terms','city','address','contact_phone','contact_whatsapp','map_url','google_place_id','latitude','longitude','capacity','price_syp','price_usd','currency_base','status','rejection_reason','is_featured','rating_avg','reviews_count','amenities','policies','vendor_categories','opening_hours','hourly_price_syp','minimum_booking_minutes','maximum_booking_minutes','cleanup_minutes','pricing_updated_at'];
    protected $casts=['is_featured'=>'boolean','amenities'=>'array','policies'=>'array','vendor_categories'=>'array','opening_hours'=>'array','price_syp'=>'decimal:2','price_usd'=>'decimal:2','rating_avg'=>'decimal:2','hourly_price_syp'=>'decimal:2','minimum_booking_minutes'=>'integer','maximum_booking_minutes'=>'integer','cleanup_minutes'=>'integer','pricing_updated_at'=>'datetime'];
    public function owner(){return $this->belongsTo(User::class,'owner_id')->withTrashed();}
    public function images(){return $this->hasMany(VenueImage::class)->orderBy('sort_order');}
    public function videos(){return $this->hasMany(VenueVideo::class)->orderBy('sort_order');}
    public function eventTypes(){return $this->belongsToMany(EventType::class,'venue_event_types');}
    public function services(){return $this->belongsToMany(Service::class,'venue_services')->withPivot(['custom_price_syp','custom_price_usd','is_available'])->withTimestamps();}
    public function bookings(){return $this->hasMany(Booking::class);}
    public function reviews(){return $this->hasMany(Review::class);}
    public function revisions(){return $this->hasMany(VenueRevision::class)->latest();}
    public function pendingRevision(){return $this->hasOne(VenueRevision::class)->where('status','pending')->latestOfMany();}
}
