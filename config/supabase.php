<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supabase Project URL & Keys
    |--------------------------------------------------------------------------
    |
    | Your Supabase Project REST API URL (e.g. https://xyz.supabase.co)
    | and the corresponding Anon and Service-Role keys.
    |
    */
    'url' => env('SUPABASE_URL'),
    'key' => env('SUPABASE_KEY'), // Anon public key
    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'), // Backend admin key

    /*
    |--------------------------------------------------------------------------
    | Supabase JWT Secret
    |--------------------------------------------------------------------------
    |
    | Secret used to sign and verify Supabase Auth JWT tokens.
    |
    */
    'jwt_secret' => env('SUPABASE_JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Supabase Auth Feature Flag
    |--------------------------------------------------------------------------
    |
    | When enabled, user registration, login, and password resets are
    | delegated to Supabase Auth (GoTrue REST API).
    |
    */
    'auth_enabled' => env('SUPABASE_AUTH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Auto-Create Personal Team
    |--------------------------------------------------------------------------
    |
    | Automatically create a personal team for users authenticated via Supabase.
    |
    */
    'auto_create_team' => env('SUPABASE_AUTO_CREATE_TEAM', true),
];

