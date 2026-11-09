<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// If public/ was copied separately, point this at the private package directory.
$packageRoot = dirname(__DIR__);

try {
    require $packageRoot . '/vendor/autoload.php';
    require $packageRoot . '/src/Forms.php';
    require $packageRoot . '/src/Security.php';
    require $packageRoot . '/src/Mailer.php';
    require $packageRoot . '/src/Application.php';
    $configFile = $packageRoot . '/config/config.local.php';
    $config = require (is_file($configFile) ? $configFile : $packageRoot . '/config/config.example.php');
    $mailer = new \SCForms\Mailer($config);
    $app = new \SCForms\Application($config, $mailer->send(...));
    // Enforce actual body size even when Content-Length is missing or forged.
    if (strlen(file_get_contents('php://input', false, null, 0, 32769)) > 32768) {
        $_SERVER['CONTENT_LENGTH'] = 32769;
    }
    $response = $app->handle($_SERVER,$_GET,$_POST);
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $value) { header($name . ': ' . $value); }
    echo $response['body'];
} catch (\Throwable $error) {
    error_log('[SC Forms] Bootstrap failed; verify dependencies and private configuration.');
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['CODE'=>500,'mess'=>['Формы временно недоступны. Проверьте настройку сервера.'],'errors'=>(object)[]],JSON_UNESCAPED_UNICODE);
}
