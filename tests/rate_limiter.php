<?php

require dirname(__DIR__) . '/app/Core/RateLimiter.php';

use Core\RateLimiter;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$storage = tempnam(sys_get_temp_dir(), 'klasik-rate-test-');
$originalRender = getenv('RENDER');
$originalServer = $_SERVER;

try {
    $limiter = new RateLimiter($storage);
    for ($i = 0; $i < 5; $i++) {
        check($limiter->attempt('login:client-a') === 0, 'Five attempts must be allowed.');
    }
    $wait = $limiter->attempt('login:client-a');
    check($wait > 0 && $wait <= 60, 'Sixth attempt must be blocked with a wait time.');
    check((new RateLimiter($storage))->attempt('login:client-a') > 0, 'A new instance must not reset the counter.');
    check($limiter->attempt('login:client-b') === 0, 'Different clients must have separate limits.');

    $buckets = json_decode(file_get_contents($storage), true);
    $id = hash('sha256', 'login:client-a');
    $expires = $buckets[$id]['expires'];
    $limiter->attempt('login:client-a');
    $unchanged = json_decode(file_get_contents($storage), true);
    check($unchanged[$id]['expires'] === $expires, 'Blocked requests must not extend the wait.');
    $buckets[$id]['expires'] = time() - 1;
    file_put_contents($storage, json_encode($buckets));
    check($limiter->attempt('login:client-a') === 0, 'Attempts must resume after expiry.');

    putenv('RENDER');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
    $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1';
    check(RateLimiter::clientIp() === '192.0.2.1', 'Local requests must ignore forged proxy headers.');
    putenv('RENDER=true');
    check(RateLimiter::clientIp() === '198.51.100.1', 'Render must identify the client instead of its proxy.');
    $_SERVER['HTTP_CF_CONNECTING_IP'] = 'invalid';
    check(RateLimiter::clientIp() === '192.0.2.1', 'Invalid proxy IP must fall back to the connection IP.');
    $_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:db8:0:0:0:0:0:1';
    check(RateLimiter::clientIp() === '2001:db8::1', 'Equivalent IPv6 addresses must use one counter.');

    foreach ([[0, 60], [5, 0]] as [$limit, $seconds]) {
        try {
            $limiter->attempt('invalid', $limit, $seconds);
            throw new RuntimeException('Invalid limits must be rejected.');
        } catch (InvalidArgumentException $expected) {
        }
    }

    foreach (['invalid json', '{"bad":null}', '{"bad":{"count":"5","expires":9999999999}}'] as $invalid) {
        file_put_contents($storage, $invalid);
        try {
            $limiter->attempt('login:client-a');
            throw new LogicException('Corrupt storage must not disable the limiter.');
        } catch (RuntimeException $expected) {
            check($expected->getMessage() === 'Unable to read rate limit storage.', 'Storage failure must be reported.');
        }
    }

    echo "Rate limiter checks passed.\n";
} finally {
    unlink($storage);
    $_SERVER = $originalServer;
    $originalRender === false ? putenv('RENDER') : putenv('RENDER=' . $originalRender);
}
