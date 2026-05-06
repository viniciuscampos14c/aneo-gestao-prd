<?php

class DataImportController extends BaseController
{
    private DataImportModel $imports;
    private StudentModel $students;
    private CourseModel $courses;

    private array $types = [
        'students' => [
            'label' => 'Alunos completos',
            'description' => 'Cadastro academico, financeiro basico, escala pratica e acesso ao portal.',
        ],
        'courses_ead' => [
            'label' => 'Cursos EAD com modulos e aulas',
            'description' => 'Cria ou atualiza cursos, categorias, modulos e aulas em video.',
        ],
        'professors' => [
            'label' => 'Professores',
            'description' => 'Cria ou atualiza usuarios do sistema com perfil de professor.',
        ],
        'admin_users' => [
            'label' => 'Usuarios administrativos',
            'description' => 'Cria ou atualiza usuarios admin/suporte e vincula as filiais existentes.',
        ],
        'practice_units' => [
            'label' => 'Unidades / Hospitais',
            'description' => 'Cria ou atualiza unidades de pratica usadas no cadastro de alunos e na Escala Aluno.',
        ],
        'arsenal' => [
            'label' => 'Arsenal Digital',
            'description' => 'Cria ou atualiza categorias e materiais por link/URL no Arsenal Digital.',
        ],
    ];

    public function __construct()
    {
        $this->imports = new DataImportModel();
        $this->students = new StudentModel();
        $this->courses = new CourseModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('data_imports');
        $this->imports->ensureSchema();

        $page = max(1, (int) request('page', 1));
        $result = $this->imports->listBatches(15, $page);
        $batchId = (int) request('batch_id', 0);
        $selectedBatch = $batchId > 0 ? $this->imports->findBatch($batchId) : null;
        $selectedRows = $selectedBatch ? $this->imports->rowsForBatch((int) $selectedBatch['id'], 500) : [];

        $this->render('data_imports/index', [
            'title' => 'Importacao de Dados',
            'types' => $this->types,
            'batches' => $result['rows'],
            'meta' => $result['meta'],
            'selectedBatch' => $selectedBatch,
            'selectedRows' => $selectedRows,
            'canUpload' => has_permission('data_imports.run'),
            'canConfirm' => has_permission('data_imports.confirm'),
        ]);
    }

    public function template(): void
    {
        require_auth();
        require_permission('data_imports');

        $type = (string) request('type', '');
        if (!isset($this->types[$type])) {
            http_response_code(404);
            exit('Modelo nao encontrado.');
        }

        $filename = match ($type) {
            'students' => 'modelo_importacao_alunos.csv',
            'professors' => 'modelo_importacao_professores.csv',
            'admin_users' => 'modelo_importacao_usuarios_administrativos.csv',
            'practice_units' => 'modelo_importacao_unidades_hospitais.csv',
            'arsenal' => 'modelo_importacao_arsenal_digital.csv',
            default => 'modelo_importacao_cursos_ead.csv',
        };

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        foreach ($this->templateRows($type) as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    public function upload(): void
    {
        require_auth();
        require_permission('data_imports.run');
        csrf_validate();
        $this->imports->ensureSchema();

        $type = (string) post('import_type', '');
        if (!isset($this->types[$type])) {
            $this->error('Tipo de importacao invalido.');
            $this->redirect('data-imports');
        }

        if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $this->error('Selecione um arquivo CSV valido.');
            $this->redirect('data-imports');
        }

        $originalName = (string) ($_FILES['csv_file']['name'] ?? 'importacao.csv');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $this->error('Nesta primeira fase, envie arquivos no formato CSV.');
            $this->redirect('data-imports');
        }

        $storedPath = $this->storeUploadedCsv($_FILES['csv_file'], $type);
        if ($storedPath === null) {
            $this->error('Nao foi possivel salvar o arquivo enviado.');
            $this->redirect('data-imports');
        }

        $batchId = $this->imports->createBatch([
            'user_id' => (int) current_user()['id'],
            'import_type' => $type,
            'original_filename' => $originalName,
            'stored_file_path' => $storedPath,
            'status' => 'uploaded',
            'options' => ['mode' => 'create_update'],
        ]);

        try {
            $result = $this->validateCsvFile(__DIR__ . '/../' . $storedPath, $type, $batchId);
            $this->imports->updateBatchValidation($batchId, $result['total'], $result['valid'], $result['errors']);

            if ($result['total'] === 0) {
                $this->imports->markBatchFailed($batchId, 'Arquivo sem linhas de dados.');
                $this->error('Arquivo sem linhas de dados.');
            } elseif ($result['errors'] > 0) {
                $this->error('Arquivo validado com erro(s). Corrija a planilha ou revise as linhas antes de confirmar.');
            } else {
                $this->success('Arquivo validado com sucesso. Revise a previa e confirme a importacao.');
            }
        } catch (Throwable $e) {
            $this->imports->markBatchFailed($batchId, $e->getMessage());
            $this->error('Falha ao validar o CSV: ' . $e->getMessage());
        }

        $this->redirect('data-imports&batch_id=' . $batchId);
    }

