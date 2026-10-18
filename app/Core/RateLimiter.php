<?php

namespace Core;

use RuntimeException;

class RateLimiter
{
    private const MAX_FAILURES = 5;
    private const COOLDOWN_SECONDS = 30;
    private const FAILURE_IDLE_SECONDS = 900;

    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir()
            . '/klasik-login-failures-' . hash('sha256', __DIR__) . '.json';
    }

    public function retryAfter(string $key): int
    {
        return $this->update($key, 'check');
    }

    /** The fifth failure starts a 30-second cooldown. */
    public function recordFailure(string $key): int
    {
        return $this->update($key, 'failure');
    }

    public function reset(string $key): void
    {
        $this->update($key, 'reset');
    }

    private function update(string $key, string $operation): int
    {
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
            $bucket = $buckets[$id] ?? ['count' => 0, 'expires' => $now + self::FAILURE_IDLE_SECONDS];
            $retryAfter = $bucket['count'] >= self::MAX_FAILURES ? $bucket['expires'] - $now : 0;
            if ($operation === 'reset') {
                unset($buckets[$id]);
                $retryAfter = 0;
            } elseif ($operation === 'failure' && $retryAfter === 0) {
                $bucket['count']++;
                $bucket['expires'] = $now + ($bucket['count'] >= self::MAX_FAILURES
                    ? self::COOLDOWN_SECONDS : self::FAILURE_IDLE_SECONDS);
                $buckets[$id] = $bucket;
                $retryAfter = $bucket['count'] >= self::MAX_FAILURES ? self::COOLDOWN_SECONDS : 0;
            }

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
