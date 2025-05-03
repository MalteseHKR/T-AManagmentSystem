return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'secure-image/*', 'images/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://garrisonta.org',
        'http://localhost:3000', // for dev if needed
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
