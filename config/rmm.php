<?php

return [
    // Times (HH:MM, comma separated) in RMM_TIMEZONE at which all active sources are checked.
    'check_times' => array_values(array_filter(array_map('trim', explode(',', env('RMM_CHECK_TIMES', '08:00,09:01'))))),
    'timezone' => env('RMM_TIMEZONE', 'Asia/Tehran'),

    // How deep to scan each source per check (pages * page size items per tab).
    'pages' => (int) env('RMM_PAGES', 2),
    'youtube_page_size' => (int) env('RMM_YT_PAGE_SIZE', 30),
    'instagram_page_size' => (int) env('RMM_IG_PAGE_SIZE', 12),

    // When a source is first added, existing items are marked as seen (baseline).
    // Number of newest items to still deliver on first add (0 = none).
    'initial_send' => (int) env('RMM_INITIAL_SEND', 0),

    'max_resolution' => (int) env('RMM_MAX_RES', 720),
    'retention_days' => (int) env('RMM_RETENTION_DAYS', 7),
    'link_days' => (int) env('RMM_LINK_DAYS', 7),
    'media_root' => env('RMM_MEDIA_ROOT', storage_path('app/media')),
    // nginx internal location that aliases media_root (X-Accel-Redirect). Empty = PHP streams the file.
    'accel_prefix' => env('RMM_ACCEL_PREFIX', ''),

    'worker' => [
        'python' => env('RMM_PYTHON', 'python3'),
        'script' => env('RMM_WORKER', base_path('worker/rmm_worker.py')),
        'timeout' => (int) env('RMM_WORKER_TIMEOUT', 1800),
        'env' => array_filter([
            'RMM_IG_COOKIES' => env('RMM_IG_COOKIES'),
            'RMM_YT_COOKIES' => env('RMM_YT_COOKIES'),
            'RMM_JS_RUNTIME' => env('RMM_JS_RUNTIME'),
            'RMM_FFMPEG_DIR' => env('RMM_FFMPEG_DIR'),
            'PATH' => env('RMM_PATH'),
            'HOME' => env('RMM_HOME'),
            'XDG_CACHE_HOME' => env('RMM_CACHE_HOME'),
        ]),
    ],

    'bale' => [
        'api' => env('RMM_BALE_API', 'https://tapi.bale.ai'),
        // Token is read from a chmod 600 file outside git (or RMM_BALE_TOKEN for local dev).
        'token_file' => env('RMM_BALE_TOKEN_FILE'),
        'token' => env('RMM_BALE_TOKEN'),
        'chat_id' => env('RMM_BALE_CHAT_ID'),
        'max_upload_mb' => (float) env('RMM_BALE_MAX_MB', 49),
        'max_photo_mb' => (float) env('RMM_BALE_MAX_PHOTO_MB', 9.5),
    ],
];
