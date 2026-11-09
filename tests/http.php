<?php

declare(strict_types=1);
require __DIR__ . '/run.php';
require __DIR__ . '/../src/Application.php';

use SCForms\Application;

$dir = __DIR__ . '/../var/http-' . bin2hex(random_bytes(6));
$config = ['public_url' => 'http://localhost:8099', 'allowed_origins' => ['https://other.example'], 'secret' => str_repeat('x', 32), 'storage' => $dir, 'rate_limit' => 20, 'rate_window' => 3600];
$sent = 0;
$app = new Application($config, function (array $form, array $data, string $body) use (&$sent) {
    $sent++;
});
$server = ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1'];
$r = $app->handle($server, ['form' => 'consultation'], []);
check($r['status'] === 200 && str_contains($r['body'], 'scf-form'), 'HTTP render');
check(str_contains($r['headers']['Cache-Control'], 'no-store'), 'token response not cached');
preg_match('/name="token" value="([^"]+)"/', $r['body'], $m);
$post = ['form' => 'consultation', 'token' => $m[1], 'send' => 'Y', 'scform' => $valid];
$server['REQUEST_METHOD'] = 'POST';
$server['HTTP_ORIGIN'] = 'http://localhost:8099';
$r = $app->handle($server, [], $post);
check($r['status'] === 200 && $sent === 1 && json_decode($r['body'], true)['CODE'] === 200, 'HTTP send');
$r = $app->handle($server, [], array_replace($post, ['scform' => []]));
check($r['status'] === 422 && $sent === 1 && isset(json_decode($r['body'], true)['errors']['email']), 'HTTP validation does not send');
$r = $app->handle($server, [], array_replace($post, ['token' => 'bad']));
check($r['status'] === 403 && $sent === 1, 'HTTP invalid token');
$r = $app->handle($server, [], array_replace($post, ['website' => 'robot']));
check($r['status'] === 422 && $sent === 1, 'HTTP honeypot');
$r = $app->handle($server, [], array_replace($post, ['scform' => 'bad']));
check($r['status'] === 400 && $sent === 1, 'HTTP malformed data');
$r = $app->handle($server, [], array_replace($post, ['form' => ['consultation']]));
check($r['status'] === 400, 'HTTP array form');
$r = $app->handle($server, [], array_replace($post, ['form' => '../../secret']));
check($r['status'] === 404, 'HTTP unknown form');
$server['HTTP_ORIGIN'] = 'https://evil.example';
check($app->handle($server, [], $post)['status'] === 403 && $sent === 1, 'HTTP foreign origin');
$server['HTTP_ORIGIN'] = 'https://other.example';
$server['REQUEST_METHOD'] = 'OPTIONS';
$r = $app->handle($server, [], []);
check($r['status'] === 204 && $r['headers']['Access-Control-Allow-Origin'] === 'https://other.example', 'HTTP CORS preflight');
$server['REQUEST_METHOD'] = 'POST';
$server['CONTENT_LENGTH'] = 40000;
check($app->handle($server, [], $post)['status'] === 413, 'HTTP request size');
unset($server['CONTENT_LENGTH']);
$server['REQUEST_METHOD'] = 'PUT';
check($app->handle($server, [], [])['status'] === 405, 'HTTP method');
$server['REQUEST_METHOD'] = 'GET';
check($app->handle($server, ['form' => 'feedback', 'send' => 'Y'], [])['status'] === 405, 'HTTP GET never sends');
$server['REQUEST_METHOD'] = 'POST';
$broken = new Application($config, function () {
    throw new RuntimeException('private password');
});
$r = $broken->handle($server, [], $post);
check($r['status'] === 500 && !str_contains($r['body'], 'private password'), 'HTTP SMTP error hidden');
foreach (glob($dir . '/*') ?: [] as $file) {
    unlink($file);
}
rmdir($dir);
