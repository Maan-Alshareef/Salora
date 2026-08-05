<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    public const PURPOSE_VERIFY_EMAIL = 'verify_email';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';
    public const PURPOSE_JOIN_REQUEST = 'join_request';
    public const PURPOSE_CHANGE_EMAIL = 'change_email';

    protected $fillable = [
        'user_id', 'email', 'purpose', 'code_hash', 'attempts', 'expires_at',
        'resend_available_at', 'used_at', 'request_ip',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'resend_available_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
