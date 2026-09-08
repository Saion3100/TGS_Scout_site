<?php
declare(strict_types=1);

/**
 * Minimal authenticated SMTP client for the contact form.
 *
 * Scope on purpose: plain-text UTF-8 messages, submission over STARTTLS (587)
 * or implicit TLS (465), AUTH LOGIN. Header values are stripped of CR/LF so a
 * form field can never inject extra headers. Not a general-purpose mailer.
 */
final class SmtpMailerException extends RuntimeException
{
}

final class SmtpMailer
{
    /** @var resource|null */
    private $conn = null;

    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $secure;
    private int $timeout;

    public function __construct(
        string $host,
        int $port = 587,
        string $username = '',
        string $password = '',
        /** 'tls' = STARTTLS, 'ssl' = implicit TLS, '' = plaintext */
        string $secure = 'tls',
        int $timeout = 15
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->secure = $secure;
        $this->timeout = $timeout;
    }

    /**
     * @param list<string> $recipients
     */
    public function send(
        string $fromEmail,
        string $fromName,
        array $recipients,
        string $subject,
        string $body,
        ?string $replyTo = null
    ): void {
        $fromEmail = $this->sanitizeHeaderValue($fromEmail);
        $recipients = array_values(array_filter(
            array_map([$this, 'sanitizeHeaderValue'], $recipients),
            static fn(string $address): bool => $address !== ''
        ));
        if ($fromEmail === '' || $recipients === []) {
            throw new SmtpMailerException('Missing sender or recipients.');
        }

        $this->open();
        try {
            $this->hello();
            if ($this->secure === 'tls') {
                $this->command('STARTTLS', [220]);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!@stream_socket_enable_crypto($this->conn, true, $crypto)) {
                    throw new SmtpMailerException('Failed to negotiate TLS.');
                }
                $this->hello();
            }
            if ($this->username !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->username), [334]);
                $this->command(base64_encode($this->password), [235]);
            }
            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            foreach ($recipients as $recipient) {
                $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
            }
            $this->command('DATA', [354]);
            $this->writeData($this->buildMessage($fromEmail, $fromName, $recipients, $subject, $body, $replyTo));
            $this->command('.', [250]);
            $this->command('QUIT', [221], true);
        } finally {
            $this->close();
        }
    }

    private function open(): void
    {
        $errno = 0;
        $errstr = '';
        $target = ($this->secure === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $conn = @stream_socket_client($target, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        if ($conn === false) {
            throw new SmtpMailerException(sprintf('Connection to SMTP host failed (%d).', $errno));
        }
        stream_set_timeout($conn, $this->timeout);
        $this->conn = $conn;
        $this->expect([220]);
    }

    private function hello(): void
    {
        $name = gethostname() ?: 'localhost';
        $this->command('EHLO ' . $name, [250]);
    }

    /**
     * @param list<int> $expected
     */
    private function command(string $line, array $expected, bool $ignoreResponse = false): string
    {
        fwrite($this->conn, $line . "\r\n");
        return $ignoreResponse ? '' : $this->expect($expected);
    }

    /**
     * @param list<int> $codes
     */
    private function expect(array $codes): string
    {
        $data = '';
        while (($line = fgets($this->conn, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($data === '') {
            throw new SmtpMailerException('No reply from SMTP host (timeout).');
        }
        $code = (int) substr($data, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new SmtpMailerException('Unexpected SMTP reply: ' . trim($data));
        }
        return $data;
    }

    private function writeData(string $message): void
    {
        foreach (explode("\r\n", $message) as $line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
            fwrite($this->conn, $line . "\r\n");
        }
    }

    private function close(): void
    {
        if (is_resource($this->conn)) {
            @fclose($this->conn);
        }
        $this->conn = null;
    }

    /**
     * @param list<string> $recipients
     */
    private function buildMessage(
        string $fromEmail,
        string $fromName,
        array $recipients,
        string $subject,
        string $body,
        ?string $replyTo
    ): string {
        $eol = "\r\n";
        $domain = substr((string) strrchr($fromEmail, '@'), 1) ?: 'localhost';

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'To: ' . implode(', ', $recipients);
        $replyTo = $replyTo === null ? '' : $this->sanitizeHeaderValue($replyTo);
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';

        $normalised = str_replace(["\r\n", "\r"], "\n", $body);
        $normalised = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $normalised) ?? $normalised;
        $encoded = rtrim(chunk_split(base64_encode($normalised), 76, $eol));

        return implode($eol, $headers) . $eol . $eol . $encoded;
    }

    private function encodeHeader(string $text): string
    {
        $text = $this->sanitizeHeaderValue($text);
        if ($text === '') {
            return '';
        }
        if (preg_match('/^[\x20-\x7E]*$/', $text) === 1) {
            return $text;
        }
        // Prefer the standard library encoder (correct RFC 2047 folding) when available.
        // $text is already CR/LF-stripped, so the folded output is safe as-is.
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
        }
        $parts = [];
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            $buffer = '';
            $length = mb_strlen($text, 'UTF-8');
            for ($i = 0; $i < $length; $i++) {
                $char = mb_substr($text, $i, 1, 'UTF-8');
                if ($buffer !== '' && strlen($buffer . $char) > 45) {
                    $parts[] = $buffer;
                    $buffer = '';
                }
                $buffer .= $char;
            }
            if ($buffer !== '') {
                $parts[] = $buffer;
            }
        } else {
            $parts[] = $text;
        }
        return implode("\r\n ", array_map(
            static fn(string $chunk): string => '=?UTF-8?B?' . base64_encode($chunk) . '?=',
            $parts
        ));
    }

    private function sanitizeHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }
}
