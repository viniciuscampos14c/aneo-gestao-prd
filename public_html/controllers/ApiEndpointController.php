<?php

/**
 * Controlador dos endpoints REST da API pública.
 * Autenticação exclusivamente por Bearer Token (sem sessão de usuário).
 */
class ApiEndpointController extends BaseController
{
    private array $token;

    private StudentModel      $students;
    private LeadModel         $leads;
    private FinanceModel      $finance;
    private CourseModel       $courses;
    private UserModel         $users;
    private SupportTicketModel $tickets;
    private PaymentMethodModel $paymentMethods;

    public function __construct(array $token)
    {
        $this->token   = $token;
        $this->students = new StudentModel();
        $this->leads    = new LeadModel();
        $this->finance  = new FinanceModel();
        $this->courses  = new CourseModel();
        $this->users    = new UserModel();
        $this->tickets  = new SupportTicketModel();
        $this->paymentMethods = new PaymentMethodModel();
    }

    // =========================================================================
    // STUDENTS
    // =========================================================================

    public function createRdStationStudent(): void
    {
        ApiAuth::requirePermission($this->token, 'rdstation_students', 'create');

        $data = $this->parseBody();
        $this->validateRequired($data, [
            'company_id',
            'full_name',
            'email',
            'phone',
            'city',
            'birth_date',
            'rg',
            'cpf',
            'enrolled_at',
            'invoice_due_day',
        ]);

        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId <= 0 || !$this->activeCompanyExists($companyId)) {
            ApiAuth::abort(422, 'company_id nao corresponde a uma empresa ativa.');
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiAuth::abort(422, 'email deve conter um endereco valido.');
        }

        $phone = $this->normalizeBrazilianPhone((string) ($data['phone'] ?? ''));
        if ($phone === null) {
            ApiAuth::abort(422, 'phone deve conter 10 ou 11 digitos, com DDD.');
        }

        $cpf = preg_replace('/\D/', '', (string) ($data['cpf'] ?? '')) ?: '';
        if (!$this->isValidCpf($cpf)) {
            ApiAuth::abort(422, 'cpf invalido.');
        }

        $birthDate = $this->validateIsoDate((string) ($data['birth_date'] ?? ''), 'birth_date');
        if ($birthDate >= date('Y-m-d')) {
            ApiAuth::abort(422, 'birth_date deve ser anterior a data atual.');
        }

