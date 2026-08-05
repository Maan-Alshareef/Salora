<?php

namespace App\Services;

use RuntimeException;

class InvalidOtpException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired OTP.');
    }
}
