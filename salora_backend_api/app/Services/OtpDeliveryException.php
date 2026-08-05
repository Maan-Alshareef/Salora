<?php

namespace App\Services;

use RuntimeException;

class OtpDeliveryException extends RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('OTP email delivery failed.', 0, $previous);
    }
}
