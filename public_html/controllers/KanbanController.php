<?php

class KanbanController extends BaseController
{
    private KanbanModel $kanban;

    public function __construct()
    {
        $this->kanban = new KanbanModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('kanban');

        $search = trim((string) request('q', ''));

        $this->render('kanban/index', [
            'title' => 'Kanban Cliente',
            'search' => $search,
            'columns' => $this->kanban->board($search),
        ]);
    }

    public function move(): void
    {
        require_auth();
        require_permission('kanban.move');

        csrf_validate();

        $studentId = (int) post('student_id');
        $statusId = (int) post('status_id');

        if ($studentId <= 0 || $statusId <= 0) {
            $this->json(['ok' => false, 'message' => 'Parametros inválidos.'], 422);
        }

        try {
            $status = $this->kanban->findStatus($statusId);
            if (!$status) {
                $this->json(['ok' => false, 'message' => 'Status de destino não encontrado.'], 404);
            }

            $this->kanban->moveStudent($studentId, $statusId, (int) current_user()['id']);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            error_log('[KANBAN_MOVE_ERROR] ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Não foi possível mover o card agora.'], 500);
        }
    }

    public function settings(): void
    {
        require_auth();
        require_permission('kanban.settings');

        $this->render('kanban/settings', [
            'title' => 'Configurar Status do Kanban',
            'statuses' => $this->kanban->statuses(),
        ]);
    }

    public function storeStatus(): void
    {
        require_auth();
        require_permission('kanban.settings');
        csrf_validate();

        $this->kanban->createStatus([
            'name' => trim((string) post('name')),
            'color' => trim((string) post('color', '#0ea5e9')),
            'display_order' => (int) post('display_order', 99),
            'is_default' => post('is_default') ? 1 : 0,
        ]);

        $this->success('Status criado.');
        $this->redirect('kanban/settings');
    }

    public function updateStatus(): void
    {
        require_auth();
        require_permission('kanban.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->kanban->updateStatus($id, [
                'name' => trim((string) post('name')),
                'color' => trim((string) post('color', '#0ea5e9')),
                'display_order' => (int) post('display_order', 99),
                'is_default' => post('is_default') ? 1 : 0,
            ]);
            $this->success('Status atualizado.');
        }

        $this->redirect('kanban/settings');
    }

    public function deleteStatus(): void
    {
        require_auth();
        require_permission('kanban.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->kanban->deleteStatus($id);
            $this->success('Status removido.');
        }

        $this->redirect('kanban/settings');
    }
}
