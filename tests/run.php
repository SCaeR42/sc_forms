<?php
declare(strict_types=1);
require __DIR__ . '/../src/Forms.php';
require __DIR__ . '/../src/Security.php';
use SCForms\Forms;
use SCForms\Security;
function check(bool $ok, string $name): void { if (!$ok) { throw new RuntimeException('FAIL: ' . $name); } echo "PASS: $name\n"; }
$forms = new Forms(__DIR__ . '/../config/forms');
$form = $forms->get('consultation');
$valid = ['certificate'=>'ABC12','email'=>'test@example.org','comment'=>'Вопрос','tos_soglasie'=>'да','tos_privacy_policy'=>'да'];
check($forms->validate($form, $valid) === [], 'valid consultation');
check(count($forms->validate($form, [])) === 5, 'all required fields');
check(isset($forms->validate($form, array_replace($valid, ['email'=>'wrong']))['email']), 'invalid email');
check(isset($forms->validate($form, array_replace($valid, ['email'=>['a']]))['email']), 'array rejected');
check(isset($forms->validate($form, array_replace($valid, ['tos_soglasie'=>'no']))['tos_soglasie']), 'exact consent value');
check(isset($forms->validate($form, array_replace($valid, ['comment'=>str_repeat('x',10001)]))['comment']), 'long comment rejected');
check(isset($forms->validate($form, array_replace($valid, ['comment'=>'Я']))['comment']), 'comment requires two Unicode characters');
check(isset($forms->validate($form, array_replace($valid, ['certificate'=>'1']))['certificate']), 'short certificate rejected');
check(isset($forms->validate($form, array_replace($valid, ['comment'=>"\xFF"]))['comment']), 'invalid UTF-8 rejected');
try { $forms->get('../config'); check(false, 'unknown form rejected'); } catch (InvalidArgumentException) { check(true,'unknown form rejected'); }
$html = $forms->render('consultation', 'token');
check(str_contains($html, 'name="scform[certificate]"') && str_contains($html, 'name="token"'), 'render fields and token');
check(str_contains($forms->mailBody($form, array_replace($valid,['comment'=>'<script>bad</script>']), '127.0.0.1','https://example.org'), '&lt;script&gt;'), 'mail escapes HTML');
foreach (['requestdemo','becomeclient','partner','feedback'] as $name) { check(str_contains($forms->render($name,'t'), 'scform[email]'), 'render ' . $name); }
$dir = __DIR__ . '/../var/scforms-tests-' . bin2hex(random_bytes(6));
$security = new Security(str_repeat('s',32), $dir, 2, 3600);
$token = $security->token('consultation', 1000);
check($security->verify($token,'consultation',1001), 'valid token');
check(!$security->verify($token,'feedback',1001), 'token bound to form');
check(!$security->verify($token . 'x','consultation',1001), 'tampered token rejected');
check(!$security->verify($token,'consultation',4601), 'expired token rejected');
check(!$security->verify($token,'consultation',999), 'future token rejected');
check($security->allow('127.0.0.1',1000) && $security->allow('127.0.0.1',1001) && !$security->allow('127.0.0.1',1002), 'rate limit');
check($security->allow('127.0.0.1',4601), 'rate window expires');
check($security->originAllowed('https://example.org',['https://example.org']), 'allowed origin');
check(!$security->originAllowed('https://evil.org',['https://example.org']), 'foreign origin rejected');
check(!$security->originAllowed('null',['https://example.org']), 'null origin rejected');
foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); } rmdir($dir);
