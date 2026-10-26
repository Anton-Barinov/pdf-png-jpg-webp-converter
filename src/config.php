<?php
declare(strict_types=1);

/**
 * Universal Converter Configuration
 * Supports environment variables with sensible defaults.
 */
return [
    // Application Name
    'app_name' => getenv('APP_NAME') ?: 'Конвертер файлов',

    // Storage directory for temporary upload and output sessions
    'storage_dir' => getenv('STORAGE_DIR') ?: (__DIR__ . '/../private/storage/sessions'),

    // File retention TTL in seconds (default: 300 seconds = 5 minutes)
    'ttl_seconds' => (int)(getenv('TTL_SECONDS') ?: 300),

    // Max file size in megabytes
    'max_file_size_mb' => (int)(getenv('MAX_FILE_SIZE_MB') ?: 100),

    // Max files per session
    'max_session_files' => (int)(getenv('MAX_SESSION_FILES') ?: 50),

    // Default image quality (10-100)
    'default_quality' => (int)(getenv('DEFAULT_QUALITY') ?: 90),
];