    public function confirm(): void
    {
        require_auth();
        require_permission('data_imports.confirm');
        csrf_validate();
        $this->imports->ensureSchema();

        $batchId = (int) post('batch_id', 0);
        $batch = $this->imports->findBatch($batchId);
        if (!$batch) {
            $this->error('Lote de importacao nao encontrado.');
            $this->redirect('data-imports');
        }

        if ((string) $batch['status'] !== 'validated') {
            $this->error('Este lote nao esta pronto para confirmacao.');
            $this->redirect('data-imports&batch_id=' . $batchId);
        }

        if ((int) $batch['error_count'] > 0 || (int) $batch['error_rows'] > 0) {
            $this->error('Corrija as linhas com erro antes de confirmar a importacao.');
            $this->redirect('data-imports&batch_id=' . $batchId);
        }

        $rows = $this->imports->validRowsForBatch($batchId);
        if ($rows === []) {
            $this->error('Nao ha linhas validas para importar.');
            $this->redirect('data-imports&batch_id=' . $batchId);
        }

        $summary = [
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'students_created' => 0,
            'students_updated' => 0,
            'courses_created' => 0,
            'courses_updated' => 0,
            'modules_created' => 0,
            'modules_updated' => 0,
            'lessons_created' => 0,
            'lessons_updated' => 0,
            'professors_created' => 0,
            'professors_updated' => 0,
            'admin_users_created' => 0,
            'admin_users_updated' => 0,
            'practice_units_created' => 0,
            'practice_units_updated' => 0,
            'arsenal_categories_created' => 0,
            'arsenal_categories_updated' => 0,
            'arsenal_items_created' => 0,
            'arsenal_items_updated' => 0,
        ];

        $type = (string) $batch['import_type'];
        $userId = (int) current_user()['id'];

        try {
            $this->imports->beginTransaction();

            foreach ($rows as $row) {
                $data = $this->imports->decodeRowData($row['normalized_data'] ?? null);
                if ($type === 'students') {
                    $imported = $this->importStudentRow($data, $userId);
                    $summary[$imported['action'] === 'create' ? 'students_created' : 'students_updated']++;
                    $this->imports->markRowImported((int) $row['id'], $imported['action'], 'students', (int) $imported['id']);
                    continue;
                }

                if ($type === 'professors') {
                    $imported = $this->importProfessorRow($data);
                    $summary[$imported['action'] === 'create' ? 'professors_created' : 'professors_updated']++;
                    $this->imports->markRowImported((int) $row['id'], $imported['action'], 'users', (int) $imported['id']);
                    continue;
                }

                if ($type === 'admin_users') {
                    $imported = $this->importAdminUserRow($data);
                    $summary[$imported['action'] === 'create' ? 'admin_users_created' : 'admin_users_updated']++;
                    $this->imports->markRowImported((int) $row['id'], $imported['action'], 'users', (int) $imported['id']);
                    continue;
                }

                if ($type === 'practice_units') {
                    $imported = $this->importPracticeUnitRow($data, $userId);
                    $summary[$imported['action'] === 'create' ? 'practice_units_created' : 'practice_units_updated']++;
                    $this->imports->markRowImported((int) $row['id'], $imported['action'], 'student_practice_units', (int) $imported['id']);
                    continue;
                }

                if ($type === 'arsenal') {
                    $imported = $this->importArsenalRow($data, $userId);
                    $summary['arsenal_categories_' . $imported['category_action'] . 'd'] = ($summary['arsenal_categories_' . $imported['category_action'] . 'd'] ?? 0) + 1;
                    $summary['arsenal_items_' . $imported['item_action'] . 'd'] = ($summary['arsenal_items_' . $imported['item_action'] . 'd'] ?? 0) + 1;
                    $this->imports->markRowImported((int) $row['id'], $imported['item_action'], 'arsenal_items', (int) $imported['item_id']);
                    continue;
                }

                if ($type === 'courses_ead') {
                    $imported = $this->importCourseRow($data, $userId);
                    $summary['courses_' . $imported['course_action'] . 'd'] = ($summary['courses_' . $imported['course_action'] . 'd'] ?? 0) + 1;
                    $summary['modules_' . $imported['module_action'] . 'd'] = ($summary['modules_' . $imported['module_action'] . 'd'] ?? 0) + 1;
                    $summary['lessons_' . $imported['lesson_action'] . 'd'] = ($summary['lessons_' . $imported['lesson_action'] . 'd'] ?? 0) + 1;
                    $this->imports->markRowImported((int) $row['id'], $imported['lesson_action'], 'course_lessons', (int) $imported['lesson_id']);
                    continue;
                }
            }

            $summary['created_count'] = $summary['students_created'] + $summary['courses_created'] + $summary['modules_created'] + $summary['lessons_created'] + $summary['professors_created'] + $summary['admin_users_created'] + $summary['practice_units_created'] + $summary['arsenal_categories_created'] + $summary['arsenal_items_created'];
            $summary['updated_count'] = $summary['students_updated'] + $summary['courses_updated'] + $summary['modules_updated'] + $summary['lessons_updated'] + $summary['professors_updated'] + $summary['admin_users_updated'] + $summary['practice_units_updated'] + $summary['arsenal_categories_updated'] + $summary['arsenal_items_updated'];

            $this->imports->completeBatch($batchId, $summary);
            $this->imports->commit();

            $this->success('Importacao concluida com sucesso. Criados: ' . $summary['created_count'] . ' | Atualizados: ' . $summary['updated_count'] . '.');
        } catch (Throwable $e) {
            $this->imports->rollBack();
            $this->error('Importacao cancelada antes de gravar tudo: ' . $e->getMessage());
        }

        $this->redirect('data-imports&batch_id=' . $batchId);
    }

