<?php

class StudentController extends BaseController
{
    private StudentModel $students;

    public function __construct()
    {
        $this->students = new StudentModel();
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

        $this->success('Aluno criado com sucesso.');
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

        $this->success('Aluno atualizado com sucesso.');
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
            'kanban_status_id' => post('kanban_status_id') !== '' ? (int) post('kanban_status_id') : null,
        ];
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
