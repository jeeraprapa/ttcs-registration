<?php

declare(strict_types=1);

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mime\Email;

require dirname(__DIR__).'/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the terminal.');
}

$password = '1stLogin@';
$recipient = $argv[1] ?? 'jeeraprapa.h@gmail.com';

if (! is_string($password) || $password === '') {
    fwrite(STDERR, "SMTP password is missing.\n");
    exit(1);
}

if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Invalid recipient email address.\n");
    exit(1);
}

try {
    $transport = (new EsmtpTransportFactory)->create(new Dsn(
        scheme: 'smtp',
        host: 'smtp.office365.com',
        user: 'central@citydynamic.com',
        password: $password,
        port: 587,
        options: ['require_tls' => true],
    ));

    $email = (new Email)
        ->from('central@citydynamic.com')
        ->to($recipient)
        ->subject('[TEST] Upper House - standalone PHP email')
        ->text('This is a test email sent from standalone PHP. No booking has been created.')
        ->html('<h1>Upper House Residences</h1><p>This is a test email sent from standalone PHP.</p><p>No booking has been created.</p>');

    (new Mailer($transport))->send($email);

    fwrite(STDOUT, "SMTP server accepted the message. Please check the recipient inbox and spam folder.\n");
} catch (Throwable $exception) {
    $detail = str_replace($password, '[redacted]', $exception->getMessage());
    fwrite(STDERR, 'Email failed: '.$detail.PHP_EOL);
    exit(1);
}
