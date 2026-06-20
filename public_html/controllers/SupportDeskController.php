<?php

class SupportDeskController extends BaseController
{
    private SupportTicketModel $tickets;
    private UserModel $users;
    private StudentPortalModel $portal;

    public function __construct()
    {
        $this->tickets = new SupportTicketModel();
        $this->users = new UserModel();
        $this->portal = new StudentPortalModel();
    }

    public function showLogin(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirectTo('support');
        }

        $this->render('support_desk/login', [
            'title' => 'Central Técnica - Login',
            'enabled' => (bool) config('support_desk.enabled', false),
        ], 'layouts/guest');
    }

    public function login(): void
    {
        csrf_validate();

        if (!(bool) config('support_desk.enabled', false)) {
            flash('error', 'Portal técnico desativado na configuração.');
            $this->redirectTo('support/login');
        }

        $login = trim((string) post('username'));
        $password = (string) post('password');

        if ($login === '' || $password === '') {
            flash('error', 'Informe usuário ou e-mail e senha.');
            $this->redirectTo('support/login');
        }

        $rateLimit = LoginRateLimiter::check('support', $login);
        if (empty($rateLimit['allowed'])) {
            flash('error', 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.');
            $this->redirectTo('support/login');
        }

        $user = $this->users->findByLogin($login);
        $validPassword = false;
        if ($user) {
            $validPassword = password_verify($password, (string) $user['password_hash']) || hash_equals((string) $user['password_hash'], $password);
        }

        if (!$user || !$validPassword) {
            LoginRateLimiter::recordFailure('support', $login);
            flash('error', 'Credenciais inválidas.');
            $this->redirectTo('support/login');
        }

        LoginRateLimiter::clear('support', $login);

        if (hash_equals((string) $user['password_hash'], $password)) {
            $rehash = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $rehash->execute([
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => (int) $user['id'],
            ]);
        }

        $permissionKeys = $this->users->permissionKeys((int) $user['id']);
        if (!$this->hasDeskPermission((string) ($user['role'] ?? ''), $permissionKeys)) {
            flash('error', 'Usuário sem permissão para a central técnica. Libere `support.desk` ou `requests.manage`.');
            $this->redirectTo('support/login');
        }

        $companies = $this->users->companiesForUser((int) $user['id']);
        $companyIds = array_values(array_unique(array_filter(array_map(
            fn ($row) => (int) ($row['id'] ?? 0),
            $companies
        ), fn ($id) => $id > 0)));

        if ($companyIds === []) {
            flash('error', 'Usuário sem empresa vinculada. Vincule ao menos um CNPJ.');
            $this->redirectTo('support/login');
        }

        $_SESSION['support_desk_auth'] = [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'permission_keys' => $permissionKeys,
            'company_ids' => $companyIds,
            'logged_at' => now(),
        ];

        $stmt = db()->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
        $stmt->execute([
            ':last_login_at' => now(),
            ':id' => (int) $user['id'],
        ]);

        flash('success', 'Acesso liberado na central técnica.');
        $this->redirectTo('support');
    }

    public function logout(): void
    {
        unset($_SESSION['support_desk_auth']);
        flash('success', 'Sessão da central técnica encerrada.');
        $this->redirectTo('support/login');
    }

    public function index(): void
    {
        $this->requireAuth();

        $allowedCompanyIds = $this->allowedCompanyIds();
        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
            'priority' => trim((string) request('priority', '')),
            'source' => trim((string) request('source', '')),
            'company_id' => (int) request('company_id', 0),
            'email_sent' => trim((string) request('email_sent', '')),
            'webhook_forwarded' => trim((string) request('webhook_forwarded', '')),
            'company_ids' => $allowedCompanyIds,
        ];

        if ($filters['company_id'] > 0 && !in_array($filters['company_id'], $allowedCompanyIds, true)) {
            $filters['company_id'] = 0;
        }

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->tickets->listAllTickets($filters, $perPage, $page);
        $ticketIds = array_map(fn ($row) => (int) ($row['id'] ?? 0), $result['rows']);

        $this->render('support_desk/index', [
            'title' => 'Central Tecnica de Chamados',
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'stats' => $this->tickets->statsAll($allowedCompanyIds),
            'dispatchStats' => $this->tickets->dispatchStatsAll($allowedCompanyIds),
            'attachmentsByTicket' => $this->tickets->attachmentsByTicketIdsAny($ticketIds),
            'commentsByTicket' => $this->tickets->commentsByTicketIdsAny($ticketIds),
            'featureAvailable' => $this->tickets->featureAvailable(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'companies' => $this->activeCompanies($allowedCompanyIds),
        ], 'layouts/support_desk');
    }

    public function addComment(): void
    {
        $this->requireAuth();
        csrf_validate();

        $auth = $this->authUser();
        $ticketId = (int) post('ticket_id');
        $comment = trim((string) post('comment'));

        if ($ticketId <= 0 || $comment === '') {
            flash('error', 'Informe um comentario valido para o chamado.');
            $this->redirectTo('support');
        }

        $ticket = $this->tickets->findTicketAny($ticketId);
        if (!$ticket || !$this->canAccessTicket((int) ($ticket['company_id'] ?? 0))) {
            flash('error', 'Chamado não encontrado para as empresas permitidas.');
            $this->redirectTo('support');
        }

        $author = trim((string) ($auth['name'] ?? '')) !== '' ? (string) $auth['name'] : (string) ($auth['username'] ?? 'suporte');
        $this->tickets->addCommentAny($ticketId, '[Suporte ' . $author . '] ' . $comment, (int) ($auth['id'] ?? 0));

        flash('success', 'Comentario registrado na central técnica.');
        $this->redirectTo('support');
    }

    public function updateStatus(): void
    {
        $this->requireAuth();
        csrf_validate();

        $auth = $this->authUser();
        $ticketId = (int) post('ticket_id');
        $status = strtolower(trim((string) post('status', 'open')));
        $statusNote = trim((string) post('status_note'));

        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $status = 'open';
        }

        if ($ticketId <= 0) {
            flash('error', 'Chamado inválido.');
            $this->redirectTo('support');
        }

        $ticket = $this->tickets->findTicketAny($ticketId);
        if (!$ticket || !$this->canAccessTicket((int) ($ticket['company_id'] ?? 0))) {
            flash('error', 'Chamado não encontrado para as empresas permitidas.');
            $this->redirectTo('support');
        }

        $this->tickets->updateStatusAny($ticketId, $status);

        if ($statusNote !== '') {
            $author = trim((string) ($auth['name'] ?? '')) !== '' ? (string) $auth['name'] : (string) ($auth['username'] ?? 'suporte');
            $this->tickets->addCommentAny($ticketId, '[Suporte ' . $author . '][Status ' . $status . '] ' . $statusNote, (int) ($auth['id'] ?? 0));
        }

        if ($status === 'resolved') {
            $this->notifyStudentAboutResolvedTicket($ticket, $statusNote);
        }

        flash('success', 'Status atualizado pela central técnica.');
        $this->redirectTo('support');
    }

    private function notifyStudentAboutResolvedTicket(array $ticket, string $statusNote = ''): void
    {
        if (!$this->portal->studentPortalNotificationsFeatureAvailable()) {
            return;
        }

        $student = $this->resolveStudentFromTicket($ticket);
        if (!$student) {
            return;
        }

        $ticketId = (int) ($ticket['id'] ?? 0);
        $ticketCode = trim((string) ($ticket['ticket_code'] ?? ''));
        if ($ticketCode === '' && $ticketId > 0) {
            $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
        }

        $subject = trim((string) ($ticket['subject'] ?? 'seu chamado'));
        $message = $ticketCode !== ''
            ? 'Seu chamado ' . $ticketCode . ' foi resolvido pela Central Tecnica.'
            : 'Seu chamado foi resolvido pela Central Tecnica.';

        $statusNote = trim(preg_replace('/\s+/', ' ', $statusNote) ?? $statusNote);
        if ($statusNote !== '') {
            $message .= ' Observacao da equipe: ' . mb_strimwidth($statusNote, 0, 180, '...');
        }

        $this->portal->createPortalNotification([
            'company_id' => (int) ($student['company_id'] ?? 0),
            'student_id' => (int) ($student['id'] ?? 0),
            'notification_type' => 'support_ticket_resolved',
            'title' => 'Chamado resolvido: ' . ($ticketCode !== '' ? $ticketCode : $subject),
            'message' => $message,
            'link_url' => route('student/requests'),
            'meta' => [
                'ticket_id' => $ticketId,
                'ticket_code' => $ticketCode,
                'subject' => $subject,
                'status' => 'resolved',
            ],
        ]);
    }

    private function resolveStudentFromTicket(array $ticket): ?array
    {
        $companyId = (int) ($ticket['company_id'] ?? 0);
        if ($companyId <= 0) {
            return null;
        }

        $externalReference = trim((string) ($ticket['external_reference'] ?? ''));
        if (preg_match('/^student:(\d+)$/', $externalReference, $matches)) {
            $studentId = (int) ($matches[1] ?? 0);
            if ($studentId > 0) {
                $stmt = db()->prepare('SELECT id, company_id, full_name, email_primary
                    FROM students
                    WHERE id = :id
                      AND company_id = :company_id
                    LIMIT 1');
                $stmt->execute([
                    ':id' => $studentId,
                    ':company_id' => $companyId,
                ]);
                $student = $stmt->fetch();
                if ($student) {
                    return $student;
                }
            }
        }

        $requesterEmail = trim((string) ($ticket['requester_email'] ?? ''));
        if ($requesterEmail === '') {
            return null;
        }

        $stmt = db()->prepare('SELECT id, company_id, full_name, email_primary
            FROM students
            WHERE company_id = :company_id
              AND email_primary = :email
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':email' => $requesterEmail,
        ]);
        $student = $stmt->fetch();

        return $student ?: null;
    }

    private function activeCompanies(array $allowedCompanyIds): array
    {
        $allowedCompanyIds = array_values(array_unique(array_filter(array_map('intval', $allowedCompanyIds), fn ($id) => $id > 0)));
        if ($allowedCompanyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($allowedCompanyIds), '?'));
        $stmt = db()->prepare("SELECT id, legal_name, trade_name
            FROM companies
            WHERE is_active = 1
              AND id IN ({$placeholders})
            ORDER BY legal_name ASC");
        $stmt->execute($allowedCompanyIds);

        return $stmt->fetchAll() ?: [];
    }

    private function requireAuth(): void
    {
        enforce_session_idle_timeout('support', ['support_desk_auth'], 'support/login', true);

        if (!(bool) config('support_desk.enabled', false) || !$this->isAuthenticated()) {
            $this->redirectTo('support/login');
        }

        $auth = $this->authUser();
        if (!$auth || !$this->hasDeskPermission((string) ($auth['role'] ?? ''), (array) ($auth['permission_keys'] ?? []))) {
            flash('error', 'Sessao sem permissão para a central técnica.');
            $this->redirectTo('support/logout');
        }

        if ($this->allowedCompanyIds() === []) {
            flash('error', 'Usuário sem empresas vinculadas para atendimento.');
            $this->redirectTo('support/logout');
        }
    }

    private function isAuthenticated(): bool
    {
        return (int) ($_SESSION['support_desk_auth']['id'] ?? 0) > 0;
    }

    private function authUser(): ?array
    {
        $auth = $_SESSION['support_desk_auth'] ?? null;
        return is_array($auth) ? $auth : null;
    }

    private function allowedCompanyIds(): array
    {
        $auth = $this->authUser();
        if (!$auth) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', (array) ($auth['company_ids'] ?? [])), fn ($id) => $id > 0)));
    }

    private function canAccessTicket(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        return in_array($companyId, $this->allowedCompanyIds(), true);
    }

    private function hasDeskPermission(string $role, array $permissionKeys): bool
    {
        if ($role === 'admin') {
            return true;
        }

        if (in_array('*', $permissionKeys, true)) {
            return true;
        }

        $rolePermissions = (array) config('roles.' . $role . '.permissions', []);
        if (in_array('*', $rolePermissions, true)) {
            return true;
        }

        $required = (array) config('support_desk.required_permissions', ['support.desk', 'requests.manage']);
        foreach ($required as $key) {
            $key = trim((string) $key);
            if ($key !== '' && (in_array($key, $permissionKeys, true) || in_array($key, $rolePermissions, true))) {
                return true;
            }
        }

        return false;
    }

    private function redirectTo(string $route): void
    {
        header('Location: support.php?route=' . rawurlencode(trim($route, '/')));
        exit;
    }
}
