<?php

return [
    'credentials' => env(
        'FIREBASE_CREDENTIALS',
        'storage/app/firebase/salora-service-account.json',
    ),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'android_channel_id' => env(
        'FIREBASE_ANDROID_CHANNEL_ID',
        'salora_high_importance',
    ),
];