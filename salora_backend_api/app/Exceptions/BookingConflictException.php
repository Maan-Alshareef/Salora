<?php

namespace App\Exceptions;

use RuntimeException;

class BookingConflictException extends RuntimeException
{
    public function __construct(
        public readonly array $conflict,
    ) {
        parent::__construct(
            (string) ($conflict['message'] ?? 'الموعد متعارض مع حجز آخر.'),
        );
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'booking_conflict',
            'data' => $this->conflict,
        ], 409);
    }
}