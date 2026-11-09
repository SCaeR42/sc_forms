<?php
declare(strict_types=1);
// One-message loopback SMTP fixture. Never forwards mail.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { throw new RuntimeException($error); }
file_put_contents($argv[1], stream_socket_get_name($server, false));
$client = stream_socket_accept($server, 15);
if (!$client) { exit(2); }
stream_set_timeout($client, 15);
fwrite($client, "220 localhost test SMTP\r\n");
$message = ''; $envelope = []; $data = false;
while (($line = fgets($client)) !== false) {
    if ($data) {
        if ($line === ".\r\n") { $data=false; fwrite($client,"250 queued\r\n"); }
        else { $message .= str_starts_with($line,'..') ? substr($line,1) : $line; }
        continue;
    }
    $command = strtoupper(strtok($line, ' '));
    if ($command === 'EHLO' || $command === 'HELO') { fwrite($client,"250 localhost\r\n"); }
    elseif ($command === 'MAIL') { $envelope[] = trim($line); fwrite($client,"250 OK\r\n"); }
    elseif ($command === 'RCPT') { $envelope[] = trim($line); fwrite($client, ($argv[3] ?? '') === 'reject' ? "550 rejected for test\r\n" : "250 OK\r\n"); }
    elseif (trim($command) === 'DATA') { $data=true; fwrite($client,"354 end with dot\r\n"); }
    elseif (trim($command) === 'QUIT') { fwrite($client,"221 bye\r\n"); break; }
    else { fwrite($client,"250 OK\r\n"); }
}
file_put_contents($argv[2], json_encode(['envelope'=>$envelope,'message'=>$message]));
fclose($client); fclose($server);
