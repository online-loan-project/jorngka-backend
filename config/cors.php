<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'], // Added 'storage/*' if serving images

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://jorngka-online.netlify.app',
        '*'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];