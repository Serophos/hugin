<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/session_policy.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function start_worker(string $code, string $sessionDirectory): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $command = [
        PHP_BINARY,
        '-d', 'session.save_path=' . $sessionDirectory,
        '-d', 'session.use_cookies=0',
        '-d', 'session.cache_limiter=',
        '-r', $code,
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PHP session test worker.');
    }

    return [$process, $pipes];
}

function read_worker_line(array $worker, string $label): string
{
    [, $pipes] = $worker;
    $read = [$pipes[1]];
    $write = null;
    $except = null;
    $ready = stream_select($read, $write, $except, 5);
    if ($ready !== 1) {
        throw new RuntimeException($label . ' did not report its synchronized checkpoint.');
    }
    $line = fgets($pipes[1]);
    if ($line === false) {
        $error = trim((string)stream_get_contents($pipes[2]));
        throw new RuntimeException($label . ' exited before its checkpoint' . ($error !== '' ? ': ' . $error : '.'));
    }

    return trim($line);
}

function finish_worker(array $worker, string $label): void
{
    [$process, $pipes] = $worker;
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) fclose($pipe);
    }
    $exitCode = proc_close($process);
    assert_true($exitCode === 0, $label . ' exited with code ' . $exitCode . '.');
}

function holder_code(string $sessionId): string
{
    return sprintf(<<<'PHP'
session_name('hugin_session_policy_test');
session_id(%s);
session_start();
fwrite(STDOUT, "LOCKED\n");
fflush(STDOUT);
fgets(STDIN);
session_write_close();
fwrite(STDOUT, "RELEASED\n");
fflush(STDOUT);
PHP, var_export($sessionId, true));
}

function contender_code(string $sessionId, string $method, string $uri): string
{
    $policyFile = realpath(__DIR__ . '/../app/session_policy.php');
    assert_true(is_string($policyFile), 'Session policy file is missing.');

    return sprintf(<<<'PHP'
require_once %s;
$requiresSession = app_request_requires_session(%s, %s);
fwrite(STDOUT, $requiresSession ? "POLICY:SESSION\n" : "POLICY:SKIP\n");
fflush(STDOUT);
if ($requiresSession) {
    session_name('hugin_session_policy_test');
    session_id(%s);
    fwrite(STDOUT, "ATTEMPT\n");
    fflush(STDOUT);
    session_start();
}
fwrite(STDOUT, "ACQUIRED\n");
fflush(STDOUT);
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
PHP,
        var_export($policyFile, true),
        var_export($method, true),
        var_export($uri, true),
        var_export($sessionId, true),
    );
}

$policyCases = [
    ['POST', '/display/lobby/heartbeat', false],
    ['post', '/display/lobby-1_test/heartbeat?source=kiosk', false],
    ['POST', '/display/lobby/heartbeat?preview=0', false],
    ['POST', '/display/lobby/heartbeat?preview=false', false],
    ['POST', '/display/lobby/heartbeat?preview=1', true],
    ['POST', '/display/lobby/heartbeat?preview=true', true],
    ['POST', '/display/lobby/heartbeat?preview=yes', true],
    ['POST', '/display/lobby/heartbeat?preview=on', true],
    ['POST', '/display/lobby/heartbeat?preview%5B%5D=1', true],
    ['GET', '/display/lobby/heartbeat', true],
    ['POST', '/display/lobby/heartbeat/', true],
    ['POST', '/display/lobby/cache-readiness', true],
    ['POST', '/preview-slide/1/heartbeat', true],
    ['POST', '/display/lobby/heartbeat/extra', true],
    ['POST', '/admin/displays/1/heartbeat', true],
];
foreach ($policyCases as [$method, $uri, $expected]) {
    assert_true(
        app_request_requires_session($method, $uri) === $expected,
        sprintf('Unexpected session policy for %s %s.', $method, $uri),
    );
}

