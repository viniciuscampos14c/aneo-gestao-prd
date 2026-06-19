<?php

class ChatwootController extends BaseController
{
    private ChatwootModel $chatwootLinks;
    private ChatwootFlowModel $chatwootFlow;
    private ChatwootService $chatwoot;
    private StudentModel $students;
    private LeadModel $leads;

    public function __construct()
    {
        $this->chatwootLinks = new ChatwootModel();
        $this->chatwootFlow = new ChatwootFlowModel();
        $this->chatwoot = new ChatwootService();
        $this->students = new StudentModel();
        $this->leads = new LeadModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('chatwoot');

        $filters = [
            'q' => trim((string) request('q', '')),
            'entity_type' => trim((string) request('entity_type', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $links = $this->chatwootLinks->listLinks($filters, $perPage, $page);

        $this->render('chatwoot/index', [
            'title' => 'Atendimento (Chatwoot)',
            'filters' => $filters,
            'rows' => $links['rows'],
            'meta' => $links['meta'],
            'stats' => $this->chatwootLinks->stats(),
            'featureAvailable' => $this->chatwootLinks->featureAvailable(),
            'flowFeatureAvailable' => $this->chatwootFlow->featureAvailable(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'integration' => [
                'enabled' => $this->chatwoot->isEnabled(),
                'configured' => $this->chatwoot->isConfigured(),
                'base_url' => $this->chatwoot->baseUrl(),
                'account_id' => $this->chatwoot->accountId(),
                'inbox_id' => $this->chatwoot->inboxId(),
                'conversations_url' => $this->chatwoot->dashboardConversationsUrl(),
                'bot_enabled' => $this->chatwoot->botEnabled(),
                'webhook_token' => $this->chatwoot->webhookToken(),
            ],
        ]);
    }

    public function openStudent(): void
    {
        require_auth();
        require_permission('chat.open');
        csrf_validate();

        $studentId = (int) post('student_id');
        $returnRoute = $this->cleanReturnRoute((string) post('return_route', 'students'));

        $student = $this->students->find($studentId);
        if (!$student) {
            $this->error('Aluno não encontrado para abrir conversa.');
            $this->redirect($returnRoute);
        }

        $this->openEntityConversation(
            'student',
            $studentId,
            [
                'name' => (string) ($student['full_name'] ?? ''),
                'email' => (string) ($student['email_primary'] ?? ''),
                'phone' => (string) ($student['phone'] ?? ''),
            ],
            $returnRoute
        );
    }

    public function openLead(): void
    {
        require_auth();
        require_permission('chat.open');
        csrf_validate();

        $leadId = (int) post('lead_id');
        $returnRoute = $this->cleanReturnRoute((string) post('return_route', 'leads'));

        $lead = $this->leads->find($leadId);
        if (!$lead) {
            $this->error('Lead não encontrado para abrir conversa.');
            $this->redirect($returnRoute);
        }

        $this->openEntityConversation(
            'lead',
            $leadId,
            [
                'name' => (string) ($lead['full_name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'phone' => (string) ($lead['phone'] ?? ''),
            ],
            $returnRoute
        );
    }

    public function openPhone(): void
    {
        require_auth();
        require_permission('chat.open');
        csrf_validate();

        $entityType = strtolower(trim((string) post('entity_type', 'other')));
        if (!in_array($entityType, ['student', 'lead', 'other'], true)) {
            $entityType = 'other';
        }

        $entityId = max(0, (int) post('entity_id', 0));
        $returnRoute = $this->cleanReturnRoute((string) post('return_route', 'chatwoot'));

        $name = trim((string) post('name'));
        $email = trim((string) post('email'));
        $phone = trim((string) post('phone'));

        if ($name === '' && $phone === '' && $email === '') {
            $this->error('Informe ao menos nome, telefone ou email para iniciar a conversa.');
            $this->redirect($returnRoute);
        }

        $this->openEntityConversation(
            $entityType,
            $entityId,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ],
            $returnRoute
        );
    }

    private function openEntityConversation(string $entityType, int $entityId, array $person, string $returnRoute): void
    {
        $existing = null;
        if ($entityId > 0) {
            $existing = $this->chatwootLinks->findByEntity($entityType, $entityId);
        }

        $result = $this->chatwoot->ensureConversation([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'name' => $person['name'] ?? '',
            'email' => $person['email'] ?? '',
            'phone' => $person['phone'] ?? '',
        ], $existing);

        if (!$result['ok']) {
            $this->error($result['message'] ?: 'Não foi possível abrir conversa no Chatwoot.');
            if (!empty($result['conversation_url']) && filter_var($result['conversation_url'], FILTER_VALIDATE_URL)) {
                header('Location: ' . $result['conversation_url']);
                exit;
            }
            $this->redirect($returnRoute);
        }

        if ($entityId > 0) {
            $this->chatwootLinks->upsertLink([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'contact_id' => $result['contact_id'] ?? null,
                'contact_source_id' => $result['contact_source_id'] ?? null,
                'conversation_id' => $result['conversation_id'] ?? null,
                'conversation_url' => $result['conversation_url'] ?? null,
                'status' => 'open',
                'contact_name' => $result['contact_name'] ?? ($person['name'] ?? null),
                'contact_phone' => $result['contact_phone'] ?? ($person['phone'] ?? null),
                'contact_email' => $result['contact_email'] ?? ($person['email'] ?? null),
                'last_synced_at' => now(),
            ], (int) current_user()['id']);
        }

        $url = trim((string) ($result['conversation_url'] ?? ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            header('Location: ' . $url);
            exit;
        }

        $this->success('Conversa preparada no Chatwoot.');
        $this->redirect('chatwoot');
    }

    private function cleanReturnRoute(string $route): string
    {
        $route = trim($route);
        if ($route === '') {
            return 'dashboard';
        }

        $route = str_replace('index.php?', '', $route);
        $route = str_replace('route=', '', $route);
        $route = ltrim($route, '/');

        return $route !== '' ? $route : 'dashboard';
    }
}
