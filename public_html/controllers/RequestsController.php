<?php

class RequestsController extends BaseController
{
    private SupportTicketModel $tickets;
    private SupportTicketDispatchService $dispatch;

    public function __construct()
    {
        $this->tickets = new SupportTicketModel();
        $this->dispatch = new SupportTicketDispatchService();
    }

    public function index(): void
    {
        require_auth();
        require_permission('requests');

        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
            'priority' => trim((string) request('priority', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->tickets->listTickets($filters, $perPage, $page);
        $ticketIds = array_map(fn ($row) => (int) ($row['id'] ?? 0), $result['rows']);

        $this->render('requests/index', [
            'title' => 'Solicitações',
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'stats' => $this->tickets->stats(),
            'attachmentsByTicket' => $this->tickets->attachmentsByTicketIds($ticketIds),
            'commentsByTicket' => $this->tickets->commentsByTicketIds($ticketIds),
            'featureAvailable' => $this->tickets->featureAvailable(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'integration' => [
                'notification_email' => (string) config('support.notification_email', 'vinicius14c@hotmail.com'),
                'webhook_enabled' => (bool) config('support.external_webhook_enabled', false),
                'webhook_url' => (string) config('support.external_webhook_url', ''),
                'webhook_token' => (string) config('support.external_webhook_token', ''),
                'local_webhook_url' => $this->buildWebhookUrl(),
            ],
        ]);
    }

    public function store(): void
    {
        require_auth();
        require_permission('requests.manage');
        csrf_validate();
        $returnRoute = $this->resolveReturnRoute('requests');

        if (!$this->tickets->featureAvailable()) {
            $this->error('Estrutura de chamados indisponivel. Execute a migracao de suporte.');
            $this->redirect($returnRoute);
        }

        $subject = trim((string) post('subject'));
        $description = trim((string) post('description'));
        $priority = $this->normalizePriority((string) post('priority', 'medium'));

        if ($subject === '' || $description === '') {
            $this->error('Assunto e descricao sao obrigatorios.');
            $this->redirect($returnRoute);
        }

        $user = current_user() ?? [];
        $ticketId = $this->tickets->createTicket([
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'requester_name' => (string) ($user['name'] ?? ''),
            'requester_email' => (string) ($user['email'] ?? ''),
        ], (int) ($user['id'] ?? 0), 'internal');

        if ($ticketId <= 0) {
            $this->error('Nao foi possivel criar o chamado.');
            $this->redirect($returnRoute);
        }

        $uploaded = $this->handlePrintUploads($ticketId, $_FILES['prints'] ?? null, (int) ($user['id'] ?? 0));
        $ticket = $this->tickets->findTicket($ticketId);
        if (!$ticket) {
            $this->error('Chamado criado, mas nao foi possivel recarregar os dados.');
            $this->redirect($returnRoute);
        }

        $attachmentsByTicket = $this->tickets->attachmentsByTicketIds([$ticketId]);
        $attachments = $attachmentsByTicket[$ticketId] ?? [];
        $dispatch = $this->dispatch->dispatchNewTicket($ticket, $attachments, $user);

        $emailOk = (bool) ($dispatch['email']['ok'] ?? false);
        $webhookOk = (bool) ($dispatch['webhook']['ok'] ?? false);
        $webhookSkipped = (bool) ($dispatch['webhook']['skipped'] ?? false);

        $this->tickets->markDispatchStatus(
            $ticketId,
            $emailOk,
            $webhookSkipped ? false : $webhookOk,
            isset($dispatch['webhook']['reference']) ? (string) $dispatch['webhook']['reference'] : null
        );

        $message = 'Chamado ' . (string) ($ticket['ticket_code'] ?? ('#' . $ticketId)) . ' criado com sucesso.';
        if ($uploaded > 0) {
            $message .= ' ' . $uploaded . ' print(s) anexado(s).';
        }
        if (!$emailOk) {
            $message .= ' Aviso: email nao enviado para ' . (string) config('support.notification_email', 'vinicius14c@hotmail.com') . '.';
        }
        if (!$webhookSkipped && !$webhookOk) {
            $message .= ' Aviso: falha no envio para o site externo.';
        }

        $this->success($message);
        $this->redirect($returnRoute);
    }

    public function addComment(): void
    {
        require_auth();
        require_permission('requests.manage');
        csrf_validate();
        $returnRoute = $this->resolveReturnRoute('requests');

        $ticketId = (int) post('ticket_id');
        $comment = trim((string) post('comment'));

        if ($ticketId <= 0 || $comment === '') {
            $this->error('Informe o comentario do chamado.');
            $this->redirect($returnRoute);
        }

        $ticket = $this->tickets->findTicket($ticketId);
        if (!$ticket) {
            $this->error('Chamado nao encontrado.');
            $this->redirect($returnRoute);
        }

        $this->tickets->addComment($ticketId, $comment, (int) (current_user()['id'] ?? 0));
        $this->success('Comentario adicionado no chamado.');
        $this->redirect($returnRoute);
    }

    public function updateStatus(): void
    {
        require_auth();
        require_permission('requests.manage');
        csrf_validate();
        $returnRoute = $this->resolveReturnRoute('requests');

        $ticketId = (int) post('ticket_id');
        $status = $this->normalizeStatus((string) post('status', 'open'));
        $statusNote = trim((string) post('status_note'));

        if ($ticketId <= 0) {
            $this->error('Chamado invalido.');
            $this->redirect($returnRoute);
        }

        $ticket = $this->tickets->findTicket($ticketId);
        if (!$ticket) {
            $this->error('Chamado nao encontrado.');
            $this->redirect($returnRoute);
        }

        $this->tickets->updateStatus($ticketId, $status);

        if ($statusNote !== '') {
            $this->tickets->addComment($ticketId, '[Status ' . $status . '] ' . $statusNote, (int) (current_user()['id'] ?? 0));
        }

        $this->success('Status do chamado atualizado.');
        $this->redirect($returnRoute);
    }

    public function webhook(): void
    {
        if (!$this->tickets->featureAvailable()) {
            $this->json([
                'ok' => false,
                'message' => 'Estrutura de chamados indisponivel.',
            ], 503);
        }

        $configuredToken = trim((string) config('support.external_webhook_token', ''));
        $providedToken = trim((string) request('token', ''));
        if ($providedToken === '' && isset($_SERVER['HTTP_X_SUPPORT_TOKEN'])) {
            $providedToken = trim((string) $_SERVER['HTTP_X_SUPPORT_TOKEN']);
        }

        if ($configuredToken !== '' && !hash_equals($configuredToken, $providedToken)) {
            $this->json([
                'ok' => false,
                'message' => 'Token invalido para webhook de chamados.',
            ], 401);
        }

        $raw = file_get_contents('php://input');
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload) || $payload === []) {
            $payload = $_POST ?: [];
        }

        if ($payload === []) {
            $this->json([
                'ok' => false,
                'message' => 'Payload vazio.',
            ], 400);
        }

        $companyId = (int) ($payload['company_id'] ?? config('support.webhook_company_id', 0));
        if ($companyId <= 0) {
            $companyId = $this->tickets->firstCompanyId();
        }
        if ($companyId <= 0) {
            $this->json([
                'ok' => false,
                'message' => 'Empresa destino nao encontrada para webhook.',
            ], 422);
        }

        $subject = trim((string) ($payload['subject'] ?? ($payload['title'] ?? 'Chamado recebido via webhook')));
        $description = trim((string) ($payload['description'] ?? ''));
        if ($subject === '' || $description === '') {
            $this->json([
                'ok' => false,
                'message' => 'Assunto e descricao sao obrigatorios.',
            ], 422);
        }

        $ticketId = $this->tickets->createTicketForCompany($companyId, [
            'subject' => $subject,
            'description' => $description,
            'priority' => $this->normalizePriority((string) ($payload['priority'] ?? 'medium')),
            'requester_name' => trim((string) ($payload['requester_name'] ?? '')) ?: 'Site Externo',
            'requester_email' => trim((string) ($payload['requester_email'] ?? '')) ?: null,
            'external_reference' => trim((string) ($payload['ticket_code_origin'] ?? '')) ?: null,
        ], null, 'webhook');

        if ($ticketId <= 0) {
            $this->json([
                'ok' => false,
                'message' => 'Falha ao criar chamado recebido via webhook.',
            ], 500);
        }

        $attachments = $payload['attachments'] ?? [];
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }

                $path = trim((string) ($attachment['url'] ?? ($attachment['file_path'] ?? '')));
                if ($path === '') {
                    continue;
                }

                $name = trim((string) ($attachment['name'] ?? 'print_externo'));
                $mime = trim((string) ($attachment['mime'] ?? ''));
                $size = (int) ($attachment['size'] ?? 0);

                $this->tickets->addAttachmentForCompany(
                    $companyId,
                    $ticketId,
                    $name !== '' ? $name : 'print_externo',
                    $path,
                    $mime !== '' ? $mime : null,
                    $size > 0 ? $size : null,
                    null
                );
            }
        }

        $initialComment = trim((string) ($payload['comment'] ?? ''));
        if ($initialComment !== '') {
            $this->tickets->addCommentForCompany($companyId, $ticketId, $initialComment, null);
        }

        $storedTicket = $this->tickets->findTicketForCompany($companyId, $ticketId);

        $this->json([
            'ok' => true,
            'message' => 'Chamado recebido com sucesso.',
            'ticket_id' => $ticketId,
            'ticket_code' => (string) ($storedTicket['ticket_code'] ?? ''),
        ]);
    }

    private function handlePrintUploads(int $ticketId, $files, int $createdBy): int
    {
        if ($ticketId <= 0 || !$files || !isset($files['name'])) {
            return 0;
        }

        $targetDir = __DIR__ . '/../uploads/support_tickets/' . $ticketId;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'];
        $count = 0;

        $isMulti = is_array($files['name']);
        $names = $isMulti ? $files['name'] : [$files['name']];
        $tmpNames = $isMulti ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = $isMulti ? $files['error'] : [$files['error']];
        $sizes = $isMulti ? $files['size'] : [$files['size']];
        $types = $isMulti ? $files['type'] : [$files['type']];

        foreach ($names as $idx => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $errorCode = (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                continue;
            }

            $size = (int) ($sizes[$idx] ?? 0);
            if ($size <= 0 || $size > (8 * 1024 * 1024)) {
                continue;
            }

            $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $storedName = 'print_' . date('YmdHis') . '_' . $idx . '_' . $safeOriginal;
            $finalPath = $targetDir . '/' . $storedName;
            $tmpPath = (string) ($tmpNames[$idx] ?? '');

            if (!move_uploaded_file($tmpPath, $finalPath)) {
                continue;
            }

            $this->tickets->addAttachment(
                $ticketId,
                $name,
                'uploads/support_tickets/' . $ticketId . '/' . $storedName,
                (string) ($types[$idx] ?? ''),
                $size,
                $createdBy
            );
            $count++;
        }

        return $count;
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));
        return in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true) ? $status : 'open';
    }

    private function buildWebhookUrl(): string
    {
        $baseUrl = trim((string) config('app.base_url', ''));
        if ($baseUrl !== '') {
            $baseUrl = rtrim($baseUrl, '/');
        } else {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $scheme = $isHttps ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $scriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $scriptDir = str_replace('\\', '/', $scriptDir);
            $scriptDir = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
            $baseUrl = $scheme . '://' . $host . $scriptDir;
        }

        $url = $baseUrl . '/index.php?route=requests/webhook';
        $token = trim((string) config('support.external_webhook_token', ''));
        if ($token !== '') {
            $url .= '&token=' . rawurlencode($token);
        }

        return $url;
    }

    private function resolveReturnRoute(string $fallback = 'requests'): string
    {
        $allowedRoutes = ['requests'];
        $candidate = trim((string) post('return_to', $fallback));

        if (!in_array($candidate, $allowedRoutes, true)) {
            return $fallback;
        }

        return $candidate;
    }
}