    private function validateCsvFile(string $path, string $type, int $batchId): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('Nao foi possivel abrir o arquivo salvo.');
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            throw new RuntimeException('CSV vazio ou sem cabecalho.');
        }

        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $line = 1;
        $total = 0;
        $valid = 0;
        $errors = 0;
        $seen = [
            'student_email' => [],
            'student_ra' => [],
            'professor_email' => [],
            'professor_username' => [],
            'admin_user_email' => [],
            'admin_user_username' => [],
            'practice_unit_name' => [],
            'arsenal_item' => [],
            'course_lesson' => [],
        ];

        while (($csvRow = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            if ($this->isEmptyCsvRow($csvRow)) {
                continue;
            }

            $raw = $this->combineCsvRow($header, $csvRow);
            $validation = match ($type) {
                'students' => $this->validateStudentRow($raw),
                'professors' => $this->validateProfessorRow($raw),
                'admin_users' => $this->validateAdminUserRow($raw),
                'practice_units' => $this->validatePracticeUnitRow($raw),
                'arsenal' => $this->validateArsenalRow($raw),
                default => $this->validateCourseRow($raw),
            };

            $rowErrors = array_merge(
                $validation['errors'],
                $this->duplicateMessagesForRow($type, $validation['data'], $seen, $line)
            );
            $status = $rowErrors === [] ? 'valid' : 'error';
            $this->imports->insertRow($batchId, [
                'row_number' => $line,
                'source_key' => $validation['source_key'],
                'status' => $status,
                'action' => $validation['action'],
                'raw_data' => $raw,
                'normalized_data' => $validation['data'],
                'errors' => $rowErrors,
                'warnings' => $validation['warnings'],
            ]);

            $total++;
            if ($status === 'valid') {
                $valid++;
            } else {
                $errors++;
            }
        }

        fclose($handle);
        return ['total' => $total, 'valid' => $valid, 'errors' => $errors];
    }

    private function validateStudentRow(array $row): array
    {
        $errors = [];
        $warnings = [];
        $name = $this->field($row, ['nome', 'nome_completo', 'full_name']);
        $email = strtolower($this->field($row, ['email', 'email_principal', 'email_primary']));
        $ra = $this->field($row, ['ra', 'registro_academico']);
        $phone = $this->field($row, ['telefone', 'phone', 'celular']);
        $contact = $this->field($row, ['contato', 'primary_contact', 'contato_principal']);

        if ($name === '') {
            $errors[] = 'Informe o nome do aluno.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email principal invalido.';
        }

        $companyInput = $this->field($row, ['filial_id', 'id_filial', 'codigo_filial', 'empresa_id', 'company_id', 'filial', 'empresa', 'nome_filial', 'cnpj_filial']);
        $targetCompany = $this->resolveImportCompany($companyInput, $errors);
        $targetCompanyId = (int) ($targetCompany['id'] ?? current_company_id() ?? 0);

        $birthDate = $this->parseDate($this->field($row, ['data_nascimento', 'nascimento']), 'Data de nascimento', $errors);
        $enrolledAt = $this->parseDate($this->field($row, ['data_entrada', 'enrolled_at']), 'Data de entrada', $errors);
        [$isActive, $statusProvided, $statusOk] = $this->parseStatus($this->field($row, ['status', 'situacao']));
        if (!$statusOk) {
            $errors[] = 'Status invalido. Use Ativo ou Inativo.';
        }

        $monthlyRaw = $this->field($row, ['mensalidade', 'monthly_fee']);
        $billingRaw = $this->field($row, ['dia_vencimento', 'billing_day']);
        $billingDay = null;
        if ($billingRaw !== '') {
            $billingDay = (int) preg_replace('/\D+/', '', $billingRaw);
            if ($billingDay < 1 || $billingDay > 31) {
                $errors[] = 'Dia de vencimento deve estar entre 1 e 31.';
            }
        }

        $unitName = $this->field($row, ['unidade_pratica', 'hospital', 'unidade_hospital']);
        $practiceUnitId = null;
        if ($unitName !== '') {
            if (!$this->students->practiceScheduleFeatureAvailable()) {
                $warnings[] = 'Campos de escala pratica indisponiveis no banco; unidade sera ignorada.';
            } else {
                $unit = $this->imports->findPracticeUnitByName($unitName, $targetCompanyId);
                if (!$unit) {
                    $errors[] = 'Unidade pratica nao encontrada: ' . $unitName . '.';
                } else {
                    $practiceUnitId = (int) $unit['id'];
                }
            }
        }

        $residencyRaw = strtoupper($this->field($row, ['nivel_residencia', 'residencia', 'r']));
        $residencyLevel = $residencyRaw !== '' ? $residencyRaw : 'R1';
        if (!in_array($residencyLevel, ['R1', 'R2', 'R3'], true)) {
            $errors[] = 'Nivel de residencia invalido. Use R1, R2 ou R3.';
        }

        $portalLogin = strtolower($this->field($row, ['login_portal', 'portal_login']));
        $portalPassword = $this->field($row, ['senha_inicial', 'senha_portal', 'portal_password']);
        $portalActiveRaw = $this->field($row, ['portal_ativo', 'acesso_portal_ativo']);
        $portalActive = $this->parseBool($portalActiveRaw, false) ? 1 : 0;
        $wantsPortal = $portalLogin !== '' || $portalPassword !== '' || $portalActiveRaw !== '';

        $existing = $this->imports->findStudentCandidate($email, $ra, $targetCompanyId);
        $action = $existing ? 'update' : 'create';
        if ($wantsPortal) {
            if (!$this->students->portalFeatureAvailable()) {
                $errors[] = 'Portal do aluno indisponivel no banco.';
            } elseif ($portalLogin === '') {
                $errors[] = 'Informe login_portal para criar/atualizar o acesso do aluno.';
            } else {
                $portalConflict = $this->imports->findStudentPortalAccountByLogin($portalLogin, $targetCompanyId);
                $expectedStudentId = $existing ? (int) $existing['id'] : 0;
                if ($portalConflict && (int) $portalConflict['student_id'] !== $expectedStudentId) {
                    $errors[] = 'Login do portal ja esta em uso por outro aluno: ' . $portalLogin . '.';
                }
                if (!$existing && $portalPassword === '' && $portalActive === 1) {
                    $errors[] = 'Informe senha_inicial para novo acesso ativo no portal.';
                }
            }
        }

        $sourceKey = $email !== '' ? $email : ($ra !== '' ? $ra : $this->slug($name));

        return [
            'source_key' => $sourceKey,
            'action' => $action,
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'full_name' => $name,
                'primary_contact' => $contact,
                'email_primary' => $email,
                'phone' => $phone,
                'city' => $this->field($row, ['cidade', 'city', 'cidade_aluno']),
                'company_id' => $targetCompanyId,
                'company_label' => (string) ($targetCompany['trade_name'] ?? $targetCompany['legal_name'] ?? ''),
                'is_active' => $isActive,
                'status_provided' => $statusProvided,
                'admin_info' => $this->field($row, ['informacoes_adm', 'admin_info', 'tags']),
                'ra' => $ra,
                'birth_date' => $birthDate,
                'enrolled_at' => $enrolledAt,
                'rg' => $this->field($row, ['rg']),
                'cro' => $this->field($row, ['cro']),
                'notes' => $this->field($row, ['observacoes', 'notes']),
                'monthly_fee' => $monthlyRaw !== '' ? parse_decimal($monthlyRaw) : 0,
                'monthly_fee_provided' => $monthlyRaw !== '',
                'billing_day' => $billingDay,
                'billing_day_provided' => $billingRaw !== '',
                'kanban_status_id' => null,
                'practice_unit_id' => $practiceUnitId,
                'practice_unit_provided' => $unitName !== '' && $practiceUnitId !== null,
                'residency_level' => $residencyLevel,
                'residency_provided' => $residencyRaw !== '',
                'portal_login' => $portalLogin,
                'portal_password' => $portalPassword,
                'portal_is_active' => $portalActive,
                'portal_provided' => $wantsPortal,
            ],
        ];
    }

    private function validateProfessorRow(array $row): array
    {
        $errors = [];
        $warnings = [];
        $name = $this->field($row, ['nome', 'nome_completo', 'name']);
        $email = strtolower($this->field($row, ['email', 'email_principal']));
        $username = strtolower($this->field($row, ['usuario', 'username', 'login']));
        $password = $this->field($row, ['senha_inicial', 'senha', 'password']);

        if ($name === '') {
            $errors[] = 'Informe o nome do professor.';
        }

        if ($email === '') {
            $errors[] = 'Informe o email do professor.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email do professor invalido.';
        }

        if ($username === '' && $email !== '') {
            $username = strtolower((string) strtok($email, '@'));
        }

        $username = $this->normalizeUsername($username);
        if ($username === '') {
            $errors[] = 'Informe usuario/login do professor.';
        } elseif (strlen($username) > 80) {
            $errors[] = 'Usuario/login deve ter no maximo 80 caracteres.';
        }

        [$isActive, $statusProvided, $statusOk] = $this->parseStatus($this->field($row, ['status', 'situacao']));
        if (!$statusOk) {
            $errors[] = 'Status invalido. Use Ativo ou Inativo.';
        }

        $emailUser = $email !== '' ? $this->imports->findUserByEmail($email) : null;
        $usernameUser = $username !== '' ? $this->imports->findUserByUsername($username) : null;
        $existing = $emailUser ?: $usernameUser;

        if ($emailUser && $usernameUser && (int) $emailUser['id'] !== (int) $usernameUser['id']) {
            $errors[] = 'Email e usuario pertencem a usuarios diferentes. Corrija a planilha antes de importar.';
        }

        if ($existing && (string) ($existing['role'] ?? '') !== 'professor') {
            $errors[] = 'Usuario ja existe com perfil ' . (string) ($existing['role'] ?? '-') . '. Ajuste manualmente para evitar alterar admin/suporte por importacao.';
        }

        if ($existing && (string) ($existing['email'] ?? '') !== '' && $email !== '' && strtolower((string) $existing['email']) !== $email) {
            $warnings[] = 'O email do usuario sera atualizado para ' . $email . '.';
        }

        if (!$existing && strlen($password) < 6) {
            $errors[] = 'Informe senha_inicial com ao menos 6 caracteres para novo professor.';
        }

        if ($existing && $password !== '' && strlen($password) < 6) {
            $errors[] = 'Quando informada, senha_inicial deve ter ao menos 6 caracteres.';
        }

        return [
            'source_key' => $email !== '' ? $email : $username,
            'action' => $existing ? 'update' : 'create',
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'role' => 'professor',
                'is_active' => $isActive,
                'status_provided' => $statusProvided,
                'notes' => $this->field($row, ['observacoes', 'notes']),
            ],
        ];
    }

    private function validateAdminUserRow(array $row): array
    {
        $errors = [];
        $warnings = [];
        $name = $this->field($row, ['nome', 'nome_completo', 'name']);
        $email = strtolower($this->field($row, ['email', 'email_principal']));
        $username = strtolower($this->field($row, ['usuario', 'username', 'login']));
        $password = $this->field($row, ['senha_inicial', 'senha', 'password']);
        $role = strtolower($this->normalizeHeader($this->field($row, ['perfil', 'role'])));

        if ($name === '') {
            $errors[] = 'Informe o nome do usuario.';
        }

        if ($email === '') {
            $errors[] = 'Informe o email do usuario.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email do usuario invalido.';
        }

        if ($username === '' && $email !== '') {
            $username = strtolower((string) strtok($email, '@'));
        }

        $username = $this->normalizeUsername($username);
        if ($username === '') {
            $errors[] = 'Informe usuario/login.';
        } elseif (strlen($username) > 80) {
            $errors[] = 'Usuario/login deve ter no maximo 80 caracteres.';
        }

        if ($role === '' || in_array($role, ['administrador', 'administrator'], true)) {
            $role = 'admin';
        }
        if (!in_array($role, ['admin', 'suporte'], true)) {
            $errors[] = 'Perfil invalido para usuarios administrativos. Use Admin ou Suporte.';
        }

        [$isActive, $statusProvided, $statusOk] = $this->parseStatus($this->field($row, ['status', 'situacao']));
        if (!$statusOk) {
            $errors[] = 'Status invalido. Use Ativo ou Inativo.';
        }

        $emailUser = $email !== '' ? $this->imports->findUserByEmail($email) : null;
        $usernameUser = $username !== '' ? $this->imports->findUserByUsername($username) : null;
        $existing = $emailUser ?: $usernameUser;

        if ($emailUser && $usernameUser && (int) $emailUser['id'] !== (int) $usernameUser['id']) {
            $errors[] = 'Email e usuario pertencem a usuarios diferentes. Corrija a planilha antes de importar.';
        }

        if ($existing && (string) ($existing['role'] ?? '') === 'professor') {
            $errors[] = 'Usuario ja existe como professor. Ajuste manualmente para evitar alterar perfil por importacao administrativa.';
        }

        if (!$existing && strlen($password) < 6) {
            $errors[] = 'Informe senha_inicial com ao menos 6 caracteres para novo usuario.';
        }

        if ($existing && $password !== '' && strlen($password) < 6) {
            $errors[] = 'Quando informada, senha_inicial deve ter ao menos 6 caracteres.';
        }

        $companyRefs = $this->field($row, ['filiais_ids', 'filial_ids', 'empresas_ids', 'company_ids', 'filiais', 'empresas']);
        $companyIds = $this->resolveCompanyRefs($companyRefs, $errors);
        if ($companyIds === []) {
            $companyIds = [(int) (current_company_id() ?? 0)];
        }
        $companyIds = array_values(array_filter(array_unique(array_map('intval', $companyIds)), fn ($id) => $id > 0));
        if ($companyIds === []) {
            $errors[] = 'Nao foi possivel definir a filial/empresa do usuario.';
        }

        $permissions = [];
        if ($role === 'suporte') {
            $permissions = $this->parsePermissionKeys($this->field($row, ['permissoes', 'permissions']), $warnings);
        }

        return [
            'source_key' => $email !== '' ? $email : $username,
            'action' => $existing ? 'update' : 'create',
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'is_active' => $isActive,
                'status_provided' => $statusProvided,
                'company_ids' => $companyIds,
                'permissions' => $permissions,
            ],
        ];
    }

    private function validatePracticeUnitRow(array $row): array
    {
        $errors = [];
        $warnings = [];
        if (!$this->imports->practiceUnitsFeatureAvailable()) {
            $errors[] = 'Modulo de unidades/hospitais indisponivel no banco. Execute a migracao da Escala Aluno.';
        }

        $name = $this->field($row, ['nome', 'unidade', 'hospital', 'nome_unidade', 'name']);
        $city = $this->field($row, ['cidade', 'city']);
        $state = strtoupper($this->field($row, ['uf', 'estado', 'state']));

        if ($name === '') {
            $errors[] = 'Informe o nome da unidade/hospital.';
        }

        if ($state !== '' && strlen($state) > 10) {
            $errors[] = 'UF/estado deve ter no maximo 10 caracteres.';
        }

        [$isActive, $statusProvided, $statusOk] = $this->parseStatus($this->field($row, ['status', 'situacao']));
        if (!$statusOk) {
            $errors[] = 'Status invalido. Use Ativo ou Inativo.';
        }

        $existing = $name !== '' ? $this->imports->findPracticeUnitByName($name) : null;
        if ($existing && (int) ($existing['is_active'] ?? 1) === 0 && $statusProvided === false) {
            $warnings[] = 'Unidade ja existe como inativa; sem coluna status ela permanecera inativa.';
        }

        return [
            'source_key' => $this->slug($name),
            'action' => $existing ? 'update' : 'create',
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'name' => $name,
                'city' => $city,
                'state' => $state,
                'is_active' => $isActive,
                'status_provided' => $statusProvided,
            ],
        ];
    }

    private function validateArsenalRow(array $row): array
    {
        $errors = [];
        $warnings = [];
        if (!$this->imports->arsenalFeatureAvailable()) {
            $errors[] = 'Modulo Arsenal Digital indisponivel no banco. Execute a migracao 20260313_arsenal_digital.sql.';
        }

        $sourceKey = $this->field($row, ['codigo_material', 'codigo', 'source_key']);
        $categoryName = $this->field($row, ['categoria', 'category']);
        $title = $this->field($row, ['titulo', 'title', 'nome']);
        $description = $this->field($row, ['descricao', 'description']);
        $url = $this->field($row, ['url', 'link', 'external_url']);
        $sortOrderRaw = $this->field($row, ['ordem', 'sort_order']);
        $sortOrder = $sortOrderRaw !== '' ? (int) preg_replace('/\D+/', '', $sortOrderRaw) : 0;

        if ($categoryName === '') {
            $errors[] = 'Informe a categoria do material.';
        }

        if ($title === '') {
            $errors[] = 'Informe o titulo do material.';
        }

        [$materialType, $materialTypeOk] = $this->parseArsenalMaterialType($this->field($row, ['tipo', 'tipo_material', 'material_type']));
        if (!$materialTypeOk) {
            $errors[] = 'Tipo do material invalido. Nesta fase use Link.';
        }

        if ($materialType !== 'link') {
            $errors[] = 'Nesta fase a importacao do Arsenal aceita apenas materiais por link/URL.';
        }

        if ($url === '') {
            $errors[] = 'Informe a URL/link do material.';
        } elseif (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'URL/link do material invalido.';
        }

        [$visibilityScope, $visibilityOk] = $this->parseArsenalVisibilityScope($this->field($row, ['escopo', 'visibilidade', 'visibility_scope']));
        if (!$visibilityOk) {
            $errors[] = 'Escopo invalido. Use Global, Curso ou Aluno.';
        }

        if ($visibilityScope !== 'global') {
            $warnings[] = 'O material sera criado com escopo ' . $visibilityScope . ', mas vinculos com curso/aluno devem ser feitos na tela do Arsenal.';
        }

        [$status, $statusOk] = $this->parseArsenalStatus($this->field($row, ['status', 'situacao']));
        if (!$statusOk) {
            $errors[] = 'Status invalido. Use Rascunho, Publicado ou Arquivado.';
        }

        $publishStart = $this->parseDateTime($this->field($row, ['publicar_inicio', 'publish_start_at']), 'Inicio da publicacao', $errors);
        $publishEnd = $this->parseDateTime($this->field($row, ['publicar_fim', 'publish_end_at']), 'Fim da publicacao', $errors);

        $sourceKey = $sourceKey !== '' ? $sourceKey : $this->slug($categoryName . '-' . $title);
        $existing = $title !== '' ? $this->imports->findArsenalItemCandidate($sourceKey, $categoryName, $title) : null;

        return [
            'source_key' => $sourceKey,
            'action' => $existing ? 'update' : 'create',
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'source_key' => $sourceKey,
                'category_name' => $categoryName,
                'category_description' => $this->field($row, ['descricao_categoria', 'category_description']),
                'title' => $title,
                'description' => $description,
                'material_type' => $materialType,
                'external_url' => $url,
                'visibility_scope' => $visibilityScope,
                'status' => $status,
                'publish_start_at' => $publishStart,
                'publish_end_at' => $publishEnd,
                'sort_order' => $sortOrder,
            ],
        ];
    }

    private function validateCourseRow(array $row): array
    {
        $errors = [];
        $warnings = [];
        if (!$this->courses->lmsFeatureAvailable()) {
            $errors[] = 'LMS modular indisponivel no banco. Execute a migration de modulos/aulas.';
        }

        $code = $this->field($row, ['codigo_curso', 'course_code', 'codigo']);
        $courseName = $this->field($row, ['nome_curso', 'curso', 'name']);
        $moduleTitle = $this->field($row, ['nome_modulo', 'modulo', 'module_title']);
        $lessonTitle = $this->field($row, ['nome_aula', 'aula', 'lesson_title']);
        $videoUrl = $this->field($row, ['url_video', 'video_url', 'link_video']);

        if ($courseName === '') {
            $errors[] = 'Informe nome_curso.';
        }
        if ($moduleTitle === '') {
            $errors[] = 'Informe nome_modulo.';
        }
        if ($lessonTitle === '') {
            $errors[] = 'Informe nome_aula.';
        }
        if ($videoUrl === '') {
            $errors[] = 'Informe url_video.';
        }

        [$status, $statusOk] = $this->parseCourseStatus($this->field($row, ['status', 'situacao']));
        if (!$statusOk) {
            $errors[] = 'Status do curso invalido. Use Rascunho ou Publicado.';
        }

        $moduleOrder = max(1, (int) ($this->field($row, ['ordem_modulo', 'module_order']) ?: 1));
        $lessonOrder = max(1, (int) ($this->field($row, ['ordem_aula', 'lesson_order']) ?: 1));
        $progress = (int) ($this->field($row, ['progresso_minimo', 'min_progress_percent']) ?: 70);
        if ($progress < 1 || $progress > 100) {
            $errors[] = 'Progresso minimo deve estar entre 1 e 100.';
        }

        $liveDate = $this->parseDateTime($this->field($row, ['data_aula_ao_vivo', 'live_datetime']), 'Data da aula ao vivo', $errors);
        $sourceKey = $code !== '' ? $code : $this->slug($courseName);
        $existing = $this->imports->findCourseCandidate($sourceKey, $courseName);

        return [
            'source_key' => $sourceKey,
            'action' => $existing ? 'update' : 'create',
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'source_key' => $sourceKey,
                'name' => $courseName,
                'category' => $this->field($row, ['categoria', 'category']),
                'description' => $this->field($row, ['descricao_curso', 'description']),
                'cover_image' => $this->field($row, ['capa', 'cover_image']),
                'status' => $status,
                'workload_hours' => $this->field($row, ['carga_horaria', 'workload_hours']),
                'curriculum' => $this->field($row, ['curriculo', 'curriculum']),
                'materials' => $this->field($row, ['materiais', 'materials']),
                'live_link' => $this->field($row, ['link_ao_vivo', 'live_link']),
                'live_password' => $this->field($row, ['senha_ao_vivo', 'live_password']),
                'live_meeting_id' => $this->field($row, ['id_reuniao', 'live_meeting_id']),
                'live_datetime' => $liveDate,
                'module' => [
                    'title' => $moduleTitle,
                    'description' => $this->field($row, ['descricao_modulo', 'module_description']),
                    'display_order' => $moduleOrder,
                    'is_active' => $this->parseBool($this->field($row, ['modulo_ativo', 'module_active']), true) ? 1 : 0,
                ],
                'lesson' => [
                    'title' => $lessonTitle,
                    'description' => $this->field($row, ['descricao_aula', 'lesson_description']),
                    'video_url' => $videoUrl,
                    'duration_seconds' => $this->durationToSeconds($this->field($row, ['duracao_minutos', 'duration_minutes'])),
                    'min_progress_percent' => $progress,
                    'display_order' => $lessonOrder,
                    'is_required' => $this->parseBool($this->field($row, ['aula_obrigatoria', 'lesson_required']), true) ? 1 : 0,
                    'is_active' => $this->parseBool($this->field($row, ['aula_ativa', 'lesson_active']), true) ? 1 : 0,
                ],
            ],
        ];
    }

    private function duplicateMessagesForRow(string $type, array $data, array &$seen, int $line): array
    {
        if ($type === 'students') {
            return $this->studentDuplicateMessages($data, $seen, $line);
        }

        if ($type === 'professors') {
            return $this->professorDuplicateMessages($data, $seen, $line);
        }

        if ($type === 'admin_users') {
            return $this->adminUserDuplicateMessages($data, $seen, $line);
        }

        if ($type === 'practice_units') {
            return $this->practiceUnitDuplicateMessages($data, $seen, $line);
        }

        if ($type === 'arsenal') {
            return $this->arsenalDuplicateMessages($data, $seen, $line);
        }

        if ($type === 'courses_ead') {
            return $this->courseDuplicateMessages($data, $seen, $line);
        }

        return [];
    }

    private function studentDuplicateMessages(array $data, array &$seen, int $line): array
    {
        $errors = [];
        $email = strtolower(trim((string) ($data['email_primary'] ?? '')));
        $ra = strtolower(trim((string) ($data['ra'] ?? '')));
        $companyKey = (string) ((int) ($data['company_id'] ?? current_company_id() ?? 0));

        if ($email !== '') {
            $key = $companyKey . '|' . $email;
            if (isset($seen['student_email'][$key])) {
                $errors[] = 'Email duplicado na planilha para a mesma filial: ' . $email . '. Ja aparece na linha ' . $seen['student_email'][$key] . '.';
            } else {
                $seen['student_email'][$key] = $line;
            }
        }

        if ($ra !== '') {
            $key = $companyKey . '|' . $ra;
            if (isset($seen['student_ra'][$key])) {
                $errors[] = 'RA duplicado na planilha para a mesma filial: ' . (string) ($data['ra'] ?? '') . '. Ja aparece na linha ' . $seen['student_ra'][$key] . '.';
            } else {
                $seen['student_ra'][$key] = $line;
            }
        }

        return $errors;
    }

    private function professorDuplicateMessages(array $data, array &$seen, int $line): array
    {
        $errors = [];
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $username = strtolower(trim((string) ($data['username'] ?? '')));

        if ($email !== '') {
            if (isset($seen['professor_email'][$email])) {
                $errors[] = 'Email duplicado na planilha: ' . $email . '. Ja aparece na linha ' . $seen['professor_email'][$email] . '.';
            } else {
                $seen['professor_email'][$email] = $line;
            }
        }

        if ($username !== '') {
            if (isset($seen['professor_username'][$username])) {
                $errors[] = 'Usuario/login duplicado na planilha: ' . $username . '. Ja aparece na linha ' . $seen['professor_username'][$username] . '.';
            } else {
                $seen['professor_username'][$username] = $line;
            }
        }

        return $errors;
    }

    private function adminUserDuplicateMessages(array $data, array &$seen, int $line): array
    {
        $errors = [];
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $username = strtolower(trim((string) ($data['username'] ?? '')));

        if ($email !== '') {
            if (isset($seen['admin_user_email'][$email])) {
                $errors[] = 'Email duplicado na planilha: ' . $email . '. Ja aparece na linha ' . $seen['admin_user_email'][$email] . '.';
            } else {
                $seen['admin_user_email'][$email] = $line;
            }
        }

        if ($username !== '') {
            if (isset($seen['admin_user_username'][$username])) {
                $errors[] = 'Usuario/login duplicado na planilha: ' . $username . '. Ja aparece na linha ' . $seen['admin_user_username'][$username] . '.';
            } else {
                $seen['admin_user_username'][$username] = $line;
            }
        }

        return $errors;
    }

    private function practiceUnitDuplicateMessages(array $data, array &$seen, int $line): array
    {
        $name = strtolower(trim((string) ($data['name'] ?? '')));
        if ($name === '') {
            return [];
        }

        $key = $this->slug($name);
        if (isset($seen['practice_unit_name'][$key])) {
            return [
                'Unidade/hospital duplicado na planilha: ' . (string) ($data['name'] ?? '') . '. Ja aparece na linha ' . $seen['practice_unit_name'][$key] . '.',
            ];
        }

        $seen['practice_unit_name'][$key] = $line;
        return [];
    }

    private function arsenalDuplicateMessages(array $data, array &$seen, int $line): array
    {
        $category = strtolower(trim((string) ($data['category_name'] ?? '')));
        $title = strtolower(trim((string) ($data['title'] ?? '')));
        if ($category === '' || $title === '') {
            return [];
        }

        $key = $this->slug($category . '-' . $title);
        if (isset($seen['arsenal_item'][$key])) {
            return [
                'Material duplicado na planilha para a mesma categoria: ' . (string) ($data['title'] ?? '') . '. Ja aparece na linha ' . $seen['arsenal_item'][$key] . '.',
            ];
        }

        $seen['arsenal_item'][$key] = $line;
        return [];
    }

    private function courseDuplicateMessages(array $data, array &$seen, int $line): array
    {
        $courseKey = strtolower(trim((string) ($data['source_key'] ?? $data['name'] ?? '')));
        $module = (array) ($data['module'] ?? []);
        $lesson = (array) ($data['lesson'] ?? []);
        $moduleKey = strtolower(trim((string) ($module['title'] ?? ''))) ?: (string) ($module['display_order'] ?? '');
        $lessonKey = strtolower(trim((string) ($lesson['title'] ?? ''))) ?: (string) ($lesson['display_order'] ?? '');

        if ($courseKey === '' || $moduleKey === '' || $lessonKey === '') {
            return [];
        }

        $key = $courseKey . '|' . $moduleKey . '|' . $lessonKey;
        if (isset($seen['course_lesson'][$key])) {
            return [
                'Aula duplicada na planilha para o mesmo curso/modulo: ' . ($lesson['title'] ?? $lessonKey) . '. Ja aparece na linha ' . $seen['course_lesson'][$key] . '.',
            ];
        }

        $seen['course_lesson'][$key] = $line;
        return [];
    }

    private function importStudentRow(array $data, int $userId): array
    {
        return $this->imports->upsertStudent($data, $userId);
    }

    private function importProfessorRow(array $data): array
    {
        return $this->imports->upsertProfessorUser($data);
    }

    private function importAdminUserRow(array $data): array
    {
        return $this->imports->upsertAdministrativeUser($data);
    }

    private function importPracticeUnitRow(array $data, int $userId): array
    {
        return $this->imports->upsertPracticeUnit($data, $userId);
    }

    private function importArsenalRow(array $data, int $userId): array
    {
        return $this->imports->upsertArsenalItem($data, $userId);
    }

    private function importCourseRow(array $data, int $userId): array
    {
        $course = $this->imports->upsertCourse($data, $userId);
        $module = $this->imports->upsertCourseModule((int) $course['id'], (array) ($data['module'] ?? []), $userId);
        $lesson = $this->imports->upsertCourseLesson((int) $course['id'], (int) $module['id'], (array) ($data['lesson'] ?? []), $userId);

        return [
            'course_id' => (int) $course['id'],
            'course_action' => (string) $course['action'],
            'module_id' => (int) $module['id'],
            'module_action' => (string) $module['action'],
            'lesson_id' => (int) $lesson['id'],
            'lesson_action' => (string) $lesson['action'],
        ];
    }

    private function mergeStudentPayload(array $existing, array $data): array
    {
        $keep = fn (string $key, $default = '') => ($data[$key] ?? '') !== '' && $data[$key] !== null ? $data[$key] : ($existing[$key] ?? $default);

        return [
            'full_name' => (string) ($data['full_name'] ?? $existing['full_name'] ?? ''),
            'primary_contact' => $keep('primary_contact'),
            'email_primary' => $keep('email_primary'),
            'phone' => $keep('phone'),
            'profile_photo' => (string) ($existing['profile_photo'] ?? ''),
            'is_active' => !empty($data['status_provided']) ? (int) $data['is_active'] : (int) ($existing['is_active'] ?? 1),
            'admin_info' => $keep('admin_info'),
            'ra' => $keep('ra'),
            'birth_date' => $keep('birth_date', null),
            'enrolled_at' => $keep('enrolled_at', null),
            'rg' => $keep('rg'),
            'cro' => $keep('cro'),
            'notes' => $keep('notes'),
            'monthly_fee' => !empty($data['monthly_fee_provided']) ? (float) $data['monthly_fee'] : (float) ($existing['monthly_fee'] ?? 0),
            'billing_day' => !empty($data['billing_day_provided']) ? ($data['billing_day'] ?? '') : ($existing['billing_day'] ?? ''),
            'kanban_status_id' => $existing['kanban_status_id'] ?? null,
            'practice_unit_id' => !empty($data['practice_unit_provided']) ? (int) $data['practice_unit_id'] : ($existing['practice_unit_id'] ?? null),
            'residency_level' => !empty($data['residency_provided']) ? (string) $data['residency_level'] : (string) ($existing['residency_level'] ?? 'R1'),
        ];
    }

    private function storeUploadedCsv(array $file, string $type): ?string
    {
        $companyId = (int) current_company_id();
        $dir = __DIR__ . '/../uploads/data_imports/' . $companyId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $safeOriginal = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', (string) ($file['name'] ?? 'import.csv'));
        $filename = $type . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeOriginal;
        $target = $dir . '/' . $filename;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return null;
        }

        return 'uploads/data_imports/' . $companyId . '/' . $filename;
    }

    private function templateRows(string $type): array
    {
        if ($type === 'students') {
            return [
                ['filial_id', 'nome_filial', 'cidade', 'nome', 'email', 'telefone', 'contato', 'ra', 'data_nascimento', 'rg', 'cro', 'data_entrada', 'unidade_pratica', 'nivel_residencia', 'mensalidade', 'dia_vencimento', 'status', 'informacoes_adm', 'observacoes', 'login_portal', 'senha_inicial', 'portal_ativo'],
                ['1', 'ANEO Brasil', 'Brasilia', 'Maria Exemplo', 'maria@example.com', '61999990000', 'Maria', 'RA-001', '10/01/1990', '123456', 'CRO-DF-1234', '01/02/2026', 'Hospital Brasilia Teste', 'R1', '1200,00', '10', 'Ativo', 'Migracao sistema antigo', 'Observacao livre', 'maria.exemplo', '123456', 'Sim'],
            ];
        }

        if ($type === 'professors') {
            return [
                ['nome', 'email', 'usuario', 'senha_inicial', 'status', 'observacoes'],
                ['Professor Exemplo', 'professor@example.com', 'professor.exemplo', '123456', 'Ativo', 'Usuario professor importado do sistema antigo'],
            ];
        }

        if ($type === 'admin_users') {
            return [
                ['nome', 'email', 'usuario', 'senha_inicial', 'perfil', 'filiais_ids', 'permissoes', 'status'],
                ['Usuario Suporte', 'suporte@example.com', 'suporte.exemplo', '123456', 'Suporte', '1', 'dashboard;students;courses;arsenal', 'Ativo'],
                ['Administrador Unidade', 'admin.unidade@example.com', 'admin.unidade', '123456', 'Admin', '1', '', 'Ativo'],
            ];
        }

        if ($type === 'practice_units') {
            return [
                ['nome', 'cidade', 'uf', 'status'],
                ['Hospital Brasilia Teste', 'Brasilia', 'DF', 'Ativo'],
                ['Hospital Minas Teste', 'Belo Horizonte', 'MG', 'Ativo'],
            ];
        }

        if ($type === 'arsenal') {
            return [
                ['codigo_material', 'categoria', 'descricao_categoria', 'titulo', 'descricao', 'tipo', 'url', 'escopo', 'status', 'ordem', 'publicar_inicio', 'publicar_fim'],
                ['MAT-001', 'Apostilas', 'Materiais de apoio em PDF e links externos', 'Manual do Aluno', 'Material inicial para orientacao do aluno', 'Link', 'https://aneobrasil.com.br/', 'Global', 'Publicado', '1', '', ''],
            ];
        }

        return [
            ['codigo_curso', 'nome_curso', 'categoria', 'status', 'carga_horaria', 'descricao_curso', 'curriculo', 'materiais', 'link_ao_vivo', 'senha_ao_vivo', 'id_reuniao', 'data_aula_ao_vivo', 'ordem_modulo', 'nome_modulo', 'descricao_modulo', 'modulo_ativo', 'ordem_aula', 'nome_aula', 'descricao_aula', 'url_video', 'duracao_minutos', 'progresso_minimo', 'aula_obrigatoria', 'aula_ativa'],
            ['CUR-IMPLANTE-01', 'Curso de Implantodontia', 'Implantodontia', 'Publicado', '40', 'Curso completo de implantodontia.', 'Grade resumida', 'Apostilas no Arsenal', '', '', '', '', '1', 'Modulo 1 - Fundamentos', 'Base teorica inicial.', 'Sim', '1', 'Aula 1 - Apresentacao', 'Boas-vindas e objetivos.', 'https://www.youtube.com/watch?v=VIDEO', '15', '70', 'Sim', 'Sim'],
        ];
    }

    private function combineCsvRow(array $header, array $row): array
    {
        $row = array_pad($row, count($header), '');
        $combined = [];
        foreach ($header as $index => $key) {
            if ($key === '') {
                continue;
            }
            $combined[$key] = trim((string) ($row[$index] ?? ''));
        }
        return $combined;
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function field(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (array_key_exists($normalized, $row)) {
                return trim((string) $row[$normalized]);
            }
        }
        return '';
    }

    private function resolveImportCompany(string $value, array &$errors): array
    {
        $value = trim($value);
        $company = $value !== ''
            ? $this->imports->findCompanyByImportRef($value)
            : current_company();

        if (!$company) {
            $errors[] = 'Filial/empresa nao encontrada: ' . ($value !== '' ? $value : 'empresa atual') . '.';
            return [];
        }

        $companyId = (int) ($company['id'] ?? 0);
        if ($companyId <= 0 || !has_company_access($companyId)) {
            $errors[] = 'Usuario logado nao tem acesso a filial/empresa informada: ' . ($value !== '' ? $value : (string) ($company['legal_name'] ?? $companyId)) . '.';
            return [];
        }

        return $company;
    }

    private function resolveCompanyRefs(string $value, array &$errors): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $ids = [];
        $parts = preg_split('/[;,|]+/', $value) ?: [];
        foreach ($parts as $part) {
            $ref = trim((string) $part);
            if ($ref === '') {
                continue;
            }

            $company = $this->imports->findCompanyByImportRef($ref);
            if (!$company) {
                $errors[] = 'Filial/empresa nao encontrada: ' . $ref . '.';
                continue;
            }

            $companyId = (int) ($company['id'] ?? 0);
            if ($companyId <= 0 || !has_company_access($companyId)) {
                $errors[] = 'Usuario logado nao tem acesso a filial/empresa informada: ' . $ref . '.';
                continue;
            }

            $ids[] = $companyId;
        }

        return array_values(array_unique($ids));
    }

    private function parsePermissionKeys(string $value, array &$warnings): array
    {
        $catalog = config('permissions_catalog.modules', []) + config('permissions_catalog.functions', []);
        $value = trim($value);
        if ($value === '') {
            return array_values(array_filter(array_map('strval', config('roles.suporte.permissions', ['dashboard', 'help']))));
        }

        if ($value === '*') {
            return array_keys($catalog);
        }

        $clean = [];
        $parts = preg_split('/[;,|]+/', $value) ?: [];
        foreach ($parts as $part) {
            $permission = trim((string) $part);
            if ($permission === '') {
                continue;
            }
            if (!isset($catalog[$permission])) {
                $warnings[] = 'Permissao ignorada por nao existir no catalogo: ' . $permission . '.';
                continue;
            }
            $clean[] = $permission;
        }

        if ($clean === []) {
            $clean = array_values(array_filter(array_map('strval', config('roles.suporte.permissions', ['dashboard', 'help']))));
        }

        return array_values(array_unique($clean));
    }

    private function normalizeUsername(string $value): string
    {
        $value = strtolower(trim($value));
        $value = strtr($value, [
            'Ã¡' => 'a', 'Ã ' => 'a', 'Ã£' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a',
            'Ã©' => 'e', 'Ãª' => 'e', 'Ã¨' => 'e', 'Ã«' => 'e',
            'Ã­' => 'i', 'Ã¬' => 'i', 'Ã®' => 'i', 'Ã¯' => 'i',
            'Ã³' => 'o', 'Ã²' => 'o', 'Ãµ' => 'o', 'Ã´' => 'o', 'Ã¶' => 'o',
            'Ãº' => 'u', 'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
            'Ã§' => 'c', 'Ã±' => 'n',
            'Ã' => 'a', 'Ã€' => 'a', 'Ãƒ' => 'a', 'Ã‚' => 'a', 'Ã„' => 'a',
            'Ã‰' => 'e', 'ÃŠ' => 'e', 'Ãˆ' => 'e', 'Ã‹' => 'e',
            'Ã' => 'i', 'ÃŒ' => 'i', 'ÃŽ' => 'i', 'Ã' => 'i',
            'Ã“' => 'o', 'Ã’' => 'o', 'Ã•' => 'o', 'Ã”' => 'o', 'Ã–' => 'o',
            'Ãš' => 'u', 'Ã™' => 'u', 'Ã›' => 'u', 'Ãœ' => 'u',
            'Ã‡' => 'c', 'Ã‘' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9_.-]+/', '.', $value);
        return trim((string) $value, '.-_');
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value));
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'Ä' => 'a',
            'É' => 'e', 'Ê' => 'e', 'È' => 'e', 'Ë' => 'e',
            'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
            'Ó' => 'o', 'Ò' => 'o', 'Õ' => 'o', 'Ô' => 'o', 'Ö' => 'o',
            'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c', 'Ñ' => 'n',
        ]);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function parseDate(string $value, string $label, array &$errors): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        $errors[] = $label . ' invalida. Use dd/mm/aaaa ou aaaa-mm-dd.';
        return null;
    }

    private function parseDateTime(string $value, string $label, array &$errors): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $errors[] = $label . ' invalida. Use dd/mm/aaaa hh:mm ou aaaa-mm-dd hh:mm.';
        return null;
    }

    private function parseStatus(string $value): array
    {
        $value = strtolower($this->normalizeHeader($value));
        if ($value === '') {
            return [1, false, true];
        }
        if (in_array($value, ['ativo', 'active', 'sim', 'yes', '1'], true)) {
            return [1, true, true];
        }
        if (in_array($value, ['inativo', 'inactive', 'nao', 'no', '0'], true)) {
            return [0, true, true];
        }
        return [1, true, false];
    }

    private function parseCourseStatus(string $value): array
    {
        $value = strtolower($this->normalizeHeader($value));
        if ($value === '' || in_array($value, ['rascunho', 'draft'], true)) {
            return ['draft', true];
        }
        if (in_array($value, ['publicado', 'published', 'ativo', 'active'], true)) {
            return ['published', true];
        }
        return ['draft', false];
    }

    private function parseArsenalStatus(string $value): array
    {
        $value = strtolower($this->normalizeHeader($value));
        if ($value === '' || in_array($value, ['rascunho', 'draft'], true)) {
            return ['draft', true];
        }
        if (in_array($value, ['publicado', 'published', 'ativo', 'active'], true)) {
            return ['published', true];
        }
        if (in_array($value, ['arquivado', 'archived', 'inativo', 'inactive'], true)) {
            return ['archived', true];
        }
        return ['draft', false];
    }

    private function parseArsenalMaterialType(string $value): array
    {
        $value = strtolower($this->normalizeHeader($value));
        if ($value === '' || in_array($value, ['link', 'url'], true)) {
            return ['link', true];
        }
        if (in_array($value, ['arquivo', 'file'], true)) {
            return ['file', true];
        }
        return ['link', false];
    }

    private function parseArsenalVisibilityScope(string $value): array
    {
        $value = strtolower($this->normalizeHeader($value));
        if ($value === '' || in_array($value, ['global', 'todos', 'geral'], true)) {
            return ['global', true];
        }
        if (in_array($value, ['curso', 'course'], true)) {
            return ['course', true];
        }
        if (in_array($value, ['aluno', 'student'], true)) {
            return ['student', true];
        }
        return ['global', false];
    }

    private function parseBool(string $value, bool $default): bool
    {
        $value = strtolower($this->normalizeHeader($value));
        if ($value === '') {
            return $default;
        }
        if (in_array($value, ['sim', 'yes', 'ativo', 'active', 'publicado', '1'], true)) {
            return true;
        }
        if (in_array($value, ['nao', 'no', 'inativo', 'inactive', '0'], true)) {
            return false;
        }
        return $default;
    }

    private function durationToSeconds(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $minutes = (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $value));
        return $minutes > 0 ? (int) round($minutes * 60) : null;
    }

    private function slug(string $value): string
    {
        $value = $this->normalizeHeader($value);
        return $value !== '' ? $value : bin2hex(random_bytes(4));
    }
}
