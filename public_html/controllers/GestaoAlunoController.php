<?php

class GestaoAlunoController extends BaseController
{
    private GestaoAlunoModel $gda;
    private FinanceModel $finance;

    public function __construct()
    {
        $this->gda = new GestaoAlunoModel();
        $this->finance = new FinanceModel();
    }

    // -------------------------------------------------------------------------
    // BOARD
    // -------------------------------------------------------------------------

    public function index(): void
    {
        require_auth();
        require_permission('gda');

        $search   = trim((string) request('q', ''));
        $archived = request('archived') === '1';

        if (!$archived) {
            $this->finance->syncFinanceBoardStatuses((int) current_user()['id']);
        }

        $this->render('gestao_aluno/index', [
            'title'    => 'Gestão do Aluno',
            'search'   => $search,
            'archived' => $archived,
            'columns'  => $this->gda->board($search, $archived),
            'users'    => $this->gda->allUsers(),
            'labels'   => $this->gda->allLabels(),
        ]);
    }

    public function calendar(): void
    {
        require_auth();
        require_permission('gda');

        $year  = (int) request('year', (int) date('Y'));
        $month = (int) request('month', (int) date('n'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        $this->render('gestao_aluno/calendar', [
            'title'  => 'Gestão do Aluno - Calendário',
            'year'   => $year,
            'month'  => $month,
            'cards'  => $this->gda->calendarCards($year, $month),
        ]);
    }

    // -------------------------------------------------------------------------
    // MOVIMENTAÇÃO
    // -------------------------------------------------------------------------

    public function move(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId = (int) post('student_id');
        $columnId  = (int) post('column_id');

        if ($studentId <= 0 || $columnId <= 0) {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        try {
            $col = $this->gda->findColumn($columnId);
            if (!$col) {
                $this->json(['ok' => false, 'message' => 'Coluna de destino não encontrada.'], 404);
            }

            $this->gda->moveStudent($studentId, $columnId, (int) current_user()['id']);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            error_log('[GDA_MOVE_ERROR] ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Não foi possível mover o card.'], 500);
        }
    }

    public function reorderCards(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $columnId   = (int) post('column_id');
        $studentIds = post('student_ids');

        if ($columnId <= 0 || !is_array($studentIds)) {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        $ids = array_map('intval', (array) $studentIds);
        $this->gda->reorderCards($columnId, $ids);
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // CARD
    // -------------------------------------------------------------------------

    public function getCard(): void
    {
        require_auth();
        require_permission('gda');

        $id   = (int) request('id');
        $data = $this->gda->getCardData($id);

        if (!$data) {
            $this->json(['ok' => false, 'message' => 'Card não encontrado.'], 404);
        }

        $student = $data;
        $notes = $student['notes'] ?? [];
        $attachments = $student['attachments'] ?? [];
        $history = $student['history'] ?? [];
        $labels = $student['labels'] ?? [];
        $allLabels = $student['all_labels'] ?? [];
        $members = $student['members'] ?? [];
        $allUsers = $student['all_users'] ?? [];
        $checklists = $student['checklists'] ?? [];
        $customFields = $student['custom_fields'] ?? [];
        $allTemplates = $student['all_templates'] ?? ($student['templates'] ?? []);
        $financialSnapshot = $student['financial_snapshot'] ?? ['summary' => [], 'installments' => []];

        unset(
            $student['notes'],
            $student['attachments'],
            $student['history'],
            $student['labels'],
            $student['all_labels'],
            $student['members'],
            $student['all_users'],
            $student['checklists'],
            $student['custom_fields'],
            $student['templates'],
            $student['all_templates'],
            $student['financial_snapshot']
        );

        $this->json([
            'ok' => true,
            'card' => [
                'student' => $student,
                'notes' => $notes,
                'attachments' => $attachments,
                'history' => $history,
                'labels' => $labels,
                'all_labels' => $allLabels,
                'members' => $members,
                'all_users' => $allUsers,
                'checklists' => $checklists,
                'custom_fields' => $customFields,
                'all_templates' => $allTemplates,
                'financial_snapshot' => $financialSnapshot,
            ],
        ]);
    }

    public function updateCardMeta(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId = (int) post('student_id');
        if ($studentId <= 0) {
            $this->json(['ok' => false, 'message' => 'ID inválido.'], 422);
        }

        $allowed = ['gda_priority', 'gda_due_date', 'gda_cover_color', 'gda_description', 'gda_assigned_to'];
        $data    = [];
        foreach ($allowed as $field) {
            $val = post($field);
            if ($val !== null) {
                $data[$field] = $val === '' ? null : $val;
            }
        }

        $this->gda->updateCardMeta($studentId, $data);
        $this->json(['ok' => true]);
    }

    public function archiveCard(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId = (int) post('student_id');
        $archive   = (bool) post('archive', true);

        if ($studentId <= 0) {
            $this->json(['ok' => false, 'message' => 'ID inválido.'], 422);
        }

        $this->gda->archiveCard($studentId, $archive);
        $this->json(['ok' => true]);
    }

    public function getArchived(): void
    {
        require_auth();
        require_permission('gda');

        $this->json(['ok' => true, 'cards' => $this->gda->archivedCards()]);
    }

    // -------------------------------------------------------------------------
    // NOTAS
    // -------------------------------------------------------------------------

    public function saveNote(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $studentId = (int) post('student_id');
        $note      = trim((string) post('note'));

        if ($studentId <= 0 || $note === '') {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        $id = $this->gda->saveNote($studentId, (int) current_user()['id'], $note);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function deleteNote(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteNote($id);
        }
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // ETIQUETAS
    // -------------------------------------------------------------------------

    public function getLabels(): void
    {
        require_auth();
        require_permission('gda');

        $this->json(['ok' => true, 'labels' => $this->gda->allLabels()]);
    }

    public function saveLabel(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $data = [
            'id'            => (int) post('id'),
            'name'          => trim((string) post('name')),
            'color'         => trim((string) post('color', '#3b82f6')),
            'display_order' => (int) post('display_order', 99),
        ];

        if ($data['name'] === '') {
            $this->json(['ok' => false, 'message' => 'Nome da etiqueta obrigatório.'], 422);
        }

        $id = $this->gda->saveLabel($data);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function deleteLabel(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteLabel($id);
        }
        $this->json(['ok' => true]);
    }

    public function setCardLabels(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId = (int) post('student_id');
        $labelIds  = post('label_ids');

        if ($studentId <= 0) {
            $this->json(['ok' => false, 'message' => 'ID inválido.'], 422);
        }

        $ids = is_array($labelIds) ? array_map('intval', $labelIds) : [];
        $this->gda->setCardLabels($studentId, $ids);
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // MEMBROS
    // -------------------------------------------------------------------------

    public function setCardMembers(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId = (int) post('student_id');
        $userIds   = post('user_ids');

        if ($studentId <= 0) {
            $this->json(['ok' => false, 'message' => 'ID inválido.'], 422);
        }

        $ids = is_array($userIds) ? array_map('intval', $userIds) : [];
        $this->gda->setCardMembers($studentId, $ids);
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // CHECKLISTS
    // -------------------------------------------------------------------------

    public function saveChecklist(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $studentId = (int) post('student_id');
        $title     = trim((string) post('title'));

        if ($studentId <= 0 || $title === '') {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        $id = $this->gda->saveChecklist($studentId, $title);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function deleteChecklist(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteChecklist($id);
        }
        $this->json(['ok' => true]);
    }

    public function saveChecklistItem(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $checklistId = (int) post('checklist_id');
        $text        = trim((string) post('text'));

        if ($checklistId <= 0 || $text === '') {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        $id = $this->gda->saveChecklistItem($checklistId, $text);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function toggleChecklistItem(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $id   = (int) post('id');
        $done = (bool) post('done');

        if ($id <= 0) {
            $this->json(['ok' => false, 'message' => 'ID inválido.'], 422);
        }

        $this->gda->toggleChecklistItem($id, $done);
        $this->json(['ok' => true]);
    }

    public function deleteChecklistItem(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteChecklistItem($id);
        }
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // BUSCA + QUICK ADD
    // -------------------------------------------------------------------------

    public function searchStudentsForColumn(): void
    {
        require_auth();
        require_permission('gda');

        $colId = (int) request('col_id');
        $q     = trim((string) request('q'));

        if ($q === '') {
            $this->json(['ok' => true, 'results' => []]);
        }

        $results = $this->gda->searchStudents($q, $colId);
        $this->json(['ok' => true, 'results' => $results]);
    }

    public function quickAddCard(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId = (int) post('student_id');
        $columnId  = (int) post('column_id');

        if ($studentId <= 0 || $columnId <= 0) {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        try {
            $this->gda->quickAddCard($studentId, $columnId);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // ANEXOS
    // -------------------------------------------------------------------------

    public function uploadAttachment(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $studentId = (int) post('student_id');
        if ($studentId <= 0) {
            $this->json(['ok' => false, 'message' => 'ID inválido.'], 422);
        }

        $file = $_FILES['attachment'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'message' => 'Nenhum arquivo enviado ou erro no upload.'], 422);
        }

        $uploaded = $this->handleAttachmentUpload($file);
        if (!$uploaded['ok']) {
            $this->json(['ok' => false, 'message' => $uploaded['message']], 422);
        }

        $id = $this->gda->saveAttachment($studentId, (int) current_user()['id'], $uploaded);
        $this->json(['ok' => true, 'id' => $id, 'file_name' => $uploaded['original_file_name']]);
    }

    public function deleteAttachment(): void
    {
        require_auth();
        require_permission('gda.notes');
        csrf_validate();

        $id = (int) post('id');
        if ($id <= 0) {
            $this->json(['ok' => false, 'message' => 'ID inválido.'], 422);
        }

        $filePath = $this->gda->deleteAttachment($id);
        if ($filePath) {
            $full = __DIR__ . '/../' . $filePath;
            if (is_file($full)) {
                @unlink($full);
            }
        }
        $this->json(['ok' => true]);
    }

    public function downloadAttachment(): void
    {
        require_auth();
        require_permission('gda');

        $id = (int) request('id');
        if ($id <= 0) {
            http_response_code(404);
            exit;
        }

        $att = $this->gda->findAttachment($id);
        if (!$att) {
            http_response_code(404);
            exit;
        }

        $uploadsBase = realpath(__DIR__ . '/../uploads');
        $fullPath    = realpath(__DIR__ . '/../' . $att['file_name']);

        if (!$uploadsBase || !$fullPath || !str_starts_with($fullPath, $uploadsBase)) {
            http_response_code(403);
            exit;
        }

        if (!is_file($fullPath)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . ($att['file_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . addslashes($att['original_file_name']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    // -------------------------------------------------------------------------
    // CAMPOS CUSTOMIZADOS
    // -------------------------------------------------------------------------

    public function getCustomFields(): void
    {
        require_auth();
        require_permission('gda');

        $this->json(['ok' => true, 'fields' => $this->gda->allCustomFields()]);
    }

    public function saveCustomField(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $data = [
            'id'           => (int) post('id'),
            'name'         => trim((string) post('name')),
            'field_type'   => trim((string) post('field_type', 'text')),
            'options_json' => trim((string) post('options_json')),
            'display_order'=> (int) post('display_order', 99),
        ];

        if ($data['name'] === '') {
            $this->json(['ok' => false, 'message' => 'Nome do campo obrigatório.'], 422);
        }

        $id = $this->gda->saveCustomField($data);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function deleteCustomField(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteCustomField($id);
        }
        $this->json(['ok' => true]);
    }

    public function saveCustomFieldValue(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId = (int) post('student_id');
        $fieldId   = (int) post('field_id');
        $value     = post('value');

        if ($studentId <= 0 || $fieldId <= 0) {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        $this->gda->saveCustomFieldValue($studentId, $fieldId, $value === '' ? null : (string) $value);
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // AUTOMAÇÕES
    // -------------------------------------------------------------------------

    public function getAutomations(): void
    {
        require_auth();
        require_permission('gda.settings');

        $this->json(['ok' => true, 'automations' => $this->gda->allAutomations()]);
    }

    public function saveAutomation(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $data = [
            'id'            => (int) post('id'),
            'name'          => trim((string) post('name')),
            'trigger_type'  => trim((string) post('trigger_type')),
            'trigger_value' => trim((string) post('trigger_value')),
            'action_type'   => trim((string) post('action_type')),
            'action_value'  => trim((string) post('action_value')),
            'is_active'     => post('is_active') ? 1 : 0,
        ];

        if ($data['name'] === '' || $data['trigger_type'] === '' || $data['action_type'] === '') {
            $this->json(['ok' => false, 'message' => 'Campos obrigatórios faltando.'], 422);
        }

        $id = $this->gda->saveAutomation($data);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function deleteAutomation(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteAutomation($id);
        }
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // TEMPLATES
    // -------------------------------------------------------------------------

    public function getTemplates(): void
    {
        require_auth();
        require_permission('gda');

        $this->json(['ok' => true, 'templates' => $this->gda->allTemplates()]);
    }

    public function saveTemplate(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $data = [
            'id'            => (int) post('id'),
            'name'          => trim((string) post('name')),
            'description'   => trim((string) post('description')),
            'priority'      => trim((string) post('priority', 'none')),
            'label_ids'     => post('label_ids'),
            'display_order' => (int) post('display_order', 99),
            'checklists'    => post('checklists'),
        ];

        if ($data['name'] === '') {
            $this->json(['ok' => false, 'message' => 'Nome do template obrigatório.'], 422);
        }

        $id = $this->gda->saveTemplate($data);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function deleteTemplate(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteTemplate($id);
        }
        $this->json(['ok' => true]);
    }

    public function applyTemplate(): void
    {
        require_auth();
        require_permission('gda.move');
        csrf_validate();

        $studentId  = (int) post('student_id');
        $templateId = (int) post('template_id');

        if ($studentId <= 0 || $templateId <= 0) {
            $this->json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        $this->gda->applyTemplate($studentId, $templateId);
        $this->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // CONFIGURAÇÕES (Colunas)
    // -------------------------------------------------------------------------

    public function settings(): void
    {
        require_auth();
        require_permission('gda.settings');

        $this->render('gestao_aluno/partials/settings', [
            'title'       => 'Gestão do Aluno — Configurações',
            'columns'     => $this->gda->allColumns(),
            'labels'      => $this->gda->allLabels(),
            'fields'      => $this->gda->allCustomFields(),
            'automations' => $this->gda->allAutomations(),
            'templates'   => $this->gda->allTemplates(),
        ]);
    }

    public function storeColumn(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $data = [
            'name'          => trim((string) post('name')),
            'color'         => trim((string) post('color', '#0ea5e9')),
            'display_order' => (int) post('display_order', 99),
            'is_default'    => post('is_default') ? 1 : 0,
        ];

        if ($data['name'] === '') {
            $this->error('Nome da coluna obrigatório.');
            $this->redirect('gestao-aluno/settings');
        }

        $this->gda->createColumn($data);
        $this->success('Coluna criada.');
        $this->redirect('gestao-aluno/settings');
    }

    public function updateColumn(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->updateColumn($id, [
                'name'          => trim((string) post('name')),
                'color'         => trim((string) post('color', '#0ea5e9')),
                'display_order' => (int) post('display_order', 99),
                'is_default'    => post('is_default') ? 1 : 0,
            ]);
            $this->success('Coluna atualizada.');
        }

        $this->redirect('gestao-aluno/settings');
    }

    public function deleteColumn(): void
    {
        require_auth();
        require_permission('gda.settings');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->gda->deleteColumn($id);
            $this->success('Coluna removida.');
        }

        $this->redirect('gestao-aluno/settings');
    }

    // -------------------------------------------------------------------------
    // UPLOAD HELPER
    // -------------------------------------------------------------------------

    private function handleAttachmentUpload(array $file): array
    {
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Arquivo inválido.'];
        }

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'message' => 'Falha no upload.'];
        }

        $originalName = basename((string) $file['name']);
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext          = preg_replace('/[^a-z0-9]/', '', $ext);
        $storedName   = uniqid('gda_', true) . ($ext ? '.' . $ext : '');
        $targetDir    = __DIR__ . '/../uploads/gestao_aluno';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return ['ok' => false, 'message' => 'Não foi possível criar a pasta de anexos.'];
        }

        if (!is_writable($targetDir)) {
            return ['ok' => false, 'message' => 'A pasta de anexos não esta com permissão de escrita.'];
        }

        $finalPath = $targetDir . '/' . $storedName;
        if (!move_uploaded_file((string) $file['tmp_name'], $finalPath)) {
            return ['ok' => false, 'message' => 'Não foi possível salvar o arquivo.'];
        }

        return [
            'ok'            => true,
            'file_name'     => 'uploads/gestao_aluno/' . $storedName,
            'original_file_name' => $originalName,
            'file_type'     => (string) ($file['type'] ?? ''),
            'file_size'     => (int) ($file['size'] ?? 0),
        ];
    }
}
