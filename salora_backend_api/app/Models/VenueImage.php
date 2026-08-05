<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenueImage extends Model
{
    protected $fillable = ['venue_id','image_url','is_main','sort_order'];
    protected $casts = ['is_main'=>'boolean'];
    public function venue(){ return $this->belongsTo(Venue::class); }
}
