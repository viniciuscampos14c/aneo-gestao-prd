<?php

class UserController extends BaseController
{
    private UserModel $users;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->users = new UserModel();
        $this->audit = new AuditLogService();
    }

    public function index(): void
    {
        require_auth();
        require_permission('users');

        $filters = [
            'q' => trim((string) request('q', '')),
            'role' => trim((string) request('role', '')),
            'is_active' => request('is_active', ''),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->users->list($filters, $perPage, $page);

        $this->render('users/index', [
            'title' => 'Administracao de Usuarios',
            'filters' => $filters,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'roles' => $this->allowedRoles(),
        ]);
    }

    public function create(): void
    {
        require_auth();
        require_permission('users');

        $availableCompanies = $this->users->listActiveCompanies();
        $currentCompanyId = current_company_id();
        $selectedCompanyIds = $currentCompanyId !== null ? [$currentCompanyId] : [];

        $this->render('users/form', [
            'title' => 'Novo Usuario',
            'action' => route('users/store'),
            'userData' => null,
            'selectedPermissions' => array_keys($this->permissionCatalog()['all']),
            'availableCompanies' => $availableCompanies,
            'selectedCompanyIds' => $selectedCompanyIds,
            'roles' => $this->allowedRoles(),
            'catalog' => $this->permissionCatalog(),
        ]);
    }

    public function store(): void
    {
        require_auth();
        require_permission('users');
        csrf_validate();

        $data = $this->collectFormData(false);
        if ($data['error']) {
            $this->error($data['error']);
            $this->redirect('users/create');
        }

        try {
            $id = $this->users->createUser($data['payload'], $data['permissions'], $data['company_ids']);
        } catch (PDOException $e) {
            $this->error('Nao foi possivel criar usuario. Verifique email/usuario duplicado.');
            $this->redirect('users/create');
        }

        $after = $this->userSnapshot($id);
        $this->audit->log([
            'module' => 'cadastro.usuarios',
            'action' => 'create',
            'entity_type' => 'user',
            'entity_id' => $id,
            'entity_label' => (string) ($after['name'] ?? 'Usuario #' . $id),
            'description' => 'Usuario criado.',
            'before' => [],
            'after' => $after,
        ]);

        $this->success('Usuario criado #' . $id . '.');
        $this->redirect('users');
    }

    public function edit(): void
    {
        require_auth();
        require_permission('users');

        $id = (int) request('id');
        $userData = $this->users->findForEdit($id);

        if (!$userData) {
            $this->error('Usuario nao encontrado.');
            $this->redirect('users');
        }

        $selected = $this->users->permissionKeys($id);
        $availableCompanies = $this->users->listActiveCompanies();
        $selectedCompanyIds = $this->users->companyIdsForUser($id);

        $this->render('users/form', [
            'title' => 'Editar Usuario',
            'action' => route('users/update&id=' . $id),
            'userData' => $userData,
            'selectedPermissions' => $selected,
            'availableCompanies' => $availableCompanies,
            'selectedCompanyIds' => $selectedCompanyIds,
            'roles' => $this->allowedRoles(),
            'catalog' => $this->permissionCatalog(),
        ]);
    }

    public function update(): void
    {
        require_auth();
        require_permission('users');
        csrf_validate();

        $id = (int) request('id');
        $existing = $this->users->findForEdit($id);
        $before = $existing ? $this->userSnapshot($id) : null;

        if (!$existing) {
            $this->error('Usuario nao encontrado.');
            $this->redirect('users');
        }

        $data = $this->collectFormData(true);
        if ($data['error']) {
            $this->error($data['error']);
            $this->redirect('users/edit&id=' . $id);
        }

        try {
            $this->users->updateUser($id, $data['payload'], $data['permissions'], $data['company_ids']);
        } catch (PDOException $e) {
            $this->error('Nao foi possivel atualizar usuario. Verifique email/usuario duplicado.');
            $this->redirect('users/edit&id=' . $id);
        }

        if ((int) current_user()['id'] === $id) {
            $_SESSION['user']['name'] = $data['payload']['name'];
            $_SESSION['user']['email'] = $data['payload']['email'];
            $_SESSION['user']['username'] = $data['payload']['username'];
            $_SESSION['user']['role'] = $data['payload']['role'];
            $_SESSION['user']['permission_keys'] = $this->users->permissionKeys($id);
            $this->refreshCurrentUserCompanySession($id);
        }

        $after = $this->userSnapshot($id);
        $this->audit->log([
            'module' => 'cadastro.usuarios',
            'action' => 'update',
            'entity_type' => 'user',
            'entity_id' => $id,
            'entity_label' => (string) ($after['name'] ?? ($before['name'] ?? ('Usuario #' . $id))),
            'description' => 'Usuario atualizado.',
            'before' => $before,
            'after' => $after,
        ]);

        $this->success('Usuario atualizado.');
        $this->redirect('users');
    }

    public function toggle(): void
    {
        require_auth();
        require_permission('users');
        csrf_validate();

        $id = (int) post('id');
        $active = (int) post('active', 1);

        if ($id <= 0) {
            $this->redirect('users');
        }

        if ($id === (int) current_user()['id'] && $active === 0) {
            $this->error('Nao e permitido inativar seu proprio usuario.');
            $this->redirect('users');
        }

        $before = $this->userSnapshot($id);
        $this->users->setActive($id, $active);
        $after = $this->userSnapshot($id);

        $this->audit->log([
            'module' => 'cadastro.usuarios',
            'action' => 'toggle',
            'entity_type' => 'user',
            'entity_id' => $id,
            'entity_label' => (string) ($after['name'] ?? ($before['name'] ?? ('Usuario #' . $id))),
            'description' => (int) $active === 1 ? 'Usuario ativado.' : 'Usuario inativado.',
            'before' => $before,
            'after' => $after,
        ]);

        $this->success('Status do usuario atualizado.');
        $this->redirect('users');
    }

    public function delete(): void
    {
        require_auth();
        require_permission('users');
        csrf_validate();

        $id = (int) post('id');

        if ($id <= 0) {
            $this->redirect('users');
        }

        if ($id === (int) current_user()['id']) {
            $this->error('Nao e permitido excluir seu proprio usuario.');
            $this->redirect('users');
        }

        $before = $this->userSnapshot($id);
        $this->users->deleteUser($id);

        $this->audit->log([
            'module' => 'cadastro.usuarios',
            'action' => 'delete',
            'entity_type' => 'user',
            'entity_id' => $id,
            'entity_label' => (string) ($before['name'] ?? ('Usuario #' . $id)),
            'description' => 'Usuario removido.',
            'before' => $before,
            'after' => [],
        ]);

        $this->success('Usuario removido.');
        $this->redirect('users');
    }

    private function collectFormData(bool $isUpdate): array
    {
        $roles = array_keys($this->allowedRoles());

        $payload = [
            'name' => trim((string) post('name')),
            'username' => trim((string) post('username')),
            'email' => trim((string) post('email')),
            'password' => (string) post('password', ''),
            'role' => trim((string) post('role', 'suporte')),
            'is_active' => (int) post('is_active', 1),
        ];

        if ($payload['name'] === '' || $payload['username'] === '' || $payload['email'] === '') {
            return ['error' => 'Nome, usuario e email sao obrigatorios.'];
        }

        if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Informe um email valido.'];
        }

        if (!in_array($payload['role'], $roles, true)) {
            return ['error' => 'Perfil de usuario invalido.'];
        }

        if (!$isUpdate && strlen($payload['password']) < 6) {
            return ['error' => 'Senha deve ter ao menos 6 caracteres.'];
        }

        if ($isUpdate && $payload['password'] !== '' && strlen($payload['password']) < 6) {
            return ['error' => 'Quando informada, a nova senha deve ter ao menos 6 caracteres.'];
        }

        $permissions = $this->sanitizePermissionKeys((array) post('permissions', []));
        if ($payload['role'] === 'suporte' && $permissions === []) {
            if ($isUpdate) {
                return ['error' => 'Selecione ao menos uma permissao para o usuario de suporte.'];
            }
            $permissions = array_keys($this->permissionCatalog()['all']);
        }

        if (in_array($payload['role'], ['admin', 'professor'], true)) {
            $permissions = [];
        }

        $companyIds = $this->normalizeCompanyIds((array) post('company_ids', []));
        $availableCompanies = $this->users->listActiveCompanies();
        if ($availableCompanies !== [] && $companyIds === []) {
            return ['error' => 'Selecione ao menos uma empresa para este usuario.'];
        }

        return [
            'error' => null,
            'payload' => $payload,
            'permissions' => $permissions,
            'company_ids' => $companyIds,
        ];
    }

    private function sanitizePermissionKeys(array $keys): array
    {
        $allowed = $this->permissionCatalog()['all'];
        $clean = [];

        foreach ($keys as $key) {
            $permission = trim((string) $key);
            if ($permission !== '' && isset($allowed[$permission])) {
                $clean[] = $permission;
            }
        }

        return array_values(array_unique($clean));
    }

    private function allowedRoles(): array
    {
        return [
            'admin' => 'Administrador',
            'suporte' => 'Suporte',
            'professor' => 'Professor',
        ];
    }

    private function normalizeCompanyIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
    }

    private function refreshCurrentUserCompanySession(int $userId): void
    {
        $companies = $this->users->companiesForUser($userId);
        $_SESSION['user_companies'] = $companies;

        if ($companies === []) {
            clear_current_company();
            return;
        }

        $currentCompanyId = current_company_id();
        foreach ($companies as $company) {
            if ((int) ($company['id'] ?? 0) === (int) $currentCompanyId) {
                set_current_company($company);
                return;
            }
        }

        set_current_company($companies[0]);
    }

    private function permissionCatalog(): array
    {
        $modules = config('permissions_catalog.modules', []);
        $functions = config('permissions_catalog.functions', []);

        return [
            'modules' => $modules,
            'functions' => $functions,
            'all' => $modules + $functions,
        ];
    }

    private function userSnapshot(int $id): ?array
    {
        $user = $this->users->findForEdit($id);
        if (!$user) {
            return null;
        }

        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'is_active' => (int) ($user['is_active'] ?? 0),
            'permission_keys' => $this->users->permissionKeys($id),
            'company_ids' => $this->users->companyIdsForUser($id),
        ];
    }
}
