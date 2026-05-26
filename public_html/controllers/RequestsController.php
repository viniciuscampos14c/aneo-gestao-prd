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
            'source' => trim((string) request('source', '')),
            'mobile_flow' => (int) request('mobile_flow', 0) > 0 ? 1 : 0,
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
            'mobileQueue' => $this->tickets->mobileNegotiationStats(),
            'integration' => [
                'notification_email' => (string) config('support.notification_email', 'vinicius14c@hotmail.com'),
                'webhook_enabled' => (bool) config('support.external_webhook_enabled', false),
                'webhook_url' => (string) config('support.external_webhook_url', ''),
                'webhook_token' => (string) config('support.external_webhook_token', ''),
                'local_webhook_url' => $this->buildWebhookUrl(),
            ],
        ]);
    }

    public function mobileAlerts(): void
    {
        require_auth();

        if (is_professor()) {
            $this->json([
                'ok' => true,
                'data' => [
                    'mobile_negotiation_alert_count' => 0,
                    'mobile_negotiation_alerts' => [],
                ],
            ]);
        }

        $this->json([
            'ok' => true,
            'data' => [
                'mobile_negotiation_alert_count' => $this->tickets->countOpenMobileNegotiations(),
                'mobile_negotiation_alerts' => $this->tickets->latestMobileNegotiationAlerts(5),
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

    public function mobileDecision(): void
    {
        require_auth();
        require_permission('requests.manage');
        csrf_validate();
        $returnRoute = $this->resolveReturnRoute('requests');

        $ticketId = (int) post('ticket_id');
        $decision = strtolower(trim((string) post('decision')));
        $note = trim((string) post('decision_note'));

        if ($ticketId <= 0) {
            $this->error('Chamado invalido para decisao.');
            $this->redirect($returnRoute);
        }

        $ticket = $this->tickets->findTicket($ticketId);
        if (!$ticket) {
            $this->error('Chamado nao encontrado.');
            $this->redirect($returnRoute);
        }

        if (!$this->tickets->isMobileNegotiationTicket($ticket)) {
            $this->error('Este chamado nao pertence ao fluxo de negociacao do app.');
            $this->redirect($returnRoute);
        }

        $currentStatus = strtolower(trim((string) ($ticket['status'] ?? 'open')));
        if (!in_array($currentStatus, ['open', 'in_progress'], true)) {
            $this->error('Esta negociacao ja foi finalizada anteriormente e nao pode ser processada novamente.');
            $this->redirect($returnRoute);
        }

        $statusMap = [
            'approve' => 'resolved',
            'adjust' => 'in_progress',
            'reject' => 'closed',
        ];

        $commentMap = [
            'approve' => '[Fluxo Mobile] Negociacao aprovada pela equipe administrativa.',
            'adjust' => '[Fluxo Mobile] Solicitado ajuste na proposta enviada pelo app.',
            'reject' => '[Fluxo Mobile] Negociacao reprovada pela equipe administrativa.',
        ];

        if (!isset($statusMap[$decision])) {
            $this->error('Acao de decisao invalida.');
            $this->redirect($returnRoute);
        }

        if ($decision === 'approve') {
            if (!has_permission('finance.invoice.settle') || !has_permission('finance.invoice.create')) {
                $this->error('Voce precisa das permissoes de baixa e criacao de faturas para aprovar esta negociacao.');
                $this->redirect($returnRoute);
            }

            $payload = $this->parseMobileNegotiationPayload($ticket);
            if (!$payload['ok']) {
                $this->error((string) ($payload['message'] ?? 'Nao foi possivel interpretar os dados da negociacao no chamado.'));
                $this->redirect($returnRoute);
            }

            $data = $payload['data'] ?? [];
            $finance = new FinanceModel();
            $result = $finance->applyMobileNegotiationApproval(
                (int) ($data['student_id'] ?? 0),
                (float) ($data['negotiated_total'] ?? 0),
                (int) ($data['installments'] ?? 1),
                (string) ($data['first_due_date'] ?? ''),
                (int) (current_user()['id'] ?? 0),
                [
                    'ticket_id' => $ticketId,
                    'ticket_code' => (string) ($ticket['ticket_code'] ?? ''),
                    'mode' => (string) ($data['mode'] ?? 'negociacao'),
                    'scope' => (string) ($data['scope'] ?? 'total'),
                    'selected_invoice_numbers' => (array) ($data['selected_invoice_numbers'] ?? []),
                    'payment_method_id' => (int) ($data['payment_method_id'] ?? 0),
                ]
            );

            if (!$result['ok']) {
                $this->error((string) ($result['message'] ?? 'Nao foi possivel aplicar a negociacao no financeiro.'));
                $this->redirect($returnRoute);
            }

            $this->tickets->updateStatus($ticketId, 'resolved');
            $numbers = array_values(array_filter(array_map('trim', (array) ($result['new_invoice_numbers'] ?? [])), fn ($v) => $v !== ''));
            $comment = '[Fluxo Mobile] Negociacao aprovada e aplicada no financeiro.'
                . "\nTitulos renegociados: " . (int) ($result['renegotiated_invoices_count'] ?? 0)
                . "\nValor renegociado de origem: " . format_currency((float) ($result['renegotiated_total'] ?? 0))
                . "\nParcelamento gerado: " . (int) ($result['installments'] ?? 1) . 'x'
                . "\nValor renegociado: " . format_currency((float) ($result['new_total'] ?? 0))
                . "\nPrimeiro vencimento: " . (string) ($result['first_due_date'] ?? '');
            if (!empty($data['payment_method_name'])) {
                $comment .= "\nForma de pagamento: " . (string) $data['payment_method_name'];
            }
            if ($numbers !== []) {
                $comment .= "\nNovas faturas: " . implode(', ', $numbers);
            }
            if ($note !== '') {
                $comment .= "\nObservacao: " . $note;
            }

            $this->tickets->addComment($ticketId, $comment, (int) (current_user()['id'] ?? 0));
            $this->success(
                'Negociacao aprovada com sucesso. '
                . (int) ($result['renegotiated_invoices_count'] ?? 0)
                . ' titulo(s) renegociado(s) e '
                . count((array) ($result['new_invoice_ids'] ?? []))
                . ' fatura(s) nova(s) criada(s).'
            );
            $this->redirect($returnRoute);
        }

        $this->tickets->updateStatus($ticketId, $statusMap[$decision]);
        $comment = $commentMap[$decision];
        if ($note !== '') {
            $comment .= "\nObservacao: " . $note;
        }
        $this->tickets->addComment($ticketId, $comment, (int) (current_user()['id'] ?? 0));

        $labels = [
            'approve' => 'aprovada',
            'adjust' => 'encaminhada para ajuste',
            'reject' => 'reprovada',
        ];

        $this->success('Negociacao ' . ($labels[$decision] ?? 'atualizada') . ' com sucesso.');
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

    private function parseMobileNegotiationPayload(array $ticket): array
    {
        $subject = strtolower(trim((string) ($ticket['subject'] ?? '')));
        $descriptionRaw = (string) ($ticket['description'] ?? '');
        $descriptionRaw = str_replace("\r\n", "\n", $descriptionRaw);
        $description = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $descriptionRaw);
        if (!is_string($description)) {
            $description = $descriptionRaw;
        }

        if ($description === '') {
            return ['ok' => false, 'message' => 'Descricao do chamado vazia para leitura da negociacao.'];
        }

        $studentId = 0;
        if (preg_match('/Aluno:\s.*\(ID\s*(\d+)\)/iu', $description, $matchStudent)) {
            $studentId = (int) ($matchStudent[1] ?? 0);
        }
        if ($studentId <= 0) {
            return ['ok' => false, 'message' => 'Nao foi possivel identificar o ID do aluno na negociacao.'];
        }

        $installments = 0;
        $installmentValue = 0.0;
        if (preg_match('/Parcelamento:\s*(\d+)\s*(?:x|X|×)?(?:\s*de)?\s*(R\$\s*[0-9\.\,]+)/iu', $description, $matchInstallments)) {
            $installments = (int) ($matchInstallments[1] ?? 0);
            $installmentValue = parse_decimal((string) ($matchInstallments[2] ?? '0'));
        }
        if ($installments <= 0) {
            foreach (explode("\n", $description) as $line) {
                $line = trim((string) $line);
                if (stripos($line, 'Parcelamento:') !== 0 && stripos($line, 'Parcelas:') !== 0) {
                    continue;
                }

                if (preg_match('/(\d+)/', $line, $matchQty)) {
                    $installments = (int) ($matchQty[1] ?? 0);
                }
                if (preg_match('/R\$\s*[0-9\.\,]+/u', $line, $matchValue)) {
                    $installmentValue = parse_decimal((string) ($matchValue[0] ?? '0'));
                }
                break;
            }
        }
        if ($installments <= 0) {
            return ['ok' => false, 'message' => 'Nao foi possivel identificar o parcelamento da negociacao.'];
        }

        $firstDueDate = '';
        if (preg_match('/Primeiro vencimento:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/iu', $description, $matchDueDate)) {
            $firstDueDate = trim((string) ($matchDueDate[1] ?? ''));
        }
        if ($firstDueDate === '' && preg_match('/Primeiro vencimento:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/iu', $description, $matchDueDateBr)) {
            $parts = explode('/', trim((string) ($matchDueDateBr[1] ?? '')));
            if (count($parts) === 3) {
                $firstDueDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        }
        if ($firstDueDate === '') {
            return ['ok' => false, 'message' => 'Nao foi possivel identificar o primeiro vencimento da negociacao.'];
        }

        $negotiatedTotal = 0.0;
        if (preg_match('/Novo valor total:\s*(R\$\s*[0-9\.\,]+)/iu', $description, $matchTotal)) {
            $negotiatedTotal = parse_decimal((string) ($matchTotal[1] ?? '0'));
        }
        if ($negotiatedTotal <= 0 && preg_match('/Total com desconto:\s*(R\$\s*[0-9\.\,]+)/iu', $description, $matchDiscountTotal)) {
            $negotiatedTotal = parse_decimal((string) ($matchDiscountTotal[1] ?? '0'));
        }
        if ($negotiatedTotal <= 0 && $installmentValue > 0) {
            $negotiatedTotal = round($installmentValue * $installments, 2);
        }
        if ($negotiatedTotal <= 0) {
            return ['ok' => false, 'message' => 'Nao foi possivel identificar o valor total negociado.'];
        }

        $mode = str_starts_with($subject, 'aditivo financeiro -') ? 'aditivo' : 'negociacao';
        $scope = 'total';
        if (preg_match('/Escopo da renegociacao:\s*(.+)/iu', $description, $matchScope)) {
            $scopeText = strtolower(trim((string) ($matchScope[1] ?? '')));
            if (str_contains($scopeText, 'vencid')) {
                $scope = 'overdue';
            }
        }

        $selectedInvoiceNumbers = [];
        if (preg_match('/Faturas vencidas consideradas:\s*(.*?)(?:\n[A-ZÁÂÃÉÊÍÓÔÕÚÇ][^\n]*:|\z)/isu', $description, $matchInvoicesBlock)) {
            $block = (string) ($matchInvoicesBlock[1] ?? '');
            if (preg_match_all('/FATURA-\d{6}-\d{2}/iu', $block, $matchesInvoices)) {
                $selectedInvoiceNumbers = array_values(array_unique(array_map(
                    static fn (string $number): string => strtoupper(trim($number)),
                    $matchesInvoices[0]
                )));
            }
        }

        $paymentMethodId = 0;
        $paymentMethodName = '';
        if (preg_match('/Forma de pagamento selecionada:\s*(.+?)\s*\(ID\s*(\d+)\)/iu', $description, $matchPayment)) {
            $paymentMethodName = trim((string) ($matchPayment[1] ?? ''));
            $paymentMethodId = (int) ($matchPayment[2] ?? 0);
        }

        return [
            'ok' => true,
            'data' => [
                'student_id' => $studentId,
                'installments' => $installments,
                'first_due_date' => $firstDueDate,
                'negotiated_total' => round($negotiatedTotal, 2),
                'mode' => $mode,
                'scope' => $scope,
                'selected_invoice_numbers' => $selectedInvoiceNumbers,
                'payment_method_id' => $paymentMethodId,
                'payment_method_name' => $paymentMethodName,
            ],
        ];
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
