<?php

class ExchangeController extends BaseController
{
    private StudentExchangeModel $exchange;
    private StudentPortalModel $portal;

    public function __construct()
    {
        $this->exchange = new StudentExchangeModel();
        $this->portal = new StudentPortalModel();
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

        $result = $this->exchange->listRequests($filters, $perPage, $page);
        $counts = $this->exchange->countByStatus((int) current_company_id());

        $this->render('exchange/index', [
            'title'            => 'Intercambio Aluno',
            'rows'             => $result['rows'],
            'meta'             => $result['meta'],
            'filters'          => $filters,
            'counts'           => $counts,
            'featureAvailable' => $this->exchange->featureAvailable(),
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
            flash('error', 'Solicitacao não encontrada.');
            $this->redirect('exchange');
        }

        // Professor acompanha a solicitação apenas em modo consulta.
        if (!is_professor()) {
            $this->exchange->markViewed($id);
        }

        $this->render('exchange/show', [
            'title'   => 'Intercambio - Solicitacao #' . $id,
            'request' => $request,
            'readOnly' => is_professor(),
        ]);
    }

    public function updateStatus(): void
    {
        require_auth();
        require_permission('students');
        csrf_validate();

        if (is_professor()) {
            flash('error', 'Perfil professor possui acesso somente de visualização nesta área.');
            $this->redirect('exchange/show?id=' . max(0, (int) post('id')));
        }

        $id        = max(0, (int) post('id'));
        $status    = trim((string) post('status', ''));
        $notes     = trim((string) post('admin_notes', ''));
        $companyId = (int) current_company_id();
        $request   = $this->exchange->findById($id, $companyId);

        $ok = $this->exchange->updateStatus($id, $status, $notes, $companyId);

        if ($ok) {
            if (is_array($request)) {
                $this->notifyStudentAboutExchangeStatus($request, $status, $notes);
            }
            flash('success', 'Status atualizado com sucesso.');
        } else {
            flash('error', 'Não foi possível atualizar o status.');
        }

        $this->redirect('exchange/show?id=' . $id);
    }

    private function notifyStudentAboutExchangeStatus(array $request, string $status, string $notes): void
    {
        $studentId = (int) ($request['student_id'] ?? 0);
        $companyId = (int) ($request['company_id'] ?? 0);
        if ($studentId <= 0 || $companyId <= 0 || !$this->portal->studentPortalNotificationsFeatureAvailable()) {
            return;
        }

        $normalizedStatus = trim($status);
        if (!in_array($normalizedStatus, ['viewed', 'approved', 'rejected'], true)) {
            return;
        }

        $targetUnit = trim((string) ($request['target_unit'] ?? ''));

        $title = match ($normalizedStatus) {
            'approved' => 'Intercambio aprovado',
            'rejected' => 'Intercambio recusado',
            'viewed' => 'Intercambio em analise',
            default => 'Atualizacao da solicitacao de intercambio',
        };

        $message = match ($normalizedStatus) {
            'approved' => 'Sua solicitacao de intercambio'
                . ($targetUnit !== '' ? ' para ' . $targetUnit : '')
                . ' foi aprovada pela equipe administrativa.',
            'rejected' => 'Sua solicitacao de intercambio'
                . ($targetUnit !== '' ? ' para ' . $targetUnit : '')
                . ' foi recusada pela equipe administrativa.',
            'viewed' => 'Sua solicitacao de intercambio foi aberta e esta em analise pela equipe administrativa.',
            default => 'Sua solicitação de intercâmbio recebeu uma atualização no painel administrativo.',
        };

        if ($notes !== '') {
            $message .= ' Observacao da equipe: ' . $notes;
        }

        $this->portal->createPortalNotification([
            'company_id' => $companyId,
            'student_id' => $studentId,
            'notification_type' => 'exchange_request',
            'title' => $title,
            'message' => $message,
            'link_url' => route('student/exchange'),
            'meta' => [
                'exchange_request_id' => (int) ($request['id'] ?? 0),
                'status' => $normalizedStatus,
                'target_unit' => $targetUnit,
            ],
        ]);
    }
}
