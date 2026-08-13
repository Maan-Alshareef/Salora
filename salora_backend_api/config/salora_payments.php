<?php

return [
    'commission_percent' => 10,
    'refund_deadline_hours' => 48,
    'customer_refund' => [
        'full_before_days' => 7,
        'half_from_days' => 5,
        'zero_under_hours' => 120,
    ],
    'allowed_method_slugs' => ['sham_cash', 'syriatel_cash', 'al_haram'],
];
