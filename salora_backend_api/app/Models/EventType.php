<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'emoji', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean'];

    public function todoTemplates(){ return $this->hasMany(TodoTemplate::class)->orderBy('sort_order'); }
    public function invitationTemplates(){ return $this->hasMany(InvitationTemplate::class); }
    public function events(){ return $this->hasMany(Event::class); }
    public function venues(){ return $this->belongsToMany(Venue::class, 'venue_event_types'); }
}
