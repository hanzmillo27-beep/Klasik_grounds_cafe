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
    for ($i = 0; $i < 10; $i++) {
        check($limiter->retryAfter('login:client-a') === 0, 'Checking a login must not count as a failure.');
    }
    for ($i = 0; $i < 4; $i++) {
        check($limiter->recordFailure('login:client-a') === 0, 'The first four failures must not start a cooldown.');
    }
    $beforeFifth = time();
    check($limiter->recordFailure('login:client-a') === 30, 'The fifth failure must start a 30-second cooldown.');
    $wait = $limiter->retryAfter('login:client-a');
    check($wait > 0 && $wait <= 30, 'Requests during the cooldown must be blocked.');
    check((new RateLimiter($storage))->retryAfter('login:client-a') > 0, 'A new instance must not reset the counter.');
    check($limiter->retryAfter('login:client-b') === 0, 'Different clients must have separate limits.');

    $buckets = json_decode(file_get_contents($storage), true);
    $id = hash('sha256', 'login:client-a');
    $expires = $buckets[$id]['expires'];
    check($expires >= $beforeFifth + 30 && $expires <= time() + 30, 'Cooldown must start at the fifth failure.');
    $limiter->recordFailure('login:client-a');
    $unchanged = json_decode(file_get_contents($storage), true);
    check($unchanged[$id]['expires'] === $expires, 'Blocked requests must not extend the wait.');
    $buckets[$id]['expires'] = time() - 1;
    file_put_contents($storage, json_encode($buckets));
    check($limiter->retryAfter('login:client-a') === 0, 'Attempts must resume after expiry.');
    check($limiter->recordFailure('login:client-a') === 0, 'Cooldown expiry must reset the failure count.');
    $limiter->reset('login:client-a');
    check($limiter->retryAfter('login:client-a') === 0, 'A successful login must clear failures.');
    for ($i = 0; $i < 4; $i++) {
        check($limiter->recordFailure('login:client-a') === 0, 'Failures before success must not carry over.');
    }

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

    foreach (['invalid json', '{"bad":null}', '{"bad":{"count":"5","expires":9999999999}}'] as $invalid) {
        file_put_contents($storage, $invalid);
        try {
            $limiter->retryAfter('login:client-a');
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
