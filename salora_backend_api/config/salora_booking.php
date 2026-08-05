<?php

return [
    'preparation_minutes' => (int) env('SALORA_BOOKING_PREPARATION_MINUTES', 30),
    'temporary_hold_minutes' => (int) env('SALORA_BOOKING_HOLD_MINUTES', 360),
    'lock_seconds' => (int) env('SALORA_BOOKING_LOCK_SECONDS', 120),

    'terminal_statuses' => [
        'cancelled',
        'canceled',
        'rejected',
        'declined',
        'expired',
        'refunded',
        'deleted',
    ],

    'temporary_hold_statuses' => [
        'draft',
        'checkout',
        'initiated',
        'payment_pending',
        'pending_payment',
        'awaiting_payment',
    ],
];