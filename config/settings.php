<?php

return [
    'cron'    => [
        'limit' => env('CRON_LIMIT', 2000)
    ],
    'publish' => [
        'limit' => env('PUBLISH_LIMIT', 250000)
    ]
];
