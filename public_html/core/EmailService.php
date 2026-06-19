<?php

class EmailService
{
    private CompanyIntegrationModel $integrations;

    public function __construct()
    {
        $this->integrations = new CompanyIntegrationModel();
    }

    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        $to = strtolower(trim($to));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'message' => 'Destinatário de e-mail inválido.',
            ];
        }

        $companyId = (int) ($options['company_id'] ?? current_company_id() ?? 0);
        $smtp = $this->resolveSmtpSettings($companyId);
        $smtpOverride = $options['smtp_override'] ?? null;
        if (is_array($smtpOverride)) {
            $smtp = $this->normalizeSmtpSettings(array_merge($smtp, $smtpOverride));
        }

        $fromEmail = strtolower(trim((string) ($options['from_email'] ?? $smtp['from_email'] ?? $this->defaultFromEmail())));
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $this->defaultFromEmail();
        }

        $fromName = trim((string) ($options['from_name'] ?? $smtp['from_name'] ?? config('app.name', 'ANEO Gestao')));
        $replyTo = strtolower(trim((string) ($options['reply_to'] ?? $smtp['reply_to'] ?? '')));
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = '';
        }

        $isHtml = !empty($options['is_html']);

        $bcc = $this->normalizeBcc($options['bcc'] ?? null);

        $smtpEnabled = !empty($smtp['enabled']) && trim((string) ($smtp['host'] ?? '')) !== '';
        if ($smtpEnabled) {
            return $this->sendViaSmtp(
                $to,
                $subject,
                $body,
                [
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'reply_to' => $replyTo,
                    'is_html' => $isHtml,
                    'bcc' => $bcc,
                ],
                $smtp
            );
        }

        return $this->sendViaMail(
            $to,
            $subject,
            $body,
            [
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'reply_to' => $replyTo,
                'is_html' => $isHtml,
                'bcc' => $bcc,
            ]
        );
    }

    /**
     * Normaliza o valor de BCC: aceita string ou array, retorna array de emails validos.
     */
    private function normalizeBcc(mixed $bcc): array
    {
        if ($bcc === null || $bcc === '' || $bcc === []) {
            return [];
        }

        $list = is_array($bcc) ? $bcc : [$bcc];
        $valid = [];
        foreach ($list as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }

        return array_values(array_unique($valid));
    }

    private function resolveSmtpSettings(int $companyId): array
    {
        $settings = config('smtp', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        if ($companyId > 0 && $this->integrations->tableExists()) {
            $settings = $this->integrations->mergeWithGlobalConfig('smtp', $companyId);
        }

        return $this->normalizeSmtpSettings($settings);
    }

    private function normalizeSmtpSettings(array $settings): array
    {
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['host'] = trim((string) ($settings['host'] ?? ''));

        $security = strtolower(trim((string) ($settings['security'] ?? 'tls')));
        if (!in_array($security, ['none', 'tls', 'ssl'], true)) {
            $security = 'tls';
        }
        $settings['security'] = $security;

        $port = (int) ($settings['port'] ?? 0);
        if ($port <= 0) {
            $port = $security === 'ssl' ? 465 : 587;
        }
        $settings['port'] = max(1, min(65535, $port));

        $timeout = (int) ($settings['timeout'] ?? 20);
        $settings['timeout'] = max(5, min(120, $timeout));

        $settings['username'] = trim((string) ($settings['username'] ?? ''));
        $settings['password'] = (string) ($settings['password'] ?? '');
        $settings['from_email'] = strtolower(trim((string) ($settings['from_email'] ?? '')));
        $settings['from_name'] = trim((string) ($settings['from_name'] ?? config('app.name', 'ANEO Gestao')));
        $settings['reply_to'] = strtolower(trim((string) ($settings['reply_to'] ?? '')));

        return $settings;
    }

    private function sendViaMail(string $to, string $subject, string $body, array $options): array
    {
        if (!function_exists('mail')) {
            return [
                'ok' => false,
                'message' => 'Funcao mail() indisponivel no servidor e SMTP não configurado.',
            ];
        }

        $subject = trim($subject);
        $contentType = !empty($options['is_html']) ? 'text/html' : 'text/plain';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType . '; charset=UTF-8',
            'From: ' . $this->formatAddress((string) $options['from_email'], (string) ($options['from_name'] ?? '')),
        ];

        $replyTo = trim((string) ($options['reply_to'] ?? ''));
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $bcc = $options['bcc'] ?? [];
        if (!empty($bcc)) {
            $headers[] = 'Bcc: ' . implode(', ', $bcc);
        }

        $sent = @mail(
            $to,
            $this->encodeHeader($subject),
            $body,
            implode("\r\n", $headers)
        );

        return [
            'ok' => $sent,
            'message' => $sent ? 'Email enviado com sucesso.' : 'Falha ao enviar email usando mail().',
        ];
    }

    private function sendViaSmtp(string $to, string $subject, string $body, array $options, array $smtp): array
    {
        $host = (string) ($smtp['host'] ?? '');
        $port = (int) ($smtp['port'] ?? 587);
        $security = (string) ($smtp['security'] ?? 'tls');
        $username = (string) ($smtp['username'] ?? '');
        $password = (string) ($smtp['password'] ?? '');
        $timeout = (int) ($smtp['timeout'] ?? 20);

        $remoteHost = $security === 'ssl' ? 'ssl://' . $host : $host;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remoteHost . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            return [
                'ok' => false,
                'message' => 'Falha de conexao SMTP: ' . ($errstr !== '' ? $errstr : 'erro desconhecido') . ' (' . $errno . ').',
            ];
        }

        stream_set_timeout($socket, $timeout);

        $response = $this->readSmtpResponse($socket);
        if (!$this->responseHasCode($response, [220])) {
            fclose($socket);
            return [
                'ok' => false,
                'message' => 'SMTP não respondeu com saudação valida: ' . $this->sanitizeSmtpResponse($response),
            ];
        }

        $heloHost = $this->smtpClientHost();

        if (!$this->runSmtpCommand($socket, 'EHLO ' . $heloHost, [250], $error)) {
            fclose($socket);
            return ['ok' => false, 'message' => $error];
        }

        if ($security === 'tls') {
            if (!$this->runSmtpCommand($socket, 'STARTTLS', [220], $error)) {
                fclose($socket);
                return ['ok' => false, 'message' => $error];
            }

            $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => 'Não foi possível iniciar criptografia TLS no SMTP.',
                ];
            }

            if (!$this->runSmtpCommand($socket, 'EHLO ' . $heloHost, [250], $error)) {
                fclose($socket);
                return ['ok' => false, 'message' => $error];
            }
        }

        if ($username !== '' || $password !== '') {
            if ($username === '' || $password === '') {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => 'Usuário e senha SMTP devem ser informados juntos.',
                ];
            }

            if (!$this->runSmtpCommand($socket, 'AUTH LOGIN', [334], $error)) {
                fclose($socket);
                return ['ok' => false, 'message' => $error];
            }

            if (!$this->runSmtpCommand($socket, base64_encode($username), [334], $error)) {
                fclose($socket);
                return ['ok' => false, 'message' => $error];
            }

            if (!$this->runSmtpCommand($socket, base64_encode($password), [235], $error)) {
                fclose($socket);
                return ['ok' => false, 'message' => $error];
            }
        }

        $fromEmail = (string) $options['from_email'];
        if (!$this->runSmtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $error)) {
            fclose($socket);
            return ['ok' => false, 'message' => $error];
        }

        if (!$this->runSmtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251], $error)) {
            fclose($socket);
            return ['ok' => false, 'message' => $error];
        }

        $bcc = $options['bcc'] ?? [];
        foreach ($bcc as $bccAddress) {
            if (!$this->runSmtpCommand($socket, 'RCPT TO:<' . $bccAddress . '>', [250, 251], $error)) {
                fclose($socket);
                return ['ok' => false, 'message' => $error];
            }
        }

        if (!$this->runSmtpCommand($socket, 'DATA', [354], $error)) {
            fclose($socket);
            return ['ok' => false, 'message' => $error];
        }

        $payload = $this->buildSmtpPayload($to, $subject, $body, $options);
        fwrite($socket, $payload . "\r\n.\r\n");

        $response = $this->readSmtpResponse($socket);
        if (!$this->responseHasCode($response, [250])) {
            fclose($socket);
            return [
                'ok' => false,
                'message' => 'Servidor SMTP rejeitou a mensagem: ' . $this->sanitizeSmtpResponse($response),
            ];
        }

        $this->runSmtpCommand($socket, 'QUIT', [221], $ignoredError);
        fclose($socket);

        return [
            'ok' => true,
            'message' => 'Email enviado com sucesso via SMTP.',
        ];
    }

    private function runSmtpCommand($socket, string $command, array $expectedCodes, ?string &$error): bool
    {
        fwrite($socket, $command . "\r\n");
        $response = $this->readSmtpResponse($socket);
        if ($this->responseHasCode($response, $expectedCodes)) {
            $error = null;
            return true;
        }

        $error = 'SMTP comando falhou (' . $command . '): ' . $this->sanitizeSmtpResponse($response);
        return false;
    }

    private function readSmtpResponse($socket): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return trim($response);
    }

    private function responseHasCode(string $response, array $expectedCodes): bool
    {
        $code = (int) substr(trim($response), 0, 3);
        return in_array($code, $expectedCodes, true);
    }

    private function buildSmtpPayload(string $to, string $subject, string $body, array $options): string
    {
        $fromEmail = (string) ($options['from_email'] ?? $this->defaultFromEmail());
        $fromName = (string) ($options['from_name'] ?? config('app.name', 'ANEO Gestao'));
        $replyTo = trim((string) ($options['reply_to'] ?? ''));
        $isHtml = !empty($options['is_html']);

        $domain = $this->domainFromEmail($fromEmail);
        try {
            $messageIdPart = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $messageIdPart = sha1(uniqid((string) mt_rand(), true));
        }

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . $messageIdPart . '@' . $domain . '>',
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'To: ' . $to,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $normalized);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        unset($line);

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $lines);
    }

    private function defaultFromEmail(): string
    {
        $configured = strtolower(trim((string) config('support.from_email', '')));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        return 'nao-responda@aneo.local';
    }

    private function smtpClientHost(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($host === '') {
            return 'localhost';
        }

        $parts = explode(':', $host, 2);
        return preg_replace('/[^a-z0-9.-]/i', '', $parts[0]) ?: 'localhost';
    }

    private function domainFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        $domain = trim((string) ($parts[1] ?? ''));
        if ($domain === '') {
            return 'localhost';
        }

        return preg_replace('/[^a-z0-9.-]/i', '', strtolower($domain)) ?: 'localhost';
    }

    private function encodeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function formatAddress(string $email, string $name = ''): string
    {
        $email = trim($email);
        $name = trim($name);
        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function sanitizeSmtpResponse(string $response): string
    {
        $response = trim(preg_replace('/\s+/', ' ', $response) ?? '');
        return $response !== '' ? $response : 'sem resposta do servidor SMTP';
    }
}
