<?php
/**
 * Tiny SMTP mailer (no Composer needed). Supports STARTTLS (587) and SSL (465) with AUTH LOGIN.
 * send_mail() returns [bool ok, string message].
 */
require_once __DIR__ . '/../config/mail.php';

function mail_dev_mode(): bool { return MAIL_USER === ''; }

function mail_log(string $file, string $text): void {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/' . $file, '[' . date('Y-m-d H:i:s') . "] " . $text . "\n\n", FILE_APPEND | LOCK_EX);
}

function smtp_read($fp): string {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $data;
}
function smtp_cmd($fp, string $cmd, array $ok): string {
    if ($cmd !== '') fwrite($fp, $cmd . "\r\n");
    $r = smtp_read($fp);
    if (!in_array((int) substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP said: ' . trim($r));
    return $r;
}

function send_mail(string $to, string $subject, string $html, string $text = ''): array {
    $to = str_replace(["\r", "\n"], '', trim($to));
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Invalid recipient address.'];
    if ($text === '') $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $html))));

    if (mail_dev_mode()) {
        mail_log('mail-log.txt', "DEV MODE (not sent)\nTo: $to\nSubject: $subject\n\n$text");
        return [true, 'Dev mode: email written to storage/mail-log.txt'];
    }

    $from = MAIL_FROM !== '' ? MAIL_FROM : MAIL_USER;
    $fp = null;
    try {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => MAIL_VERIFY_SSL, 'verify_peer_name' => MAIL_VERIFY_SSL, 'peer_name' => MAIL_HOST]]);
        $remote = (MAIL_SECURE === 'ssl' ? 'ssl://' : 'tcp://') . MAIL_HOST . ':' . MAIL_PORT;
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) throw new RuntimeException("Cannot connect to " . MAIL_HOST . ":" . MAIL_PORT . " ($errstr). Check internet/firewall and the host/port.");
        stream_set_timeout($fp, 20);

        smtp_cmd($fp, '', [220]);
        smtp_cmd($fp, 'EHLO localhost', [250]);
        if (MAIL_SECURE === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS handshake failed (see README_EMAIL.txt: openssl.cafile).');
            smtp_cmd($fp, 'EHLO localhost', [250]);
        }
        smtp_cmd($fp, 'AUTH LOGIN', [334]);
        smtp_cmd($fp, base64_encode(MAIL_USER), [334]);
        smtp_cmd($fp, base64_encode(MAIL_PASS), [235]);
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $b = 'b' . bin2hex(random_bytes(8));
        $domain = substr(strrchr($from, '@'), 1) ?: 'localhost';
        $msg = implode("\r\n", [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
            '',
            '--' . $b, 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64', '', rtrim(chunk_split(base64_encode($text), 76, "\r\n")),
            '--' . $b, 'Content-Type: text/html; charset=UTF-8', 'Content-Transfer-Encoding: base64', '', rtrim(chunk_split(base64_encode($html), 76, "\r\n")),
            '--' . $b . '--', '',
        ]);
        fwrite($fp, $msg . "\r\n.\r\n");
        $r = smtp_read($fp);
        if ((int) substr($r, 0, 3) !== 250) throw new RuntimeException('SMTP said: ' . trim($r));
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return [true, 'Sent'];
    } catch (Throwable $ex) {
        if (is_resource($fp)) @fclose($fp);
        mail_log('mail-error.txt', 'To: ' . $to . ' | ' . $ex->getMessage());
        return [false, $ex->getMessage()];
    }
}

/** Branded email with a big code. Returns [html, text]. */
function email_otp_template(string $name, string $otp, int $minutes): array {
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;background:#e9eef5;padding:28px">'
      . '<div style="max-width:480px;margin:auto;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #d6deea">'
      . '<div style="background:#14213d;color:#fff;padding:18px 24px;font-size:20px;font-weight:700">Queue<span style="color:#ffc83d">&amp;</span>Care</div>'
      . '<div style="padding:26px 24px;color:#14213d">'
      . '<p style="margin:0 0 8px;font-size:16px">Hi ' . $n . ',</p>'
      . '<p style="margin:0 0 18px;color:#5c6b84">Use this code to reset your password:</p>'
      . '<div style="background:#ffc83d;border-radius:14px;text-align:center;padding:18px;font-size:34px;font-weight:800;letter-spacing:10px">' . $otp . '</div>'
      . '<p style="margin:18px 0 0;color:#5c6b84;font-size:14px">This code expires in ' . $minutes . ' minutes and can be used once. Never share it with anyone, including clinic staff.</p>'
      . '<p style="margin:14px 0 0;color:#5c6b84;font-size:14px">Did not ask for this? You can ignore this email. Your password stays the same.</p>'
      . '</div></div></div>';
    $text = "Hi $name,\n\nYour Queue&Care password reset code is: $otp\nIt expires in $minutes minutes and can be used once. Never share it with anyone.\n\nDid not ask for this? Ignore this email.";
    return [$html, $text];
}
