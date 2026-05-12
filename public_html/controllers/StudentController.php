<?php

class StudentController extends BaseController
{
    private StudentModel $students;
    private FinanceModel $finance;

    public function __construct()
    {
        $this->students = new StudentModel();
        $this->finance = new FinanceModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('students');

        $filters = [
            'q' => trim((string) request('q', '')),
            'is_active' => request('is_active', ''),
            'kanban_status_id' => request('kanban_status_id', ''),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->students->list($filters, $perPage, $page);

        $this->render('students/index', [
            'title' => 'Alunos',
            'filters' => $filters,
            'students' => $result['rows'],
            'meta' => $result['meta'],
            'stats' => $this->students->stats(),
            'statuses' => $this->students->allKanbanStatuses(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function show(): void
    {
        require_auth();
        require_permission('students');

        $id = (int) request('id');
        $student = $this->students->find($id);

        if (!$student) {
            $this->error('Aluno nao encontrado.');
            $this->redirect('students');
        }

        $this->render('students/show', [
            'title' => 'Aluno #' . $id,
            'student' => $student,
            'photoFeatureAvailable' => $this->students->studentPhotoFeatureAvailable(),
            'practiceScheduleAvailable' => $this->students->practiceScheduleFeatureAvailable(),
            'portalAvailable' => $this->students->portalFeatureAvailable(),
            'portalAccount' => $this->students->findPortalAccount($id),
            'documents' => $this->students->documents($id),
            'financeHistory' => $this->students->financialHistory($id),
            'kanbanHistory' => $this->students->kanbanHistory($id),
        ]);
    }

    public function create(): void
    {
        require_auth();
        require_permission('students.create');

        $this->render('students/form', [
            'title' => 'Novo Aluno',
            'student' => null,
            'photoFeatureAvailable' => $this->students->studentPhotoFeatureAvailable(),
            'practiceScheduleAvailable' => $this->students->practiceScheduleFeatureAvailable(),
            'practiceUnits' => $this->students->practiceUnits(),
            'portalAvailable' => $this->students->portalFeatureAvailable(),
            'financialPlanFeatureAvailable' => $this->students->financialPlanFeatureAvailable(),
            'paymentMethods' => $this->finance->paymentMethodsForInvoiceSelection(),
            'paymentMethodsAvailable' => $this->finance->invoicePaymentMethodsAvailable(),
            'portalAccount' => null,
            'statuses' => $this->students->allKanbanStatuses(),
            'flags' => config('student_flags', []),
            'action' => route('students/store'),
        ]);
    }

    public function store(): void
    {
        require_auth();
        require_permission('students.create');
        csrf_validate();

        $data = $this->collectFormData();
        $portal = $this->collectPortalData();

        if ($data['full_name'] === '') {
            $this->error('Nome completo e obrigatorio.');
            $this->redirect('students/create');
        }

        if (!$this->validateFinancialPlanData($data, 'students/create')) {
            return;
        }

        if (!$this->validatePortalData($portal, true, 0, null, 'students/create')) {
            return;
        }

        $id = $this->students->create($data, (int) current_user()['id']);

        if ($this->students->studentPhotoFeatureAvailable()) {
            $photoPath = $this->handleStudentPhotoUpload($id, $_FILES['student_photo'] ?? null, null);
            if ($photoPath !== null) {
                $this->students->updateProfilePhoto($id, $photoPath);
            }
        } elseif (!empty($_FILES['student_photo']['name'])) {
            $this->error('Foto do aluno indisponivel: execute a migracao de perfil de foto no banco.');
        }

        if (!$this->persistPortalData($id, $portal, null, 'students/edit&id=' . $id)) {
            return;
        }

        if (!empty($_FILES['document']['name'])) {
            $this->handleDocumentUpload($id, $_FILES['document']);
        }

        $planMessage = $this->handleFinancialPlanGeneration($id, $data);
        $this->success('Aluno criado com sucesso.' . $planMessage);
        $this->redirect('students/show&id=' . $id);
    }

    public function edit(): void
    {
        require_auth();
        require_permission('students.edit');

        $id = (int) request('id');
        $student = $this->students->find($id);

        if (!$student) {
            $this->error('Aluno nao encontrado.');
            $this->redirect('students');
        }

        $this->render('students/form', [
            'title' => 'Editar Aluno',
            'student' => $student,
            'photoFeatureAvailable' => $this->students->studentPhotoFeatureAvailable(),
            'practiceScheduleAvailable' => $this->students->practiceScheduleFeatureAvailable(),
            'practiceUnits' => $this->students->practiceUnits(),
            'portalAvailable' => $this->students->portalFeatureAvailable(),
            'financialPlanFeatureAvailable' => $this->students->financialPlanFeatureAvailable(),
            'paymentMethods' => $this->finance->paymentMethodsForInvoiceSelection(),
            'paymentMethodsAvailable' => $this->finance->invoicePaymentMethodsAvailable(),
            'portalAccount' => $this->students->findPortalAccount($id),
            'statuses' => $this->students->allKanbanStatuses(),
            'flags' => config('student_flags', []),
            'action' => route('students/update&id=' . $id),
        ]);
    }

    public function update(): void
    {
        require_auth();
        require_permission('students.edit');
        csrf_validate();

        $id = (int) request('id');
        $student = $this->students->find($id);

        if (!$student) {
            $this->error('Aluno nao encontrado.');
            $this->redirect('students');
        }

        $data = $this->collectFormData();
        $portal = $this->collectPortalData();
        $portalAccount = $this->students->findPortalAccount($id);

        if ($data['full_name'] === '') {
            $this->error('Nome completo e obrigatorio.');
            $this->redirect('students/edit&id=' . $id);
        }

        if (!$this->validateFinancialPlanData($data, 'students/edit&id=' . $id)) {
            return;
        }

        if (!$this->validatePortalData($portal, false, $id, $portalAccount, 'students/edit&id=' . $id)) {
            return;
        }

        if ($this->students->studentPhotoFeatureAvailable()) {
            $photoPath = $this->handleStudentPhotoUpload($id, $_FILES['student_photo'] ?? null, (string) ($student['profile_photo'] ?? ''));
            $data['profile_photo'] = $photoPath ?? (string) ($student['profile_photo'] ?? '');
        } else {
            $data['profile_photo'] = '';
            if (!empty($_FILES['student_photo']['name'])) {
                $this->error('Foto do aluno indisponivel: execute a migracao de perfil de foto no banco.');
            }
        }

        $this->students->update($id, $data, (int) current_user()['id']);

        if (!$this->persistPortalData($id, $portal, $portalAccount, 'students/edit&id=' . $id)) {
            return;
        }

        if (!empty($_FILES['document']['name'])) {
            $this->handleDocumentUpload($id, $_FILES['document']);
        }

        $planMessage = $this->handleFinancialPlanGeneration($id, $data);
        $this->success('Aluno atualizado com sucesso.' . $planMessage);
        $this->redirect('students/show&id=' . $id);
    }

    public function delete(): void
    {
        require_auth();
        require_permission('students.delete');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->students->delete($id);
            $this->success('Aluno removido.');
        }

        $this->redirect('students');
    }

    public function toggle(): void
    {
        require_auth();
        require_permission('students.edit');
        csrf_validate();

        $id = (int) post('id');
        $active = (int) post('active', 1);

        if ($id > 0) {
            $this->students->setActive($id, $active);
            $this->success('Status atualizado.');
        }

        $this->redirect('students');
    }

    public function bulk(): void
    {
        require_auth();
        require_permission('students.bulk');
        csrf_validate();

        $ids = post('ids', []);
        $action = (string) post('bulk_action');
        $statusId = post('bulk_status_id') !== '' ? (int) post('bulk_status_id') : null;

        $affected = $this->students->bulkAction((array) $ids, $action, $statusId, (int) current_user()['id']);

        $this->success($affected . ' registro(s) afetado(s).');
        $this->redirect('students');
    }

    public function importCsv(): void
    {
        require_auth();
        require_permission('students.import');
        csrf_validate();

        if (empty($_FILES['csv_file']['tmp_name'])) {
            $this->error('Selecione um CSV valido.');
            $this->redirect('students');
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            $this->error('Falha ao abrir CSV.');
            $this->redirect('students');
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            $this->error('CSV vazio.');
            $this->redirect('students');
        }

        $header = array_map(fn ($v) => strtolower(trim((string) $v)), $header);
        $created = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowData = array_combine($header, $row);
            if (!$rowData || empty($rowData['nome'])) {
                continue;
            }

            $this->students->create([
                'full_name' => trim((string) ($rowData['nome'] ?? '')),
                'primary_contact' => trim((string) ($rowData['contato'] ?? '')),
                'email_primary' => trim((string) ($rowData['email'] ?? '')),
                'phone' => trim((string) ($rowData['telefone'] ?? '')),
                'is_active' => 1,
                'admin_info' => trim((string) ($rowData['informacoes_adm'] ?? '')),
                'ra' => trim((string) ($rowData['ra'] ?? '')),
                'birth_date' => trim((string) ($rowData['data_nascimento'] ?? '')),
                'rg' => trim((string) ($rowData['rg'] ?? '')),
                'cro' => trim((string) ($rowData['cro'] ?? '')),
                'notes' => trim((string) ($rowData['observacoes'] ?? '')),
                'monthly_fee' => parse_decimal((string) ($rowData['mensalidade'] ?? '0')),
                'billing_day' => trim((string) ($rowData['dia_vencimento'] ?? '')),
                'kanban_status_id' => null,
            ], (int) current_user()['id']);

            $created++;
        }

        fclose($handle);

        $this->success($created . ' aluno(s) importado(s).');
        $this->redirect('students');
    }

    public function exportCsv(): void
    {
        require_auth();
        require_permission('students.export');

        $result = $this->students->list(['q' => trim((string) request('q', ''))], 10000, 1);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=alunos_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Nome', 'Contato', 'Email', 'Telefone', 'Status', 'RA', 'Nascimento', 'RG', 'CRO', 'Info Adm'], ';');

        foreach ($result['rows'] as $s) {
            fputcsv($out, [
                $s['id'],
                $s['full_name'],
                $s['primary_contact'],
                $s['email_primary'],
                $s['phone'],
                $s['is_active'] ? 'Ativo' : 'Inativo',
                $s['ra'],
                $s['birth_date'],
                $s['rg'],
                $s['cro'],
                $s['admin_info'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function uploadDocument(): void
    {
        require_auth();
        require_permission('students.edit');
        csrf_validate();

        $id = (int) post('student_id');
        if ($id <= 0 || empty($_FILES['document']['name'])) {
            $this->error('Arquivo nao enviado.');
            $this->redirect('students/show&id=' . $id);
        }

        $this->handleDocumentUpload($id, $_FILES['document']);
        $this->success('Documento anexado com sucesso.');
        $this->redirect('students/show&id=' . $id);
    }

    private function collectFormData(): array
    {
        return [
            'full_name' => trim((string) post('full_name')),
            'primary_contact' => trim((string) post('primary_contact')),
            'email_primary' => trim((string) post('email_primary')),
            'phone' => trim((string) post('phone')),
            'city' => trim((string) post('city')),
            'profile_photo' => trim((string) post('profile_photo_current')),
            'is_active' => (int) post('is_active', 1),
            'admin_info' => trim((string) post('admin_info')),
            'ra' => trim((string) post('ra')),
            'birth_date' => trim((string) post('birth_date')),
            'enrolled_at' => trim((string) post('enrolled_at')),
            'practice_unit_id' => post('practice_unit_id') !== '' ? (int) post('practice_unit_id') : null,
            'residency_level' => strtoupper(trim((string) post('residency_level', 'R1'))),
            'rg' => trim((string) post('rg')),
            'cro' => trim((string) post('cro')),
            'notes' => trim((string) post('notes')),
            'monthly_fee' => parse_decimal((string) post('monthly_fee', '0')),
            'billing_day' => trim((string) post('billing_day')),
            'financial_plan_profile' => trim((string) post('financial_plan_profile')),
            'financial_plan_installments' => post('financial_plan_installments') !== '' ? (int) post('financial_plan_installments') : null,
            'financial_plan_first_due_date' => trim((string) post('financial_plan_first_due_date')),
            'financial_plan_payment_method_id' => post('financial_plan_payment_method_id') !== '' ? (int) post('financial_plan_payment_method_id') : null,
            'financial_plan_auto_generate' => post('financial_plan_auto_generate') ? 1 : 0,
            'financial_plan_boleto_days_before' => post('financial_plan_boleto_days_before') !== '' ? (int) post('financial_plan_boleto_days_before') : 10,
            'financial_plan_generated_at' => trim((string) post('financial_plan_generated_at')),
            'kanban_status_id' => post('kanban_status_id') !== '' ? (int) post('kanban_status_id') : null,
        ];
    }

    private function validateFinancialPlanData(array &$data, string $redirectRoute): bool
    {
        if (!$this->students->financialPlanFeatureAvailable()) {
            $data['financial_plan_profile'] = '';
            $data['financial_plan_installments'] = null;
            $data['financial_plan_first_due_date'] = '';
            $data['financial_plan_payment_method_id'] = null;
            $data['financial_plan_auto_generate'] = 0;
            $data['financial_plan_boleto_days_before'] = 10;
            $data['financial_plan_generated_at'] = trim((string) ($data['financial_plan_generated_at'] ?? ''));
            return true;
        }

        $hasPlanInput = !empty($data['financial_plan_auto_generate'])
            || !empty($data['financial_plan_installments'])
            || !empty($data['financial_plan_first_due_date'])
            || !empty($data['financial_plan_payment_method_id'])
            || trim((string) ($data['financial_plan_profile'] ?? '')) !== '';

        $data['financial_plan_profile'] = $this->normalizeFinancialPlanProfile((string) ($data['financial_plan_profile'] ?? ''));
        $data['financial_plan_boleto_days_before'] = max(0, min(60, (int) ($data['financial_plan_boleto_days_before'] ?? 10)));
        $data['financial_plan_generated_at'] = trim((string) ($data['financial_plan_generated_at'] ?? ''));

        if (!empty($data['financial_plan_first_due_date']) && empty($data['billing_day'])) {
            $data['billing_day'] = date('d', strtotime((string) $data['financial_plan_first_due_date']));
        }

        if (!$hasPlanInput) {
            $data['financial_plan_profile'] = $data['financial_plan_profile'] === 'legacy' ? 'legacy' : '';
            $data['financial_plan_installments'] = null;
            $data['financial_plan_first_due_date'] = '';
            $data['financial_plan_payment_method_id'] = null;
            $data['financial_plan_auto_generate'] = 0;
            return true;
        }

        if ((float) ($data['monthly_fee'] ?? 0) <= 0) {
            $this->error('Informe o valor da parcela para o plano financeiro do aluno.');
            $this->redirect($redirectRoute);
            return false;
        }

        if ((int) ($data['financial_plan_installments'] ?? 0) <= 0) {
            $this->error('Informe a quantidade de parcelas do plano financeiro.');
            $this->redirect($redirectRoute);
            return false;
        }

        if (!$this->isValidDate((string) ($data['financial_plan_first_due_date'] ?? ''))) {
            $this->error('Informe um primeiro vencimento valido para o plano financeiro.');
            $this->redirect($redirectRoute);
            return false;
        }

        $billingDay = (int) ($data['billing_day'] ?? 0);
        if ($billingDay <= 0 || $billingDay > 31) {
            $this->error('Informe um dia de vencimento entre 1 e 31.');
            $this->redirect($redirectRoute);
            return false;
        }

        if ($this->finance->invoicePaymentMethodsAvailable()) {
            $paymentMethodId = (int) ($data['financial_plan_payment_method_id'] ?? 0);
            if ($paymentMethodId <= 0) {
                $this->error('Selecione a forma de pagamento padrao do plano financeiro.');
                $this->redirect($redirectRoute);
                return false;
            }

            $method = $this->finance->findPaymentMethod($paymentMethodId);
            if (!$method || (int) ($method['is_active'] ?? 0) !== 1) {
                $this->error('Forma de pagamento padrao invalida ou inativa.');
                $this->redirect($redirectRoute);
                return false;
            }
        } else {
            $data['financial_plan_payment_method_id'] = null;
        }

        if ($data['financial_plan_profile'] === '') {
            $data['financial_plan_profile'] = 'custom';
        }

        return true;
    }

    private function handleFinancialPlanGeneration(int $studentId, array $data): string
    {
        if (!$this->students->financialPlanFeatureAvailable() || empty($data['financial_plan_auto_generate'])) {
            return '';
        }

        $result = $this->finance->generateStudentFinancialPlan($studentId, (int) current_user()['id']);
        if (!($result['ok'] ?? false)) {
            flash('error', (string) ($result['message'] ?? 'Nao foi possivel gerar o plano financeiro automaticamente.'));
            return '';
        }

        $created = (int) ($result['created'] ?? 0);
        $existing = (int) ($result['existing'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $parts = [];

        if ($created > 0) {
            $parts[] = $created . ' fatura(s) do plano gerada(s)';
        }
        if ($existing > 0) {
            $parts[] = $existing . ' parcela(s) ja existiam';
        }
        if ($failed > 0) {
            flash('error', $failed . ' parcela(s) do plano nao puderam ser geradas automaticamente.');
        }

        return $parts !== [] ? ' Plano financeiro: ' . implode(', ', $parts) . '.' : '';
    }

    private function normalizeFinancialPlanProfile(string $profile): string
    {
        $profile = trim(strtolower($profile));
        $allowed = ['custom', 'preset_36_2200', 'preset_36_2900', 'preset_48_2240_25', 'legacy'];
        return in_array($profile, $allowed, true) ? $profile : '';
    }

    private function isValidDate(string $date): bool
    {
        $date = trim($date);
        if ($date === '') {
            return false;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function collectPortalData(): array
    {
        return [
            'login' => strtolower(trim((string) post('portal_login'))),
            'password' => trim((string) post('portal_password')),
            'is_active' => (int) post('portal_is_active', 0) === 1 ? 1 : 0,
        ];
    }

    private function validatePortalData(array $portal, bool $isCreate, int $studentId, ?array $existingAccount, string $redirectRoute): bool
    {
        $wantsAccount = $existingAccount !== null
            || $portal['login'] !== ''
            || $portal['password'] !== ''
            || (int) $portal['is_active'] === 1;

        if (!$wantsAccount) {
            return true;
        }

        if (!$this->students->portalFeatureAvailable()) {
            $this->error('A tabela de acesso do portal do aluno nao existe. Execute o SQL atualizado antes de salvar esse acesso.');
            $this->redirect($redirectRoute);
            return false;
        }

        if ($portal['login'] === '') {
            $this->error('Informe o login do portal do aluno.');
            $this->redirect($redirectRoute);
            return false;
        }

        if (($isCreate || $existingAccount === null) && $portal['password'] === '') {
            $this->error('Informe a senha inicial do portal do aluno.');
            $this->redirect($redirectRoute);
            return false;
        }

        $conflict = $this->students->findPortalAccountByLogin($portal['login']);
        if ($conflict && (int) $conflict['student_id'] !== $studentId) {
            $this->error('Esse login do portal ja esta em uso por outro aluno.');
            $this->redirect($redirectRoute);
            return false;
        }

        return true;
    }

    private function persistPortalData(int $studentId, array $portal, ?array $existingAccount, string $errorRedirect): bool
    {
        $wantsAccount = $existingAccount !== null
            || $portal['login'] !== ''
            || $portal['password'] !== ''
            || (int) $portal['is_active'] === 1;

        if (!$wantsAccount || !$this->students->portalFeatureAvailable()) {
            return true;
        }

        try {
            $this->students->upsertPortalAccount(
                $studentId,
                $portal['login'],
                $portal['password'] !== '' ? $portal['password'] : null,
                (int) $portal['is_active']
            );
            return true;
        } catch (Throwable $e) {
            $this->error('Falha ao salvar acesso do portal do aluno: ' . $e->getMessage());
            $this->redirect($errorRedirect);
            return false;
        }
    }

    private function handleDocumentUpload(int $studentId, array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return;
        }

        $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowed, true)) {
            return;
        }

        $targetDir = __DIR__ . '/../uploads/documents';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $safeName = 'student_' . $studentId . '_' . date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $file['name']);
        $targetPath = $targetDir . '/' . $safeName;

        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            return;
        }

        $this->students->addDocument(
            $studentId,
            (string) $file['name'],
            'uploads/documents/' . $safeName,
            $extension,
            (int) current_user()['id']
        );
    }

    private function handleStudentPhotoUpload(int $studentId, $file, ?string $currentPhoto): ?string
    {
        if (!$file || !isset($file['name'])) {
            return null;
        }

        $fileName = trim((string) ($file['name'] ?? ''));
        if ($fileName === '') {
            return null;
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->error('Falha no upload da foto do aluno.');
            return ($currentPhoto ?? '') !== '' ? $currentPhoto : null;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $this->error('Foto do aluno invalida. Use PNG, JPG, JPEG ou WEBP.');
            return ($currentPhoto ?? '') !== '' ? $currentPhoto : null;
        }

        $maxSize = 5 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            $this->error('Foto do aluno acima de 5MB.');
            return ($currentPhoto ?? '') !== '' ? $currentPhoto : null;
        }

        $targetDir = __DIR__ . '/../uploads/students';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        $storedFileName = 'student_photo_' . $studentId . '_' . date('YmdHis') . '_' . $safeOriginal;
        $targetPath = $targetDir . '/' . $storedFileName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetPath)) {
            $this->error('Nao foi possivel salvar a foto do aluno.');
            return ($currentPhoto ?? '') !== '' ? $currentPhoto : null;
        }

        $relativePath = 'uploads/students/' . $storedFileName;
        if (($currentPhoto ?? '') !== '' && $currentPhoto !== $relativePath) {
            $this->safeRemoveStudentPhotoFile($currentPhoto);
        }

        return $relativePath;
    }

    private function safeRemoveStudentPhotoFile(string $relativePath): void
    {
        $uploadsBase = realpath(__DIR__ . '/../uploads');
        if (!$uploadsBase) {
            return;
        }

        $fullPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
        if (!$fullPath) {
            return;
        }

        if (!str_starts_with($fullPath, $uploadsBase)) {
            return;
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
