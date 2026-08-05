<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenueRevision extends Model
{
    protected $fillable = [
        'venue_id', 'owner_id', 'payload', 'event_type_ids', 'service_ids', 'image_urls', 'video_urls',
        'replace_images', 'replace_videos', 'status', 'admin_id', 'decision_reason', 'decided_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'event_type_ids' => 'array',
        'service_ids' => 'array',
        'image_urls' => 'array',
        'video_urls' => 'array',
        'replace_images' => 'boolean',
        'replace_videos' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function venue(){ return $this->belongsTo(Venue::class); }
    public function owner(){ return $this->belongsTo(User::class, 'owner_id')->withTrashed(); }
    public function admin(){ return $this->belongsTo(User::class, 'admin_id')->withTrashed(); }
}
