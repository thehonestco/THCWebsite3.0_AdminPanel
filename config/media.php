<?php

return [
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 's3')),
    'base_directory' => env('MEDIA_BASE_DIRECTORY', 'media-center'),
    'max_files_per_request' => (int) env('MEDIA_MAX_FILES_PER_REQUEST', 20),
    'max_file_size_kb' => (int) env('MEDIA_MAX_FILE_SIZE_KB', 512000),

    'image' => [
        'quality' => (int) env('MEDIA_IMAGE_QUALITY', 82),
        'max_width' => (int) env('MEDIA_IMAGE_MAX_WIDTH', 2560),
    ],

    'video' => [
        'ffmpeg_path' => env('MEDIA_FFMPEG_PATH', 'ffmpeg'),
        'crf' => (int) env('MEDIA_VIDEO_CRF', 32),
        'audio_bitrate' => env('MEDIA_VIDEO_AUDIO_BITRATE', '96k'),
        'timeout_seconds' => (int) env('MEDIA_VIDEO_TIMEOUT_SECONDS', 300),
    ],

    'presign' => [
        'expiry_minutes' => (int) env('MEDIA_PRESIGN_EXPIRY_MINUTES', 5),
        'directory' => env('MEDIA_PRESIGN_DIRECTORY', 'uploads'),
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
            'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska',
            'audio/mpeg', 'audio/wav', 'audio/aac', 'audio/mp4', 'audio/ogg', 'audio/flac',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
    ],
];
