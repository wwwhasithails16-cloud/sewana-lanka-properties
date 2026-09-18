<?php

function smtpReadReply($socket): array
{
    $message = '';

    while (($line = fgets($socket, 515)) !== false) {
        $message .= $line;

        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    if ($message === '') {
        throw new RuntimeException('The SMTP server closed the connection.');
    }

    return [(int) substr($message, 0, 3), trim($message)];
}

function smtpExpect($socket, array $acceptedCodes): string
{
    [$code, $message] = smtpReadReply($socket);

    if (!in_array($code, $acceptedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . $message);
    }

    return $message;
}

function smtpCommand($socket, string $command, array $acceptedCodes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Could not write to the SMTP server.');
    }

    return smtpExpect($socket, $acceptedCodes);
}

function smtpSafeHeader(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function smtpConfigured(array $config): bool
{
    $password = trim((string) ($config['smtp_password'] ?? ''));

    return !empty($config['smtp_host'])
        && !empty($config['smtp_username'])
        && filter_var($config['from_email'] ?? '', FILTER_VALIDATE_EMAIL)
        && $password !== ''
        && $password !== 'PASTE_GMAIL_APP_PASSWORD_HERE';
}

function sendPasswordResetEmail(array $config, string $recipient, string $resetUrl): void
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('The recipient email address is invalid.');
    }

    $host = trim((string) ($config['smtp_host'] ?? ''));
    $port = (int) ($config['smtp_port'] ?? 587);
    $encryption = strtolower(trim((string) ($config['smtp_encryption'] ?? 'tls')));
    $username = (string) ($config['smtp_username'] ?? '');
    $password = (string) ($config['smtp_password'] ?? '');
    $fromEmail = smtpSafeHeader((string) ($config['from_email'] ?? ''));
    $fromName = smtpSafeHeader((string) ($config['from_name'] ?? 'Sewana Lanka'));

    if ($host === '' || $port < 1 || $port > 65535 || !in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        throw new RuntimeException('The SMTP configuration is invalid.');
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The sender email address is invalid.');
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $host,
        ],
    ]);

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        20,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        throw new RuntimeException('Could not connect to the SMTP server: ' . $errorMessage);
    }

    stream_set_timeout($socket, 20);

    try {
        smtpExpect($socket, [220]);
        $clientName = gethostname() ?: 'localhost';
        smtpCommand($socket, 'EHLO ' . preg_replace('/[^a-z0-9.\-]/i', '', $clientName), [250]);

        if ($encryption === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start an encrypted SMTP connection.');
            }

            smtpCommand($socket, 'EHLO ' . preg_replace('/[^a-z0-9.\-]/i', '', $clientName), [250]);
        }

        if ($username !== '') {
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode($username), [334]);
            smtpCommand($socket, base64_encode($password), [235]);
        }

        smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $subject = 'Reset your Sewana Lanka administrator password';
        $body = "A password reset was requested for your Sewana Lanka administrator account.\n\n"
            . "Open this secure link to choose a new password:\n"
            . $resetUrl . "\n\n"
            . "This link expires soon and can be used only once.\n"
            . "If you did not request this change, you can ignore this email.";

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'To: <' . smtpSafeHeader($recipient) . '>',
            'Subject: ' . $subject,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^a-z0-9.\-]/i', '', $host) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = preg_replace('/^\./m', '..', $body);
        $message = implode("\r\n", $headers)
            . "\r\n\r\n"
            . str_replace("\n", "\r\n", $body)
            . "\r\n.";

        smtpCommand($socket, $message, [250]);
        smtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