        $enrolledAt = $this->validateIsoDate((string) ($data['enrolled_at'] ?? ''), 'enrolled_at');
        $invoiceDueDay = filter_var(
            $data['invoice_due_day'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 31]]
        );
        if ($invoiceDueDay === false) {
            ApiAuth::abort(422, 'invoice_due_day deve ser um numero inteiro entre 1 e 31.');
        }

        $existingByCpf = $this->findStudentIdentity('cpf', $cpf);
        if ($existingByCpf && (int) $existingByCpf['company_id'] !== $companyId) {
            ApiAuth::abort(409, 'CPF ja cadastrado em outra empresa. Contate a ANEO para transferir o aluno.');
        }

        $existing = $existingByCpf ?: $this->findStudentIdentity('email_primary', $email, $companyId);
        $this->students->useCompany($companyId);
        $_SESSION['company'] = ['id' => $companyId];

        $studentData = array_merge(
            $this->rdStationStudentDefaults($existing),
            [
                'full_name' => trim((string) $data['full_name']),
                'email_primary' => $email,
                'phone' => $phone,
                'city' => trim((string) $data['city']),
                'birth_date' => $birthDate,
                'rg' => trim((string) $data['rg']),
                'cpf' => $cpf,
                'cro' => array_key_exists('cro', $data)
                    ? trim((string) $data['cro'])
                    : (string) ($existing['cro'] ?? ''),
                'enrolled_at' => $enrolledAt,
                'billing_day' => (int) $invoiceDueDay,
                'is_active' => 1,
            ]
        );

        if ($existing) {
            $studentId = (int) $existing['id'];
            $this->students->update($studentId, $studentData, $this->actorUserId());
            $student = $this->students->find($studentId);
            $this->ok([
                'action' => 'updated',
                'student' => $student,
            ]);
        }

        $studentId = $this->students->create($studentData, $this->actorUserId());
        $student = $this->students->find($studentId);
        $this->ok([
            'action' => 'created',
            'student' => $student,
        ], null, 201);
    }

    public function listStudents(): void
    {
        ApiAuth::requirePermission($this->token, 'students', 'search');

        $filters = [
            'q'                => trim((string) ($_GET['q'] ?? '')),
            'is_active'        => $_GET['is_active'] ?? '',
            'kanban_status_id' => $_GET['kanban_status_id'] ?? '',
        ];
        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->students->list($filters, $perPage, $page);
        $this->ok($result['rows'], $result['meta']);
    }

    public function getStudent(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'students', 'get');

        $student = $this->students->find($id);
        if (!$student) {
            ApiAuth::abort(404, 'Aluno nao encontrado.');
        }
        $this->ok($student);
    }

    public function createStudent(): void
    {
        ApiAuth::requirePermission($this->token, 'students', 'create');

        $data = $this->parseBody();
        $required = ['full_name'];
        $this->validateRequired($data, $required);

        $data = array_merge([
            'primary_contact' => '', 'email_primary' => '', 'phone' => '',
            'admin_info' => '', 'ra' => '', 'birth_date' => '', 'rg' => '',
            'cro' => '', 'notes' => '', 'monthly_fee' => 0, 'billing_day' => '',
            'kanban_status_id' => '', 'is_active' => 1, 'profile_photo' => '',
        ], $data);

        $id = $this->students->create($data, $this->actorUserId());
        $student = $this->students->find($id);
        $this->ok($student, null, 201);
    }

    public function updateStudent(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'students', 'update');

        $student = $this->students->find($id);
        if (!$student) {
            ApiAuth::abort(404, 'Aluno nao encontrado.');
        }

        $data = $this->parseBody();
        $merged = array_merge([
            'full_name'       => $student['full_name'],
            'primary_contact' => $student['primary_contact'] ?? '',
            'email_primary'   => $student['email_primary'] ?? '',
            'phone'           => $student['phone'] ?? '',
            'admin_info'      => $student['admin_info'] ?? '',
            'ra'              => $student['ra'] ?? '',
            'birth_date'      => $student['birth_date'] ?? '',
            'rg'              => $student['rg'] ?? '',
            'cro'             => $student['cro'] ?? '',
            'notes'           => $student['notes'] ?? '',
            'monthly_fee'     => $student['monthly_fee'] ?? 0,
            'billing_day'     => $student['billing_day'] ?? '',
            'kanban_status_id'=> $student['kanban_status_id'] ?? '',
            'is_active'       => $student['is_active'] ?? 1,
            'profile_photo'   => $student['profile_photo'] ?? '',
        ], $data);

        $this->students->update($id, $merged, $this->actorUserId());
        $updated = $this->students->find($id);
        $this->ok($updated);
    }

    public function deleteStudent(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'students', 'delete');

        $student = $this->students->find($id);
        if (!$student) {
            ApiAuth::abort(404, 'Aluno nao encontrado.');
        }

        $this->students->delete($id);
        $this->ok(['deleted' => true, 'id' => $id]);
    }

    // =========================================================================
    // LEADS
    // =========================================================================

    public function listLeads(): void
    {
        ApiAuth::requirePermission($this->token, 'leads', 'search');

        $filters = [
            'q'         => trim((string) ($_GET['q'] ?? '')),
            'status_id' => $_GET['status_id'] ?? '',
        ];
        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->leads->list($filters, $perPage, $page);
        $this->ok($result['rows'], $result['meta']);
    }

    public function getLead(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'leads', 'get');

        $lead = $this->leads->find($id);
        if (!$lead) {
            ApiAuth::abort(404, 'Lead nao encontrado.');
        }
        $this->ok($lead);
    }

    public function createLead(): void
    {
        ApiAuth::requirePermission($this->token, 'leads', 'create');

        $data = $this->parseBody();
        $this->validateRequired($data, ['full_name']);

        $targetCompanyId = $this->resolveLeadCompanyId($data);
        $this->leads->useCompany($targetCompanyId);
        $_SESSION['company'] = ['id' => $targetCompanyId];

        $data = array_merge([
            'email' => '', 'phone' => '', 'lead_value' => 0,
            'assigned_to' => '', 'source' => '', 'lead_status_id' => '',
            'unit_name' => '', 'tags' => '', 'last_contact_at' => '',
        ], $data);

        $id = $this->leads->create($data, $this->actorUserId());
        $lead = $this->leads->find($id);
        $this->ok($lead, null, 201);
    }

    private function resolveLeadCompanyId(array $data): int
    {
        $tokenCompanyId = (int) ($this->token['company_id'] ?? 0);
        $requestedCompanyId = (int) ($data['company_id'] ?? 0);
        if ($requestedCompanyId > 0) {
            if ($this->activeCompanyExists($requestedCompanyId)) {
                return $requestedCompanyId;
            }
            ApiAuth::abort(422, 'Empresa informada em company_id nao encontrada ou inativa.');
        }

        $target = trim((string) ($data['uf'] ?? $data['state'] ?? $data['unit_name'] ?? ''));
        if ($target === '') {
            return $tokenCompanyId;
        }

        $normalizedTarget = $this->normalizeCompanyMatcher($this->stateNameFromUf($target) ?? $target);
        foreach ($this->activeCompaniesForLeadRouting() as $company) {
            $haystack = $this->normalizeCompanyMatcher(
                (string) ($company['trade_name'] ?? '') . ' ' . (string) ($company['legal_name'] ?? '')
            );
            if ($normalizedTarget !== '' && str_contains($haystack, $normalizedTarget)) {
                return (int) $company['id'];
            }
        }

        ApiAuth::abort(422, 'Nao foi possivel identificar a unidade do lead. Envie company_id, uf, state ou unit_name valido.');
    }

    private function activeCompanyExists(int $companyId): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM companies WHERE id = :id AND is_active = 1');
        $stmt->execute([':id' => $companyId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function activeCompaniesForLeadRouting(): array
    {
        $stmt = db()->query('SELECT id, trade_name, legal_name FROM companies WHERE is_active = 1 ORDER BY id ASC');
        return $stmt->fetchAll() ?: [];
    }

    private function stateNameFromUf(string $value): ?string
    {
        $uf = strtoupper(trim($value));
        $map = [
            'AC' => 'acre', 'AL' => 'alagoas', 'AP' => 'amapa', 'AM' => 'amazonas',
            'BA' => 'bahia', 'CE' => 'ceara', 'DF' => 'brasilia', 'ES' => 'espirito santo',
            'GO' => 'goiania', 'MA' => 'maranhao', 'MT' => 'mato grosso', 'MS' => 'mato grosso do sul',
            'MG' => 'minas gerais', 'PA' => 'para', 'PB' => 'paraiba', 'PR' => 'parana',
            'PE' => 'pernambuco', 'PI' => 'piaui', 'RJ' => 'rio de janeiro', 'RN' => 'rio grande do norte',
            'RS' => 'rio grande do sul', 'RO' => 'rondonia', 'RR' => 'roraima', 'SC' => 'santa catarina',
            'SP' => 'sao paulo', 'SE' => 'sergipe', 'TO' => 'palmas',
        ];

        return $map[$uf] ?? null;
    }

    private function normalizeCompanyMatcher(string $value): string
    {
        $value = strtolower(trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($ascii) ? $ascii : $value;
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    public function updateLead(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'leads', 'update');

        $lead = $this->leads->find($id);
        if (!$lead) {
            ApiAuth::abort(404, 'Lead nao encontrado.');
        }

        $data = $this->parseBody();
        $merged = array_merge([
            'full_name'       => $lead['full_name'],
            'email'           => $lead['email'] ?? '',
            'phone'           => $lead['phone'] ?? '',
            'lead_value'      => $lead['lead_value'] ?? 0,
            'assigned_to'     => $lead['assigned_to'] ?? '',
            'source'          => $lead['source'] ?? '',
            'lead_status_id'  => $lead['lead_status_id'] ?? '',
            'unit_name'       => $lead['unit_name'] ?? '',
            'tags'            => $lead['tags'] ?? '',
            'last_contact_at' => $lead['last_contact_at'] ?? '',
        ], $data);

        $this->leads->update($id, $merged, $this->actorUserId());
        $updated = $this->leads->find($id);
        $this->ok($updated);
    }

    public function deleteLead(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'leads', 'delete');

        $lead = $this->leads->find($id);
        if (!$lead) {
            ApiAuth::abort(404, 'Lead nao encontrado.');
        }

        $this->leads->delete($id);
        $this->ok(['deleted' => true, 'id' => $id]);
    }

    // =========================================================================
    // INVOICES (Faturas)
    // =========================================================================

    public function listInvoices(): void
    {
        ApiAuth::requirePermission($this->token, 'invoices', 'search');

        $filters = [
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'status'     => $_GET['status'] ?? '',
            'student_id' => $_GET['student_id'] ?? '',
        ];
        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->finance->listInvoices($filters, $perPage, $page);
        $this->ok($result['rows'], $result['meta']);
    }

    public function getInvoice(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'invoices', 'get');

        $invoice = $this->finance->findInvoice($id);
        if (!$invoice) {
            ApiAuth::abort(404, 'Fatura nao encontrada.');
        }
        $this->ok($invoice);
    }

    public function createInvoice(): void
    {
        ApiAuth::requirePermission($this->token, 'invoices', 'create');

        $data = $this->parseBody();
        $this->validateRequired($data, ['student_id', 'due_date', 'amount']);

        $data = array_merge([
            'tax_amount'          => 0,
            'tags'                => '',
            'project_name'        => '',
            'boleto_url'          => '',
            'is_recurring'        => 0,
            'recurrence_interval' => null,
            'status'              => 'open',
            'paid_at'             => null,
        ], $data);

        $id = $this->finance->createInvoice($data, $this->actorUserId());
        $invoice = $this->finance->findInvoice($id);
        $this->ok($invoice, null, 201);
    }

    public function deleteInvoice(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'invoices', 'delete');

        $invoice = $this->finance->findInvoice($id);
        if (!$invoice) {
            ApiAuth::abort(404, 'Fatura nao encontrada.');
        }

        $this->finance->deleteInvoice($id);
        $this->ok(['deleted' => true, 'id' => $id]);
    }

    // =========================================================================
    // COURSES (Cursos)
    // =========================================================================

    public function listCourses(): void
    {
        ApiAuth::requirePermission($this->token, 'courses', 'search');

        $filters = [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'status' => $_GET['status'] ?? '',
        ];
        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->courses->listCourses($filters, $perPage, $page);
        $this->ok($result['rows'], $result['meta']);
    }

    public function getCourse(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'courses', 'get');

        $course = $this->courses->findCourse($id);
        if (!$course) {
            ApiAuth::abort(404, 'Curso nao encontrado.');
        }
        $this->ok($course);
    }

    public function listTrialAccesses(): void
    {
        ApiAuth::requirePermission($this->token, 'trial_accesses', 'search');

        if (!$this->courses->trialAccessFeatureAvailable()) {
            ApiAuth::abort(409, 'Funcionalidade de degustacao indisponivel no banco.');
        }

        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->courses->listTrialAccesses($perPage, $page);
        $this->ok($result['rows'], $result['meta']);
    }

    public function getTrialAccess(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'trial_accesses', 'get');

        if (!$this->courses->trialAccessFeatureAvailable()) {
            ApiAuth::abort(409, 'Funcionalidade de degustacao indisponivel no banco.');
        }

        $trialAccess = $this->courses->findTrialAccess($id);
        if (!$trialAccess) {
            ApiAuth::abort(404, 'Acesso de degustacao nao encontrado.');
        }

        $this->ok($trialAccess);
    }

    public function createTrialAccess(): void
    {
        ApiAuth::requirePermission($this->token, 'trial_accesses', 'create');

        if (!$this->courses->trialAccessFeatureAvailable()) {
            ApiAuth::abort(409, 'Funcionalidade de degustacao indisponivel no banco.');
        }

        $data = $this->parseBody();
        $this->validateRequired($data, ['student_name', 'course_id', 'access_date']);

        $payload = [
            'student_name' => trim((string) ($data['student_name'] ?? '')),
            'student_email' => trim((string) ($data['student_email'] ?? '')),
            'student_phone' => trim((string) ($data['student_phone'] ?? '')),
            'course_id' => (int) ($data['course_id'] ?? 0),
            'access_date' => trim((string) ($data['access_date'] ?? '')),
        ];

        try {
            $created = $this->courses->createTrialAccess($payload, (int) ($this->token['user_id'] ?? 0));
        } catch (RuntimeException $e) {
            ApiAuth::abort(422, $e->getMessage());
        }

        $this->ok($created, null, 201);
    }

    // =========================================================================
    // USERS (Usuários)
    // =========================================================================

    public function listUsers(): void
    {
        ApiAuth::requirePermission($this->token, 'users', 'search');

        $filters = [
            'q'         => trim((string) ($_GET['q'] ?? '')),
            'role'      => $_GET['role'] ?? '',
            'is_active' => $_GET['is_active'] ?? '',
        ];
        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->users->list($filters, $perPage, $page);
        $this->ok($result['rows'], $result['meta']);
    }

    public function getUser(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'users', 'get');

        $user = $this->users->find($id);
        if (!$user) {
            ApiAuth::abort(404, 'Usuario nao encontrado.');
        }
        $this->ok($user);
    }

    // =========================================================================
    // PAYMENT METHODS
    // =========================================================================

    public function listPaymentMethods(): void
    {
        ApiAuth::requirePermission($this->token, 'payment_methods', 'search');

        $companyId = (int) ($this->token['company_id'] ?? 0);
        $channel = strtolower(trim((string) ($_GET['channel'] ?? '')));
        $activeOnly = !isset($_GET['is_active']) || (string) ($_GET['is_active'] ?? '1') !== '0';
        $rows = $activeOnly
            ? $this->paymentMethods->activeByCompany($companyId)
            : $this->paymentMethods->allByCompany($companyId);

        if ($channel !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($channel): bool {
                return strtolower(trim((string) ($row['channel'] ?? ''))) === $channel;
            }));
        }

        $total = count($rows);
        $this->ok($rows, [
            'total' => $total,
            'per_page' => $total,
            'page' => 1,
            'pages' => 1,
        ]);
    }

    // =========================================================================
    // TICKETS (Chamados)
    // =========================================================================

    public function listTickets(): void
    {
        ApiAuth::requirePermission($this->token, 'tickets', 'search');

        $filters = [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'status'      => $_GET['status'] ?? '',
            'priority'    => $_GET['priority'] ?? '',
            'source'      => trim((string) ($_GET['source'] ?? '')),
            'mobile_flow' => (int) ($_GET['mobile_flow'] ?? 0) > 0 ? 1 : 0,
        ];
        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->tickets->listTickets($filters, $perPage, $page);
        $this->ok($result['rows'], $result['meta']);
    }

    public function getTicket(int $id): void
    {
        ApiAuth::requirePermission($this->token, 'tickets', 'get');

        $ticket = $this->tickets->findTicket($id);
        if (!$ticket) {
            ApiAuth::abort(404, 'Chamado nao encontrado.');
        }
        $this->ok($ticket);
    }

    public function createTicket(): void
    {
        ApiAuth::requirePermission($this->token, 'tickets', 'create');

        $data = $this->parseBody();
        $this->validateRequired($data, ['subject', 'description', 'requester_name']);

        $data = array_merge([
            'requester_email' => '',
            'requester_phone' => '',
            'priority'        => 'normal',
            'category'        => '',
        ], $data);

        $id = $this->tickets->createTicket($data, null, 'api');
        $ticket = $this->tickets->findTicket($id);
        $this->ok($ticket, null, 201);
    }

    // =========================================================================
    // Helpers internos
    // =========================================================================

    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    private function validateRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                ApiAuth::abort(422, "Campo obrigatorio ausente: {$field}");
            }
        }
    }

    private function ok(mixed $data, ?array $meta = null, int $status = 200): never
    {
        $payload = ['ok' => true, 'data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        $this->json($payload, $status);
    }

    private function rdStationStudentDefaults(?array $existing): array
    {
        return [
            'primary_contact' => (string) ($existing['primary_contact'] ?? ''),
            'admin_info' => (string) ($existing['admin_info'] ?? ''),
            'ra' => (string) ($existing['ra'] ?? ''),
            'notes' => (string) ($existing['notes'] ?? ''),
            'monthly_fee' => (float) ($existing['monthly_fee'] ?? 0),
            'kanban_status_id' => $existing['kanban_status_id'] ?? '',
            'profile_photo' => (string) ($existing['profile_photo'] ?? ''),
            'practice_unit_id' => $existing['practice_unit_id'] ?? null,
            'residency_level' => (string) ($existing['residency_level'] ?? 'R1'),
            'financial_plan_profile' => (string) ($existing['financial_plan_profile'] ?? ''),
            'financial_plan_installments' => $existing['financial_plan_installments'] ?? null,
            'financial_plan_first_due_date' => (string) ($existing['financial_plan_first_due_date'] ?? ''),
            'financial_plan_payment_method_id' => $existing['financial_plan_payment_method_id'] ?? null,
            'financial_plan_auto_generate' => (int) ($existing['financial_plan_auto_generate'] ?? 0),
            'financial_plan_boleto_days_before' => (int) ($existing['financial_plan_boleto_days_before'] ?? 10),
            'financial_plan_generated_at' => (string) ($existing['financial_plan_generated_at'] ?? ''),
        ];
    }

    private function findStudentIdentity(string $field, string $value, ?int $companyId = null): ?array
    {
        if (!in_array($field, ['cpf', 'email_primary'], true)) {
            return null;
        }

        $sql = "SELECT * FROM students WHERE {$field} = :value";
        $params = [':value' => $value];
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function normalizeBrazilianPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?: '';
        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return in_array(strlen($digits), [10, 11], true) ? $digits : null;
    }

    private function validateIsoDate(string $value, string $field): string
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            ApiAuth::abort(422, "{$field} deve usar o formato YYYY-MM-DD.");
        }

        return $value;
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) {
                $sum += (int) $cpf[$index] * (($digit + 1) - $index);
            }
            $check = (10 * $sum) % 11;
            $check = $check === 10 ? 0 : $check;
            if ($check !== (int) $cpf[$digit]) {
                return false;
            }
        }

        return true;
    }

    private function actorUserId(): int
    {
        $userId = (int) ($this->token['user_id'] ?? 0);
        return $userId > 0 ? $userId : 1;
    }
}
