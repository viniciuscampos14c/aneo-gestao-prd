<?php

class ArsenalController extends BaseController
{
    private ArsenalModel $arsenal;

    public function __construct()
    {
        $this->arsenal = new ArsenalModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('arsenal');

        $allowedTabs = ['items', 'categories', 'bindings', 'access'];
        $tab = trim((string) request('tab', 'items'));
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'items';
        }

        $featureAvailable = $this->arsenal->featureAvailable();
        $stats = $featureAvailable ? $this->arsenal->stats() : [
            'items_total' => 0,
            'items_published' => 0,
            'categories_total' => 0,
            'links_total' => 0,
            'files_total' => 0,
        ];

        $itemFilters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
            'material_type' => trim((string) request('material_type', '')),
            'visibility_scope' => trim((string) request('visibility_scope', '')),
            'category_id' => trim((string) request('category_id', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $itemsResult = $featureAvailable
            ? $this->arsenal->listItems($itemFilters, $perPage, $page)
            : ['rows' => [], 'meta' => pagination_meta(0, $perPage, $page)];

        $categories = $featureAvailable ? $this->arsenal->listCategories() : [];

        $editId = (int) request('edit_id');
        $editingItem = ($featureAvailable && $editId > 0) ? $this->arsenal->findItem($editId) : null;

        $bindingItemId = (int) request('item_id');
        if ($bindingItemId <= 0 && $editingItem) {
            $bindingItemId = (int) ($editingItem['id'] ?? 0);
        }
        $bindingItem = ($featureAvailable && $bindingItemId > 0) ? $this->arsenal->findItem($bindingItemId) : null;

        $bindingCourses = ($featureAvailable && $bindingItem) ? $this->arsenal->listItemCourseBindings((int) $bindingItem['id']) : [];
        $bindingStudents = ($featureAvailable && $bindingItem) ? $this->arsenal->listItemStudentBindings((int) $bindingItem['id']) : [];
        $availableCourses = ($featureAvailable && $bindingItem) ? $this->arsenal->listCoursesForBinding() : [];
        $availableStudents = ($featureAvailable && $bindingItem) ? $this->arsenal->listStudentsForBinding() : [];
        $allItemsForBinding = $featureAvailable ? $this->arsenal->listItems([], 1000, 1)['rows'] : [];

        $accessFilters = [
            'q' => trim((string) request('log_q', '')),
            'action' => trim((string) request('log_action', '')),
        ];
        $accessPerPage = (int) request('log_per_page', 50);
        if (!in_array($accessPerPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $accessPerPage = 50;
        }
        $accessPage = max(1, (int) request('log_page', 1));

        $accessResult = $featureAvailable
            ? $this->arsenal->listAccessLogs($accessFilters, $accessPerPage, $accessPage)
            : ['rows' => [], 'meta' => pagination_meta(0, $accessPerPage, $accessPage)];

        $this->render('arsenal/index', [
            'title' => 'Arsenal Digital',
            'tab' => $tab,
            'featureAvailable' => $featureAvailable,
            'stats' => $stats,
            'items' => $itemsResult['rows'],
            'itemsMeta' => $itemsResult['meta'],
            'itemFilters' => $itemFilters,
            'categories' => $categories,
            'editingItem' => $editingItem,
            'bindingItem' => $bindingItem,
            'bindingCourses' => $bindingCourses,
            'bindingStudents' => $bindingStudents,
            'availableCourses' => $availableCourses,
            'availableStudents' => $availableStudents,
            'allItemsForBinding' => $allItemsForBinding,
            'accessRows' => $accessResult['rows'],
            'accessMeta' => $accessResult['meta'],
            'accessFilters' => $accessFilters,
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function storeItem(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        if (!$this->arsenal->featureAvailable()) {
            $this->error('Módulo Arsenal indisponivel no banco. Execute a migração 20260313_arsenal_digital.sql.');
            $this->redirect('arsenal');
        }

        $data = $this->collectItemData();
        $validationError = $this->validateItemData($data);
        if ($validationError !== null) {
            $this->error($validationError);
            $this->redirect('arsenal&tab=items');
        }

        if ($data['material_type'] === 'file') {
            $upload = $this->handleArsenalUpload($_FILES['material_file'] ?? null);
            if (!$upload['ok']) {
                $this->error((string) $upload['message']);
                $this->redirect('arsenal&tab=items');
            }

            $data['file_name'] = (string) $upload['file_name'];
            $data['file_path'] = (string) $upload['file_path'];
            $data['file_type'] = (string) $upload['file_type'];
            $data['file_size'] = (int) $upload['file_size'];
            $data['external_url'] = '';
        } else {
            $data['file_name'] = '';
            $data['file_path'] = '';
            $data['file_type'] = '';
            $data['file_size'] = 0;
        }

        try {
            $id = $this->arsenal->createItem($data, (int) current_user()['id']);
            if ($id <= 0) {
                $this->error('Não foi possível criar item do Arsenal.');
                $this->redirect('arsenal&tab=items');
            }
        } catch (Throwable $e) {
            if (!empty($data['file_path'])) {
                $this->safeRemoveArsenalFile((string) $data['file_path']);
            }
            $this->error('Falha ao salvar item do Arsenal. Verifique os dados e tente novamente.');
            $this->redirect('arsenal&tab=items');
        }

        $this->success('Item do Arsenal criado com sucesso.');
        $this->redirect('arsenal&tab=items');
    }

    public function updateItem(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $id = (int) post('id');
        $item = $this->arsenal->findItem($id);
        if (!$item) {
            $this->error('Item do Arsenal não encontrado.');
            $this->redirect('arsenal&tab=items');
        }

        $data = $this->collectItemData();
        $validationError = $this->validateItemData($data);
        if ($validationError !== null) {
            $this->error($validationError);
            $this->redirect('arsenal&tab=items&edit_id=' . $id);
        }

        $oldFilePath = trim((string) ($item['file_path'] ?? ''));
        $removeOldFile = false;

        if ($data['material_type'] === 'file') {
            $uploadedSomething = !empty($_FILES['material_file']['name']);

            if ($uploadedSomething) {
                $upload = $this->handleArsenalUpload($_FILES['material_file'] ?? null);
                if (!$upload['ok']) {
                    $this->error((string) $upload['message']);
                    $this->redirect('arsenal&tab=items&edit_id=' . $id);
                }

                $data['file_name'] = (string) $upload['file_name'];
                $data['file_path'] = (string) $upload['file_path'];
                $data['file_type'] = (string) $upload['file_type'];
                $data['file_size'] = (int) $upload['file_size'];
                $data['external_url'] = '';
                $removeOldFile = $oldFilePath !== '' && $oldFilePath !== $data['file_path'];
            } else {
                $data['file_name'] = (string) ($item['file_name'] ?? '');
                $data['file_path'] = $oldFilePath;
                $data['file_type'] = (string) ($item['file_type'] ?? '');
                $data['file_size'] = (int) ($item['file_size'] ?? 0);
                $data['external_url'] = '';

                if ($data['file_path'] === '') {
                    $this->error('Envie um arquivo para esse item.');
                    $this->redirect('arsenal&tab=items&edit_id=' . $id);
                }
            }
        } else {
            $data['file_name'] = '';
            $data['file_path'] = '';
            $data['file_type'] = '';
            $data['file_size'] = 0;
            $removeOldFile = $oldFilePath !== '';
        }

        try {
            $this->arsenal->updateItem($id, $data);
        } catch (Throwable $e) {
            if (!empty($data['file_path']) && $data['file_path'] !== $oldFilePath) {
                $this->safeRemoveArsenalFile((string) $data['file_path']);
            }
            $this->error('Falha ao atualizar item do Arsenal.');
            $this->redirect('arsenal&tab=items&edit_id=' . $id);
        }

        if ($removeOldFile) {
            $this->safeRemoveArsenalFile($oldFilePath);
        }

        $this->success('Item do Arsenal atualizado.');
        $this->redirect('arsenal&tab=items');
    }

    public function deleteItem(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $id = (int) post('id');
        $item = $this->arsenal->findItem($id);
        if (!$item) {
            $this->error('Item não encontrado para exclusão.');
            $this->redirect('arsenal&tab=items');
        }

        $filePath = trim((string) ($item['file_path'] ?? ''));
        $this->arsenal->deleteItem($id);
        if ($filePath !== '') {
            $this->safeRemoveArsenalFile($filePath);
        }

        $this->success('Item removido do Arsenal.');
        $this->redirect('arsenal&tab=items');
    }

    public function storeCategory(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        if (!$this->arsenal->featureAvailable()) {
            $this->error('Módulo Arsenal indisponivel no banco.');
            $this->redirect('arsenal&tab=categories');
        }

        $name = trim((string) post('name'));
        if ($name === '') {
            $this->error('Nome da categoria e obrigatório.');
            $this->redirect('arsenal&tab=categories');
        }

        try {
            $this->arsenal->createCategory([
                'name' => $name,
                'description' => trim((string) post('description')),
                'is_active' => (int) post('is_active', 1) === 1,
            ], (int) current_user()['id']);
            $this->success('Categoria criada.');
        } catch (Throwable $e) {
            $this->error('Falha ao criar categoria. Verifique se o nome ja existe.');
        }

        $this->redirect('arsenal&tab=categories');
    }

    public function updateCategory(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $id = (int) post('id');
        $category = $this->arsenal->findCategory($id);
        if (!$category) {
            $this->error('Categoria não encontrada.');
            $this->redirect('arsenal&tab=categories');
        }

        $name = trim((string) post('name'));
        if ($name === '') {
            $this->error('Nome da categoria e obrigatório.');
            $this->redirect('arsenal&tab=categories');
        }

        try {
            $this->arsenal->updateCategory($id, [
                'name' => $name,
                'description' => trim((string) post('description')),
                'is_active' => (int) post('is_active', 0) === 1,
            ]);
            $this->success('Categoria atualizada.');
        } catch (Throwable $e) {
            $this->error('Falha ao atualizar categoria. Verifique se o nome ja existe.');
        }

        $this->redirect('arsenal&tab=categories');
    }

    public function deleteCategory(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $id = (int) post('id');
        if ($id <= 0) {
            $this->error('Categoria inválida.');
            $this->redirect('arsenal&tab=categories');
        }

        $this->arsenal->deleteCategory($id);
        $this->success('Categoria removida.');
        $this->redirect('arsenal&tab=categories');
    }

    public function bindCourse(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $itemId = (int) post('item_id');
        $courseId = (int) post('course_id');
        $item = $this->arsenal->findItem($itemId);

        if (!$item) {
            $this->error('Item do Arsenal não encontrado.');
            $this->redirect('arsenal&tab=bindings');
        }

        if ((string) ($item['visibility_scope'] ?? '') !== 'course') {
            $this->error('Esse item não utiliza vinculação por curso.');
            $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
        }

        if ($courseId <= 0 || !$this->arsenal->addCourseBinding($itemId, $courseId)) {
            $this->error('Não foi possível vincular curso ao item.');
            $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
        }

        $this->success('Curso vinculado com sucesso.');
        $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
    }

    public function unbindCourse(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $itemId = (int) post('item_id');
        $courseId = (int) post('course_id');
        if ($itemId <= 0 || $courseId <= 0) {
            $this->error('Dados inválidos para desvinculo.');
            $this->redirect('arsenal&tab=bindings');
        }

        $this->arsenal->removeCourseBinding($itemId, $courseId);
        $this->success('Curso desvinculado.');
        $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
    }

    public function bindStudent(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $itemId = (int) post('item_id');
        $studentId = (int) post('student_id');
        $item = $this->arsenal->findItem($itemId);

        if (!$item) {
            $this->error('Item do Arsenal não encontrado.');
            $this->redirect('arsenal&tab=bindings');
        }

        if ((string) ($item['visibility_scope'] ?? '') !== 'student') {
            $this->error('Esse item não utiliza vinculação por aluno.');
            $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
        }

        if ($studentId <= 0 || !$this->arsenal->addStudentBinding($itemId, $studentId)) {
            $this->error('Não foi possível vincular aluno ao item.');
            $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
        }

        $this->success('Aluno vinculado com sucesso.');
        $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
    }

    public function unbindStudent(): void
    {
        require_auth();
        require_permission('arsenal.manage');
        csrf_validate();

        $itemId = (int) post('item_id');
        $studentId = (int) post('student_id');
        if ($itemId <= 0 || $studentId <= 0) {
            $this->error('Dados inválidos para desvinculo.');
            $this->redirect('arsenal&tab=bindings');
        }

        $this->arsenal->removeStudentBinding($itemId, $studentId);
        $this->success('Aluno desvinculado.');
        $this->redirect('arsenal&tab=bindings&item_id=' . $itemId);
    }

    public function download(): void
    {
        require_auth();
        require_permission('arsenal');

        $id = (int) request('id');
        $item = $this->arsenal->findItem($id);
        if (!$item) {
            $this->error('Item do Arsenal não encontrado.');
            $this->redirect('arsenal&tab=items');
        }

        $materialType = (string) ($item['material_type'] ?? 'file');
        if ($materialType === 'link') {
            $url = trim((string) ($item['external_url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                $this->error('URL externa inválida para este item.');
                $this->redirect('arsenal&tab=items');
            }

            header('Location: ' . $url);
            exit;
        }

        $relativePath = trim((string) ($item['file_path'] ?? ''));
        $fullPath = $this->resolveArsenalFilePath($relativePath);
        if ($fullPath === null || !is_file($fullPath)) {
            $this->error('Arquivo não encontrado no servidor.');
            $this->redirect('arsenal&tab=items');
        }

        $downloadName = trim((string) ($item['file_name'] ?? ''));
        if ($downloadName === '') {
            $downloadName = basename($fullPath);
        }

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $mimeType = $this->detectMimeType($fullPath);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . addslashes($downloadName) . '"');
        header('Content-Length: ' . (string) filesize($fullPath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        readfile($fullPath);
        exit;
    }

    private function collectItemData(): array
    {
        return [
            'title' => trim((string) post('title')),
            'description' => trim((string) post('description')),
            'material_type' => trim((string) post('material_type', 'file')),
            'category_id' => (int) post('category_id', 0),
            'external_url' => trim((string) post('external_url')),
            'visibility_scope' => trim((string) post('visibility_scope', 'global')),
            'status' => trim((string) post('status', 'draft')),
            'publish_start_at' => $this->normalizeDateTime((string) post('publish_start_at')),
            'publish_end_at' => $this->normalizeDateTime((string) post('publish_end_at')),
            'sort_order' => (int) post('sort_order', 0),
        ];
    }

    private function validateItemData(array $data): ?string
    {
        if (trim((string) ($data['title'] ?? '')) === '') {
            return 'Título do item e obrigatório.';
        }

        if (!in_array((string) ($data['material_type'] ?? ''), ['file', 'link'], true)) {
            return 'Tipo de material inválido.';
        }

        if (!in_array((string) ($data['visibility_scope'] ?? ''), ['global', 'course', 'student'], true)) {
            return 'Escopo de visibilidade inválido.';
        }

        if (!in_array((string) ($data['status'] ?? ''), ['draft', 'published', 'archived'], true)) {
            return 'Status do item inválido.';
        }

        $start = trim((string) ($data['publish_start_at'] ?? ''));
        $end = trim((string) ($data['publish_end_at'] ?? ''));
        if ($start !== '' && $end !== '' && strtotime($start) > strtotime($end)) {
            return 'Data inicial de publicação não pode ser maior que a data final.';
        }

        if ((string) ($data['material_type'] ?? '') === 'link') {
            $url = trim((string) ($data['external_url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                return 'Informe uma URL valida para material do tipo link.';
            }
        }

        return null;
    }

    private function handleArsenalUpload($file): array
    {
        if (!$file || !isset($file['name']) || trim((string) ($file['name'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Selecione um arquivo para upload no Arsenal.'];
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Falha no upload do arquivo do Arsenal.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return ['ok' => false, 'message' => 'Arquivo inválido para upload.'];
        }

        if ($size > (100 * 1024 * 1024)) {
            return ['ok' => false, 'message' => 'Arquivo acima do limite de 100MB.'];
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'mp4', 'mp3', 'png', 'jpg', 'jpeg', 'webp'];
        if (!in_array($extension, $allowed, true)) {
            return ['ok' => false, 'message' => 'Extensão não permitida no Arsenal.'];
        }

        $targetDir = __DIR__ . '/../uploads/arsenal';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $storedName = 'arsenal_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeOriginal;
        $finalPath = $targetDir . '/' . $storedName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $finalPath)) {
            return ['ok' => false, 'message' => 'Não foi possível salvar arquivo no servidor.'];
        }

        return [
            'ok' => true,
            'message' => 'Arquivo enviado com sucesso.',
            'file_name' => $originalName,
            'file_path' => 'uploads/arsenal/' . $storedName,
            'file_type' => $extension,
            'file_size' => $size,
        ];
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            $normalized .= ':00';
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function safeRemoveArsenalFile(string $relativePath): void
    {
        $fullPath = $this->resolveArsenalFilePath($relativePath);
        if ($fullPath && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function resolveArsenalFilePath(string $relativePath): ?string
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $uploadsBase = realpath(__DIR__ . '/../uploads');
        if (!$uploadsBase) {
            return null;
        }

        $fullPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
        if (!$fullPath) {
            return null;
        }

        if (!str_starts_with($fullPath, $uploadsBase)) {
            return null;
        }

        return $fullPath;
    }

    private function detectMimeType(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if (is_string($mime) && trim($mime) !== '') {
                    return $mime;
                }
            }
        }

        return 'application/octet-stream';
    }
}
