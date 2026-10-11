<?php

/**
 * CEPFMS application settings (what the original app kept in config/config.php).
 */
return [

    // ---- AI classification (Google Gemini) ------------------------------
    // Leave GEMINI_API_KEY blank to use the offline English/Filipino/Taglish
    // keyword classifier. Never expose the key client-side.
    'gemini' => [
        'api_key'        => env('GEMINI_API_KEY', ''),
        // "-latest" aliases so Google's model retirements don't silently
        // break classification. Lite answers fastest for this short prompt.
        'model'          => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),
        // Retried once on overload / rate limit before the keyword fallback.
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-flash-latest'),
        'api_base'       => 'https://generativelanguage.googleapis.com/v1beta/models/',
    ],

    // ---- Citizen photo uploads ------------------------------------------
    'uploads' => [
        'dir'          => 'uploads/submissions/', // under public/
        'max_files'    => 5,
        'max_size'     => 5 * 1024 * 1024, // 5MB per file
        'allowed_mime' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png'  => ['png'],
            'image/webp' => ['webp'],
            'image/heic' => ['heic'],
            'image/heif' => ['heic'],
        ],
    ],

    // ---- Citizen identity verification -------------------------------------
    // Government IDs accepted for verification. Each must show the holder's
    // address, which the reviewing admin checks is in San Jose del Monte.
    'verification' => [
        'id_types' => [
            'PhilSys National ID (PhilID / ePhilID)',
            "Driver's License",
            'UMID',
            "Voter's ID / Voter's Certification",
            'Postal ID',
            'Bulacan Provincial ID',
            'Barangay ID',
            'Senior Citizen ID',
            'PWD ID',
            'Other government-issued ID with address',
        ],
        'photo_max_size' => 5 * 1024 * 1024, // 5MB per photo
        'photo_min_px'   => 300,             // shortest side, so the details are readable
        'max_per_day'    => 3,               // verification attempts per account per 24 hours
    ],

    // ---- Staff sessions ---------------------------------------------------
    // Staff are signed out after this many seconds without activity.
    'staff_idle_seconds' => (int) env('STAFF_IDLE_SECONDS', 180),
];
