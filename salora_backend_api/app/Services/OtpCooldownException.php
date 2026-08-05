<?php

namespace App\Services;

use RuntimeException;

class OtpCooldownException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Please wait before requesting another OTP.');
    }
}
