<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS — WedPlan API
    |--------------------------------------------------------------------------
    | Permet au frontend React (Vite) de communiquer avec l'API Laravel.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [     'http://localhost:3000',   
        env('FRONTEND_URL_1', 'https://wedplan-chi.vercel.app'), // Domaine principal récupéré depuis le fichier .env       
        env('FRONTEND_URL_2', 'https://wedplan.com'), // Domaine secondaire en cas de panne
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // false = pas de cookies cross-origin nécessaires avec Sanctum Token
    'supports_credentials' => true,

];
