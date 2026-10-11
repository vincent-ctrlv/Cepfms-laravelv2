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

    // ---- Staff sessions ---------------------------------------------------
    // Staff are signed out after this many seconds without activity.
    'staff_idle_seconds' => (int) env('STAFF_IDLE_SECONDS', 180),
];
