<?php

class ExchangeController extends BaseController
{
    private StudentExchangeModel $exchange;

    public function __construct()
    {
        $this->exchange = new StudentExchangeModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('students');

        $filters = [
            'q'      => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
        ];

        $perPage = 50;
        $page    = max(1, (int) request('page', 1));

        $result  = $this->exchange->listRequests($filters, $perPage, $page);
        $counts  = $this->exchange->countByStatus((int) current_company_id());

        $this->render('exchange/index', [
            'title'             => 'Intercâmbio Aluno',
            'rows'              => $result['rows'],
            'meta'              => $result['meta'],
            'filters'           => $filters,
            'counts'            => $counts,
            'featureAvailable'  => $this->exchange->featureAvailable(),
        ]);
    }

    public function show(): void
    {
        require_auth();
        require_permission('students');

        $id        = max(0, (int) request('id'));
        $companyId = (int) current_company_id();

        $request = $this->exchange->findById($id, $companyId);

        if ($request === null) {
            flash('error', 'Solicitação não encontrada.');
            $this->redirect('exchange');
        }

        // Marca como visualizado se ainda pendente
        $this->exchange->markViewed($id);

        $this->render('exchange/show', [
            'title'   => 'Intercâmbio — Solicitação #' . $id,
            'request' => $request,
        ]);
    }

    public function updateStatus(): void
    {
        require_auth();
        require_permission('students');
        csrf_validate();

        $id        = max(0, (int) post('id'));
        $status    = trim((string) post('status', ''));
        $notes     = trim((string) post('admin_notes', ''));
        $companyId = (int) current_company_id();

        $ok = $this->exchange->updateStatus($id, $status, $notes, $companyId);

        if ($ok) {
            flash('success', 'Status atualizado com sucesso.');
        } else {
            flash('error', 'Não foi possível atualizar o status.');
        }

        $this->redirect('exchange/show?id=' . $id);
    }
}
