<?php

return [
    'disk' => 'attachments',

    'max_files' => (int) env('VAULT_MAX_FILES', 5),

    'max_file_size_kb' => (int) env('VAULT_MAX_FILE_SIZE_MB', 20) * 1024,

    'allowed_mime_types' => array_filter(explode(',', (string) env(
        'VAULT_ALLOWED_MIME_TYPES',
        'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,'
        .'audio/mpeg,audio/ogg,audio/wav,audio/x-wav,application/pdf,text/plain',
    ))),

    'download_link_ttl_minutes' => (int) env('VAULT_DOWNLOAD_LINK_TTL_MINUTES', 5),

    'per_page' => (int) env('VAULT_PER_PAGE', 15),
];
