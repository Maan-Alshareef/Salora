<?php

return [
    'otp' => [
        // This flag is read through config so it remains available after config:cache.
        'expose_in_local' => (bool) env('OTP_EXPOSE_IN_LOCAL', false),
    ],
];
