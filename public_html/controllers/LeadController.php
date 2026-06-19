<?php

class LeadController extends BaseController
{
    private LeadModel $leads;
    private StudentModel $students;

    public function __construct()
    {
        $this->leads = new LeadModel();
        $this->students = new StudentModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('leads');

        $filters = [
            'q' => trim((string) request('q', '')),
            'status_id' => request('status_id', ''),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->leads->list($filters, $perPage, $page);

        $this->render('leads/index', [
            'title' => 'Leads',
            'filters' => $filters,
            'leads' => $result['rows'],
            'meta' => $result['meta'],
            'statuses' => $this->leads->statuses(),
            'users' => $this->leads->usersAssignable(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function create(): void
    {
        require_auth();
        require_permission('leads.create');

        $this->render('leads/form', [
            'title' => 'Novo Lead',
            'lead' => null,
            'statuses' => $this->leads->statuses(),
            'users' => $this->leads->usersAssignable(),
            'action' => route('leads/store'),
        ]);
    }

    public function store(): void
    {
        require_auth();
        require_permission('leads.create');
        csrf_validate();

        $data = $this->collectFormData();
        if ($data['full_name'] === '') {
            $this->error('Nome e obrigatório.');
            $this->redirect('leads/create');
        }

        $this->leads->create($data, (int) current_user()['id']);
        $this->success('Lead criado.');
        $this->redirect('leads');
    }

    public function edit(): void
    {
        require_auth();
        require_permission('leads.edit');

        $id = (int) request('id');
        $lead = $this->leads->find($id);

        if (!$lead) {
            $this->error('Lead não encontrado.');
            $this->redirect('leads');
        }

        $this->render('leads/form', [
            'title' => 'Editar Lead',
            'lead' => $lead,
            'statuses' => $this->leads->statuses(),
            'users' => $this->leads->usersAssignable(),
            'history' => $this->leads->history($id),
            'action' => route('leads/update&id=' . $id),
        ]);
    }

    public function update(): void
    {
        require_auth();
        require_permission('leads.edit');
        csrf_validate();

        $id = (int) request('id');
        $lead = $this->leads->find($id);

        if (!$lead) {
            $this->error('Lead não encontrado.');
            $this->redirect('leads');
        }

        $data = $this->collectFormData();
        $this->leads->update($id, $data, (int) current_user()['id']);

        $this->success('Lead atualizado.');
        $this->redirect('leads/edit&id=' . $id);
    }

    public function delete(): void
    {
        require_auth();
        require_permission('leads.delete');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->leads->delete($id);
            $this->success('Lead removido.');
        }

        $this->redirect('leads');
    }

    public function setStatus(): void
    {
        require_auth();
        require_permission('leads.status');
        csrf_validate();

        $id = (int) post('id');
        $statusId = (int) post('lead_status_id');

        if ($id > 0 && $statusId > 0) {
            $this->leads->setStatus($id, $statusId, (int) current_user()['id'], 'Status alterado na tabela de leads.');
            $this->success('Status atualizado.');
        }

        $this->redirect('leads');
    }

    public function bulk(): void
    {
        require_auth();
        require_permission('leads.bulk');
        csrf_validate();

        $ids = (array) post('ids', []);
        $action = trim((string) post('bulk_action'));
        $statusId = post('bulk_status_id') !== '' ? (int) post('bulk_status_id') : null;

        $affected = $this->leads->bulkAction($ids, $action, $statusId, (int) current_user()['id']);
        $this->success($affected . ' lead(s) afetado(s).');
        $this->redirect('leads');
    }

    public function addHistory(): void
    {
        require_auth();
        require_permission('leads.edit');
        csrf_validate();

        $id = (int) post('lead_id');
        $note = trim((string) post('interaction'));

        if ($id > 0 && $note !== '') {
            $this->leads->addHistory($id, $note, (int) current_user()['id']);
            $this->success('Interacao registrada.');
        }

        $this->redirect('leads/edit&id=' . $id);
    }

    public function convert(): void
    {
        require_auth();
        require_permission('leads.convert');
        csrf_validate();

        $id = (int) post('id');
        $lead = $this->leads->find($id);

        if (!$lead) {
            $this->error('Lead não encontrado.');
            $this->redirect('leads');
        }

        if (!empty($lead['converted_student_id'])) {
            $this->error('Lead ja convertido.');
            $this->redirect('leads');
        }

        $studentId = $this->students->createFromLead($lead, (int) current_user()['id']);
        $this->leads->setConverted($id, $studentId, (int) current_user()['id']);

        $this->success('Lead convertido em aluno #' . $studentId . '.');
        $this->redirect('students/show&id=' . $studentId);
    }

    public function exportCsv(): void
    {
        require_auth();
        require_permission('leads.export');

        $result = $this->leads->list(['q' => trim((string) request('q', ''))], 10000, 1);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leads_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Nome', 'Email', 'Telefone', 'Valor', 'Fonte', 'Status', 'Unidade', 'Tags', 'Ultimo contato'], ';');

        foreach ($result['rows'] as $l) {
            fputcsv($out, [
                $l['id'],
                $l['full_name'],
                $l['email'],
                $l['phone'],
                $l['lead_value'],
                $l['source'],
                $l['status_name'],
                $l['unit_name'],
                $l['tags'],
                $l['last_contact_at'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function statusSettings(): void
    {
        require_auth();
        require_permission('leads.settings');

        $this->render('leads/settings', [
            'title' => 'Configurar Pipeline',
            'statuses' => $this->leads->statuses(),
        ]);
    }

    public function storeStatus(): void
    {
        require_auth();
        require_permission('leads.settings');
        csrf_validate();

        $this->leads->createStatus([
            'name' => trim((string) post('name')),
            'color' => trim((string) post('color', '#6366f1')),
            'display_order' => (int) post('display_order', 99),
            'is_default' => post('is_default') ? 1 : 0,
        ]);

        $this->success('Status criado.');
        $this->redirect('leads/settings');
    }

    public function updateStatusConfig(): void
    {
        require_auth();
        require_permission('leads.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->leads->updateStatusConfig($id, [
                'name' => trim((string) post('name')),
                'color' => trim((string) post('color', '#6366f1')),
                'display_order' => (int) post('display_order', 99),
                'is_default' => post('is_default') ? 1 : 0,
            ]);
            $this->success('Status atualizado.');
        }

        $this->redirect('leads/settings');
    }

    public function deleteStatusConfig(): void
    {
        require_auth();
        require_permission('leads.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->leads->deleteStatusConfig($id);
            $this->success('Status removido.');
        }

        $this->redirect('leads/settings');
    }

    private function collectFormData(): array
    {
        return [
            'full_name' => trim((string) post('full_name')),
            'email' => trim((string) post('email')),
            'phone' => trim((string) post('phone')),
            'lead_value' => parse_decimal((string) post('lead_value', '0')),
            'assigned_to' => post('assigned_to'),
            'source' => trim((string) post('source')),
            'lead_status_id' => post('lead_status_id'),
            'unit_name' => trim((string) post('unit_name')),
            'tags' => trim((string) post('tags')),
            'last_contact_at' => trim((string) post('last_contact_at')),
        ];
    }
}
