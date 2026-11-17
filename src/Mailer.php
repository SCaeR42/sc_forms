<?php
declare(strict_types=1);
namespace SCForms;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public function __construct(private array $config) {}

    public function send(array $form, array $data, string $body): void
    {
        $smtp = $this->config['smtp'];
        $settings = $this->config['mail'] ?? [];
        $original = $form['mailData'];
        $encryption = $smtp['encryption'] ?? 'tls';
        $host = $smtp['host'] ?? '';
        if ($host === '' || preg_match('/[;\s\/]/', $host) || !in_array($encryption, ['tls','ssl',''],true)) {
            throw new \RuntimeException('Некорректные настройки SMTP');
        }
        if ($encryption === '' && !in_array($host, ['127.0.0.1','localhost','::1'],true)) {
            throw new \RuntimeException('Незашифрованный SMTP разрешён только для локального тестирования');
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->Host = $host;
        $mail->Port = (int)($smtp['port'] ?? 587);
        $mail->SMTPSecure = $encryption;
        $mail->SMTPAutoTLS = $encryption !== '';
        $mail->SMTPAuth = (bool)($smtp['auth'] ?? true);
        $mail->Username = $smtp['username'] ?? '';
        $mail->Password = $smtp['password'] ?? '';
        $mail->Timeout = max(1, min(60, (int)($smtp['timeout'] ?? 15)));
        $mail->getSMTPInstance()->Timelimit = $mail->Timeout;
        $mail->SMTPDebug = 0;
        $mail->XMailer = '';
        $mail->setFrom($settings['from_email'] ?? $original['emailFrom'], $settings['from_name'] ?? $original['nameFrom']);
        $recipients = $settings['to'] ?? $original['emailTo'];
        foreach (is_array($recipients) ? $recipients : [$recipients] as $recipient) { $mail->addAddress($recipient); }
        $copyRecipient = trim($settings['cc'] ?? '');
        if ($copyRecipient !== '') {
            $mail->addCC($copyRecipient);
        }
        if (isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) { $mail->addReplyTo($data['email']); }
        $mail->Subject = $original['subject'];
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = html_entity_decode(strip_tags(str_replace(['</p>','<br>'], ["\n\n","\n"], $body)), ENT_QUOTES | ENT_HTML5,'UTF-8');
        $mail->send();
    }
}
