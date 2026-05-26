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

        $data = array_merge([
            'email' => '', 'phone' => '', 'lead_value' => 0,
            'assigned_to' => '', 'source' => '', 'lead_status_id' => '',
            'unit_name' => '', 'tags' => '', 'last_contact_at' => '',
        ], $data);

        $id = $this->leads->create($data, $this->actorUserId());
        $lead = $this->leads->find($id);
        $this->ok($lead, null, 201);
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
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'status'   => $_GET['status'] ?? '',
            'priority' => $_GET['priority'] ?? '',
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

    private function actorUserId(): int
    {
        $userId = (int) ($this->token['user_id'] ?? 0);
        return $userId > 0 ? $userId : 1;
    }
}
