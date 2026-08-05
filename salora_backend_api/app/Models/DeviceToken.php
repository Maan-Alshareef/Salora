<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id', 'token', 'token_hash', 'platform', 'device_name', 'last_seen_at',
    ];

    protected $hidden = ['token'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function user(){ return $this->belongsTo(User::class); }
}
