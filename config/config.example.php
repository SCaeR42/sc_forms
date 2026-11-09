<?php
declare(strict_types=1);
return [
    // Exact origin of the PHP endpoint, without path or trailing slash.
    'public_url' => getenv('SC_FORMS_PUBLIC_URL') ?: 'https://example.org',
    'allowed_origins' => [], // e.g. ['https://another-site.org']
    'secret' => getenv('SC_FORMS_SECRET') ?: '', // At least 32 random characters.
    'storage' => getenv('SC_FORMS_STORAGE') ?: dirname(__DIR__) . '/var/rate',
    'rate_limit' => 5,
    'rate_window' => 3600,
    'smtp' => [
        'host' => getenv('SC_SMTP_HOST') ?: '',
        'port' => (int)(getenv('SC_SMTP_PORT') ?: 587),
        'encryption' => getenv('SC_SMTP_ENCRYPTION') === false ? 'tls' : getenv('SC_SMTP_ENCRYPTION'), // tls (587), ssl (465); '' only for local tests.
        'auth' => getenv('SC_SMTP_AUTH') === false ? true : filter_var(getenv('SC_SMTP_AUTH'), FILTER_VALIDATE_BOOLEAN),
        'username' => getenv('SC_SMTP_USER') ?: '',
        'password' => getenv('SC_SMTP_PASSWORD') ?: '',
        'timeout' => 15,
    ],
    // Null preserves each form's original mailData. Usually set a mailbox permitted by your SMTP provider.
    'mail' => [
        'from_email' => getenv('SC_MAIL_FROM') ?: null,
        'from_name' => getenv('SC_MAIL_NAME') ?: null,
        'to' => getenv('SC_MAIL_TO') ?: null, // String or array of email addresses.
    ],
];
