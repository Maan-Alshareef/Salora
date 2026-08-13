<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedInvitation extends Model
{
    protected $fillable = [
        'event_id',
        'customer_id',
        'invitation_template_id',
        'style',
        'host_name',
        'location',
        'message',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function invitationTemplate()
    {
        return $this->belongsTo(InvitationTemplate::class);
    }
}
