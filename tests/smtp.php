<?php
declare(strict_types=1);
set_error_handler(static function(int $severity, string $message): never { throw new ErrorException($message, 0, $severity); });
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Mailer.php';
function smtpCheck(bool $ok,string $name): void { if (!$ok) { throw new RuntimeException('FAIL: '.$name); } echo "PASS: $name\n"; }
foreach (['accept','reject'] as $mode) {
    $base = __DIR__ . '/../var/smtp-' . bin2hex(random_bytes(5));
    $process = proc_open([PHP_BINARY,__DIR__.'/smtp-server.php',$base.'.ready',$base.'.json',$mode],[0=>['pipe','r'],1=>['file',$base.'.log','w'],2=>['file',$base.'.err','w']],$pipes);
    if (!is_resource($process)) { throw new RuntimeException('Could not start SMTP fixture'); }
    try {
        $until = microtime(true)+5;
        while (!is_file($base.'.ready') && microtime(true)<$until) { usleep(20000); }
        if (!is_file($base.'.ready')) { throw new RuntimeException('SMTP fixture not ready'); }
        $port = (int)substr(strrchr(file_get_contents($base.'.ready'),':'),1);
        $mailer = new \SCForms\Mailer(['smtp'=>['host'=>'127.0.0.1','port'=>$port,'encryption'=>'','auth'=>false,'timeout'=>3],'mail'=>['from_email'=>'sender@example.org','from_name'=>'SC Forms','to'=>['inbox@example.org']]]);
        $form = ['mailData'=>['subject'=>'Тест формы','emailFrom'=>'original@example.org','nameFrom'=>'Original','emailTo'=>'original-to@example.org']];
        $thrown=false;
        try { $mailer->send($form,['email'=>'visitor@example.org'],'<p>Тест &lt;script&gt;</p>'); } catch (Throwable) { $thrown=true; }
        smtpCheck($thrown === ($mode==='reject'),'SMTP '.$mode.' outcome');
        proc_close($process); $process=null;
        $result = json_decode(file_get_contents($base.'.json'),true);
        if ($mode==='accept') {
            smtpCheck(str_contains(implode('\n',$result['envelope']),'inbox@example.org'),'SMTP recipient override');
            smtpCheck(str_contains($result['message'],'Reply-To: visitor@example.org'),'SMTP Reply-To');
            smtpCheck(str_contains($result['message'],'From: SC Forms <sender@example.org>'),'SMTP From');
            smtpCheck(str_contains($result['message'],'text/html'),'SMTP HTML content');
            smtpCheck(str_contains(quoted_printable_decode($result['message']),'Тест &lt;script&gt;'),'SMTP UTF-8 escaped body');
        }
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach (glob($base.'.*') ?: [] as $file) { unlink($file); }
    }
}
