<?php

return [
    'preparation_minutes' => (int) env('SALORA_BOOKING_PREPARATION_MINUTES', 30),
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

];