$indexSource = (string)file_get_contents(__DIR__ . '/../public/index.php');
$bootstrapSource = (string)file_get_contents(__DIR__ . '/../app/bootstrap.php');
$policyAssignment = strpos($indexSource, "\$GLOBALS['app_request_requires_session'] = app_request_requires_session(");
$bootstrapInclude = strpos($indexSource, "require_once __DIR__ . '/../app/bootstrap.php';");
assert_true($policyAssignment !== false && $bootstrapInclude !== false && $policyAssignment < $bootstrapInclude, 'Front controller must decide session policy before bootstrap.');
assert_true(strpos($bootstrapSource, 'if ($requestRequiresSession && session_status() === PHP_SESSION_NONE)') !== false, 'Bootstrap must guard session_start with the request policy.');

$sessionDirectory = sys_get_temp_dir() . '/hugin-session-policy-' . bin2hex(random_bytes(8));
assert_true(mkdir($sessionDirectory, 0700), 'Unable to create temporary session directory.');

try {
    $heartbeatSessionId = 'heartbeat-' . bin2hex(random_bytes(8));
    $heartbeatHolder = start_worker(holder_code($heartbeatSessionId), $sessionDirectory);
    assert_true(read_worker_line($heartbeatHolder, 'Heartbeat lock holder') === 'LOCKED', 'Heartbeat lock holder did not acquire the session.');

    $heartbeat = start_worker(
        contender_code($heartbeatSessionId, 'POST', '/display/lobby/heartbeat'),
        $sessionDirectory,
    );
    assert_true(read_worker_line($heartbeat, 'Heartbeat worker policy') === 'POLICY:SKIP', 'Exact heartbeat POST unexpectedly requested a session.');
    assert_true(read_worker_line($heartbeat, 'Heartbeat worker completion') === 'ACQUIRED', 'Heartbeat did not complete while the shared session remained locked.');
    finish_worker($heartbeat, 'Heartbeat worker');

    fwrite($heartbeatHolder[1][0], "release\n");
    fflush($heartbeatHolder[1][0]);
    assert_true(read_worker_line($heartbeatHolder, 'Heartbeat lock holder release') === 'RELEASED', 'Heartbeat lock holder did not release cleanly.');
    finish_worker($heartbeatHolder, 'Heartbeat lock holder');

    $adminSessionId = 'admin-' . bin2hex(random_bytes(8));
    $adminHolder = start_worker(holder_code($adminSessionId), $sessionDirectory);
    assert_true(read_worker_line($adminHolder, 'Admin lock holder') === 'LOCKED', 'Admin lock holder did not acquire the session.');

    $admin = start_worker(
        contender_code($adminSessionId, 'GET', '/admin'),
        $sessionDirectory,
    );
    assert_true(read_worker_line($admin, 'Admin worker policy') === 'POLICY:SESSION', 'Admin route unexpectedly skipped its session.');
    assert_true(read_worker_line($admin, 'Admin worker attempt') === 'ATTEMPT', 'Admin worker did not attempt to acquire the shared session.');
    $adminRead = [$admin[1][1]];
    $adminWrite = null;
    $adminExcept = null;
    assert_true(stream_select($adminRead, $adminWrite, $adminExcept, 0, 0) === 0, 'Admin route acquired the session before its holder released it.');

    // Release is an explicit protocol event. The admin worker can only print
    // ACQUIRED after PHP obtains the lock released at this checkpoint.
    fwrite($adminHolder[1][0], "release\n");
    fflush($adminHolder[1][0]);
    assert_true(read_worker_line($adminHolder, 'Admin lock holder release') === 'RELEASED', 'Admin lock holder did not release cleanly.');
    assert_true(read_worker_line($admin, 'Admin worker acquisition') === 'ACQUIRED', 'Admin worker did not acquire the session after release.');
    finish_worker($admin, 'Admin worker');
    finish_worker($adminHolder, 'Admin lock holder');
} finally {
    foreach (glob($sessionDirectory . '/*') ?: [] as $sessionFile) {
        if (is_file($sessionFile)) unlink($sessionFile);
    }
    rmdir($sessionDirectory);
}

fwrite(STDOUT, "PASS exact heartbeat session bypass and synchronized session-lock ordering\n");
