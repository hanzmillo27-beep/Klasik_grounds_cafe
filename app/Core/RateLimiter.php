<?php

namespace Core;

use RuntimeException;

class RateLimiter
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir()
            . '/klasik-rate-limits-' . hash('sha256', __DIR__) . '.json';
    }

    /** Count an attempt and return the wait in seconds, or zero when allowed. */
    public function attempt(string $key, int $limit = 5, int $windowSeconds = 60): int
    {
        if ($limit < 1 || $windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit and window must be positive.');
        }

        $file = @fopen($this->storagePath, 'c+');
        if ($file === false) {
            throw new RuntimeException('Unable to open rate limit storage.');
        }

        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('Unable to lock rate limit storage.');
            }

            $now = time();
            $contents = stream_get_contents($file);
            if ($contents === false) {
                throw new RuntimeException('Unable to read rate limit storage.');
            }
            $buckets = $contents === '' ? [] : json_decode($contents, true);
            if (!is_array($buckets)) {
                throw new RuntimeException('Unable to read rate limit storage.');
            }

            // One file keeps expired clients from leaving files behind forever.
            foreach ($buckets as $id => $bucket) {
                if (!is_array($bucket) || !isset($bucket['count'], $bucket['expires'])
                    || !is_int($bucket['count']) || $bucket['count'] < 0
                    || !is_int($bucket['expires']) || $bucket['expires'] <= 0) {
                    throw new RuntimeException('Unable to read rate limit storage.');
                }
                if ($bucket['expires'] <= $now) {
                    unset($buckets[$id]);
                }
            }

            $id = hash('sha256', $key);
            $bucket = $buckets[$id] ?? ['count' => 0, 'expires' => $now + $windowSeconds];
            $retryAfter = $bucket['count'] >= $limit ? $bucket['expires'] - $now : 0;
            if ($retryAfter === 0) {
                $bucket['count']++;
            }
            $buckets[$id] = $bucket;

            $encoded = json_encode($buckets, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
                throw new RuntimeException('Unable to save rate limit storage.');
            }

            return $retryAfter;
        } finally {
            // Closing also releases the exclusive lock, including on errors.
            fclose($file);
        }
    }

    public static function clientIp(): string
    {
        // Render's public ingress passes through Cloudflare. Local requests must
        // never be allowed to choose their own IP through a forwarded header.
        if (getenv('RENDER') === 'true') {
            $ip = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return inet_ntop(inet_pton($ip));
            }
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : 'unknown';
    }
}
