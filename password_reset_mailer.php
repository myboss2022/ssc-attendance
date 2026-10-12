<?php
function password_reset_smtp_write($socket, $data)
{
    $written = 0;
    while ($written < strlen($data)) {
        $count = fwrite($socket, substr($data, $written));
        if ($count === false || $count === 0) {
            throw new RuntimeException('Unable to write to the SMTP server.');
        }
        $written += $count;
    }
}

function password_reset_smtp_read($socket)
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^(\d{3}) /', $line, $matches)) {
            return [(int)$matches[1], trim($response)];
        }
        if (!preg_match('/^\d{3}-/', $line)) {
            throw new RuntimeException('Invalid SMTP server response.');
        }
    }

    throw new RuntimeException('SMTP server closed the connection.');
}

function password_reset_smtp_command($socket, $command, $expectedCode)
{
    password_reset_smtp_write($socket, $command."\r\n");
    [$code, $response] = password_reset_smtp_read($socket);
    if ($code !== $expectedCode) {
        throw new RuntimeException('SMTP command failed with response '.$code.': '.$response);
    }
}

function send_password_reset_code($recipient, $code)
{
    $username = trim((string)getenv ('GMAIL_SMTP_USER'));
    $appPassword = preg_replace('/\s+/', '', (string)getenv('GMAIL_SMTP_APP_PASSWORD'));
    $fromName = trim((string)(getenv('MAIL_FROM_NAME') ?: 'SSC Attendance'));
    $host = trim((string)(getenv('GMAIL_SMTP_HOST') ?: 'smtp.gmail.com'));
    $port = (int)(getenv('GMAIL_SMTP_PORT') ?: 587);

    $invalidSettings = [];
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $invalidSettings[] = 'account email is invalid';
    }
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $invalidSettings[] = 'GMAIL_SMTP_USER is missing or is not a valid email address';
    }
    if ($appPassword === '') {
        $invalidSettings[] = 'GMAIL_SMTP_APP_PASSWORD is missing';
    }
    if (preg_match('/[\r\n]/', $fromName)) {
        $invalidSettings[] = 'MAIL_FROM_NAME contains invalid line breaks';
    }
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
        $invalidSettings[] = 'GMAIL_SMTP_HOST is invalid';
    }
    if ($port < 1 || $port > 65535) {
        $invalidSettings[] = 'GMAIL_SMTP_PORT is invalid';
    }
    if ($invalidSettings) {
        throw new RuntimeException('Invalid password reset settings: '.implode('; ', $invalidSettings).'.');
    }

    $errno = 0;
    $error = '';
    $socket = @stream_socket_client('tcp://'.$host.':'.$port, $errno, $error, 12, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('Could not connect to Gmail SMTP: '.$error);
    }
    stream_set_timeout($socket, 12);

    try {
        [$codeNumber] = password_reset_smtp_read($socket);
        if ($codeNumber !== 220) {
            throw new RuntimeException('Gmail SMTP did not accept the connection.');
        }

        password_reset_smtp_command($socket, 'EHLO localhost', 250);
        password_reset_smtp_command($socket, 'STARTTLS', 220);
        if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new RuntimeException('Could not establish a secure Gmail SMTP connection.');
        }

        password_reset_smtp_command($socket, 'EHLO localhost', 250);
        password_reset_smtp_command($socket, 'AUTH LOGIN', 334);
        password_reset_smtp_command($socket, base64_encode($username), 334);
        password_reset_smtp_command($socket, base64_encode($appPassword), 235);
        password_reset_smtp_command($socket, 'MAIL FROM:<'.$username.'>', 250);
        password_reset_smtp_command($socket, 'RCPT TO:<'.$recipient.'>', 250);
        password_reset_smtp_command($socket, 'DATA', 354);

        $encodedName = '=?UTF-8?B?'.base64_encode($fromName).'?=';
        $message = 'Date: '.gmdate('D, d M Y H:i:s').' +0000'."\r\n"
            .'From: '.$encodedName.' <'.$username.'>'."\r\n"
            .'To: <'.$recipient.'>'."\r\n"
            .'Subject: SSC Attendance password reset code'."\r\n"
            .'MIME-Version: 1.0'."\r\n"
            .'Content-Type: text/plain; charset=UTF-8'."\r\n"
            .'Content-Transfer-Encoding: 8bit'."\r\n"
            ."\r\n"
            .'Your SSC Attendance password reset code is: '.$code."\r\n"
            .'This code expires in 10 minutes. If you did not request a password reset, you can ignore this email.'."\r\n";
        $message = preg_replace('/(?m)^\./', '..', $message);
        password_reset_smtp_write($socket, $message.".\r\n");
        [$acceptedCode, $acceptedResponse] = password_reset_smtp_read($socket);
        if ($acceptedCode !== 250) {
            throw new RuntimeException('Gmail SMTP rejected the password reset email: '.$acceptedResponse);
        }
        password_reset_smtp_write($socket, "QUIT\r\n");
    } finally {
        fclose($socket);
    }
}
