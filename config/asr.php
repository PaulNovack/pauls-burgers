<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ASR (Automatic Speech Recognition) Configuration
    |--------------------------------------------------------------------------
    */

    'url' => env('ASR_URL', 'http://localhost:5000'),

    'route' => env('ASR_ROUTE', '/transcribe'),

    'timeout' => env('ASR_TIMEOUT', 60),

    'max_file_size' => env('ASR_MAX_FILE_SIZE', 10 * 1024 * 1024), // 10MB default

    'allowed_extensions' => ['wav', 'mp3', 'ogg', 'webm', 'flac', 'm4a'],

    'allowed_mime_types' => [
        'audio/wav',
        'audio/wave',
        'audio/x-wav',
        'audio/mpeg',
        'audio/mp3',
        'audio/ogg',
        'audio/webm',
        'audio/flac',
        'audio/x-m4a',
        'video/webm',  // WebM files are often sent with video MIME type even for audio-only
    ],

    'retry' => [
        'enabled' => env('ASR_RETRY_ENABLED', false),
        'times' => env('ASR_RETRY_TIMES', 2),
        'sleep_ms' => env('ASR_RETRY_SLEEP_MS', 100),
    ],
];
