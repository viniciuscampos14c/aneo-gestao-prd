<?php

class GenericModuleController extends BaseController
{
    private GenericModuleModel $items;
    private AdminAiChatModel $aiChat;
    private AdminAiService $assistant;

    public function __construct()
    {
        $this->items = new GenericModuleModel();
        $this->aiChat = new AdminAiChatModel();
        $this->assistant = new AdminAiService();
    }

    public function index(string $module, string $permission, string $title): void
    {
        require_auth();
        require_permission($permission);

        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->items->list($module, $filters, $perPage, $page);

        $data = [
            'title' => $title,
            'module' => $module,
            'permission' => $permission,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ];

        if ($module === 'help') {
            $data['helpAi'] = $this->helpAiData();
        }

        $this->render('generic/module', $data);
    }

    public function store(string $module, string $permission, string $title): void
    {
        require_auth();
        require_permission($permission . '.manage');
        csrf_validate();

        $data = [
            'title' => trim((string) post('title')),
            'status' => trim((string) post('status', 'aberto')),
            'responsible' => trim((string) post('responsible')),
            'priority' => trim((string) post('priority', 'media')),
            'due_date' => trim((string) post('due_date')),
            'notes' => trim((string) post('notes')),
        ];

        if ($data['title'] !== '') {
            $this->items->create($module, $data, (int) current_user()['id']);
            $this->success('Registro criado em ' . $title . '.');
        } else {
            $this->error('Titulo e obrigatorio.');
        }

        $this->redirect($module);
    }

    public function update(string $module, string $permission): void
    {
        require_auth();
        require_permission($permission . '.manage');
        csrf_validate();

        $id = (int) post('id');
        $row = $this->items->find($id);

        if (!$row || $row['module_name'] !== $module) {
            $this->error('Registro nao encontrado.');
            $this->redirect($module);
        }

        $this->items->update($id, [
            'title' => trim((string) post('title')),
            'status' => trim((string) post('status', 'aberto')),
            'responsible' => trim((string) post('responsible')),
            'priority' => trim((string) post('priority', 'media')),
            'due_date' => trim((string) post('due_date')),
            'notes' => trim((string) post('notes')),
        ]);

        $this->success('Registro atualizado.');
        $this->redirect($module);
    }

    public function delete(string $module, string $permission): void
    {
        require_auth();
        require_permission($permission . '.manage');
        csrf_validate();

        $id = (int) post('id');
        $row = $this->items->find($id);

        if ($row && $row['module_name'] === $module) {
            $this->items->delete($id);
            $this->success('Registro removido.');
        }

        $this->redirect($module);
    }

    private function helpAiData(): array
    {
        $userId = (int) (current_user()['id'] ?? 0);
        $sessionId = max(0, (int) request('chat_session_id', 0));
        $historyAvailable = $this->aiChat->featureAvailable();

        $sessions = [];
        $messages = [];

        if ($historyAvailable) {
            $sessions = $this->aiChat->listSessions($userId, 20);

            if ($sessionId > 0 && !$this->aiChat->findSession($sessionId, $userId)) {
                $sessionId = 0;
            }

            if ($sessionId <= 0 && $sessions !== []) {
                $sessionId = (int) ($sessions[0]['id'] ?? 0);
            }

            if ($sessionId > 0) {
                $messages = $this->aiChat->listMessages($sessionId, $userId, 80);
            }
        }

        return [
            'session_id' => $sessionId,
            'sessions' => $sessions,
            'messages' => $messages,
            'history_available' => $historyAvailable,
            'ai_enabled' => $this->assistant->isEnabled(),
            'ai_configured' => $this->assistant->isConfigured(),
            'ai_provider' => $this->assistant->provider(),
            'ai_model' => $this->assistant->model(),
        ];
    }
}
