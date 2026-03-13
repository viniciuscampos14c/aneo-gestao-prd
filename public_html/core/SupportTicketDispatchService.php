<?php

class SupportTicketDispatchService
{
    public function dispatchNewTicket(array $ticket, array $attachments, array $author): array
    {
        return [
            'email' => $this->sendEmailNotification($ticket, $attachments, $author),
            'webhook' => $this->sendWebhookNotification($ticket, $attachments, $author),
        ];
    }

    private function sendEmailNotification(array $ticket, array $attachments, array $author): array
    {
        $to = trim((string) config('support.notification_email', 'vinicius14c@hotmail.com'));
        if ($to === '') {
            return [
                'ok' => false,
                'message' => 'Email de notificacao nao configurado.',
            ];
        }

        if (!function_exists('mail')) {
            return [
                'ok' => false,
                'message' => 'Funcao mail() indisponivel neste servidor.',
            ];
        }

        $subject = '[Chamado ' . (string) ($ticket['ticket_code'] ?? '#') . '] ' . (string) ($ticket['subject'] ?? 'Novo chamado');
        $from = trim((string) config('support.from_email', 'nao-responda@aneo.local'));
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from,
        ];

        $bodyLines = [
            'Novo chamado registrado no ANEO.',
            '',
            'Codigo: ' . (string) ($ticket['ticket_code'] ?? '-'),
            'Assunto: ' . (string) ($ticket['subject'] ?? '-'),
            'Prioridade: ' . (string) ($ticket['priority'] ?? '-'),
            'Status: ' . (string) ($ticket['status'] ?? '-'),
            'Solicitante: ' . (string) ($author['name'] ?? ($ticket['requester_name'] ?? '-')),
            'Email solicitante: ' . (string) ($author['email'] ?? ($ticket['requester_email'] ?? '-')),
            'Criado em: ' . (string) ($ticket['created_at'] ?? now()),
            '',
            'Descricao:',
            (string) ($ticket['description'] ?? ''),
        ];

        if ($attachments !== []) {
            $bodyLines[] = '';
            $bodyLines[] = 'Anexos:';
            foreach ($attachments as $attachment) {
                $url = $this->attachmentPublicUrl((string) ($attachment['file_path'] ?? ''));
                $bodyLines[] = '- ' . (string) ($attachment['file_name'] ?? 'anexo') . ($url !== '' ? ' => ' . $url : '');
            }
        }

        $sent = @mail($to, $subject, implode(PHP_EOL, $bodyLines), implode("\r\n", $headers));
        return [
            'ok' => $sent ? true : false,
            'message' => $sent ? 'Email enviado para ' . $to . '.' : 'Falha ao enviar email para ' . $to . '.',
            'to' => $to,
        ];
    }

    private function sendWebhookNotification(array $ticket, array $attachments, array $author): array
    {
        $enabled = (bool) config('support.external_webhook_enabled', false);
        $url = trim((string) config('support.external_webhook_url', ''));
        $token = trim((string) config('support.external_webhook_token', ''));

        if (!$enabled || $url === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => 'Webhook externo desativado.',
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'skipped' => false,
                'message' => 'cURL indisponivel para envio do webhook externo.',
            ];
        }

        $attachmentsPayload = [];
        foreach ($attachments as $attachment) {
            $attachmentsPayload[] = [
                'name' => (string) ($attachment['file_name'] ?? 'anexo'),
                'url' => $this->attachmentPublicUrl((string) ($attachment['file_path'] ?? '')),
                'mime' => (string) ($attachment['file_type'] ?? ''),
                'size' => (int) ($attachment['file_size'] ?? 0),
            ];
        }

        $payload = [
            'ticket_code_origin' => (string) ($ticket['ticket_code'] ?? ''),
            'subject' => (string) ($ticket['subject'] ?? ''),
            'description' => (string) ($ticket['description'] ?? ''),
            'priority' => (string) ($ticket['priority'] ?? 'medium'),
            'requester_name' => (string) ($author['name'] ?? ($ticket['requester_name'] ?? '')),
            'requester_email' => (string) ($author['email'] ?? ($ticket['requester_email'] ?? '')),
            'source_site' => $this->baseUrl(),
            'attachments' => $attachmentsPayload,
        ];

        $targetUrl = $url;
        if ($token !== '') {
            $targetUrl .= (str_contains($targetUrl, '?') ? '&' : '?') . 'token=' . rawurlencode($token);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [
                'ok' => false,
                'skipped' => false,
                'message' => 'Falha ao serializar payload de webhook.',
            ];
        }

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($json),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'skipped' => false,
                'message' => 'Falha ao enviar webhook externo: ' . ($err !== '' ? $err : 'erro desconhecido'),
            ];
        }

        $ok = $status >= 200 && $status < 300;
        $decoded = json_decode((string) $raw, true);
        $reference = is_array($decoded) ? (string) ($decoded['ticket_code'] ?? '') : '';

        return [
            'ok' => $ok,
            'skipped' => false,
            'message' => $ok ? 'Webhook enviado para o site externo.' : 'Webhook respondeu HTTP ' . $status . '.',
            'status' => $status,
            'reference' => $reference !== '' ? $reference : null,
        ];
    }

    private function attachmentPublicUrl(string $filePath): string
    {
        $filePath = trim($filePath);
        if ($filePath === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $filePath) === 1) {
            return $filePath;
        }

        return rtrim($this->baseUrl(), '/') . '/' . ltrim($filePath, '/');
    }

    private function baseUrl(): string
    {
        $base = trim((string) config('app.base_url', ''));
        if ($base !== '') {
            return rtrim($base, '/');
        }

        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $scriptDir = str_replace('\\', '/', $scriptDir);
        $scriptDir = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');

        return $scheme . '://' . $host . $scriptDir;
    }
}

