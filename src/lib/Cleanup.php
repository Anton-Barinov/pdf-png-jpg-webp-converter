<?php
declare(strict_types=1);

namespace App;

/**
 * Strict 5-minute Data Purge Daemon & Helper
 * Guarantees zero long-term retention of user data.
 */
class Cleanup
{
    public const TTL_SECONDS = 300; // 5 minutes

    /**
     * Purges all session directories older than TTL_SECONDS
     */
    public static function purgeExpired(string $sessionsBaseDir, int $ttlSeconds = self::TTL_SECONDS): int
    {
        if (!is_dir($sessionsBaseDir)) {
            return 0;
        }

        $purgedCount = 0;
        $now = time();
        $items = scandir($sessionsBaseDir);

        if ($items === false) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sessionPath = $sessionsBaseDir . '/' . $item;
            if (!is_dir($sessionPath)) {
                continue;
            }

            $metaFile = $sessionPath . '/meta.json';
            $isExpired = false;

            if (file_exists($metaFile)) {
                $meta = json_decode((string)file_get_contents($metaFile), true);
                if (is_array($meta) && isset($meta['created_at'])) {
                    if (($now - (int)$meta['created_at']) >= $ttlSeconds) {
                        $isExpired = true;
                    }
                }
            }

            // Fallback: check file modification time of directory
            if (!$isExpired && ($now - filemtime($sessionPath)) >= $ttlSeconds) {
                $isExpired = true;
            }

            if ($isExpired) {
                self::deleteDirRecursive($sessionPath);
                $purgedCount++;
            }
        }

        return $purgedCount;
    }

    /**
     * Instantly deletes a specific session
     */
    public static function deleteSession(string $sessionsBaseDir, string $sessionId): bool
    {
        // Enforce safe session ID (hex only)
        if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
            return false;
        }

        $sessionPath = $sessionsBaseDir . '/' . $sessionId;
        if (is_dir($sessionPath)) {
            return self::deleteDirRecursive($sessionPath);
        }

        return false;
    }

    public static function deleteDirRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::deleteDirRecursive($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }
}
