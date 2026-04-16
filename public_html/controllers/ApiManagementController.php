<?php

class ApiManagementController extends BaseController
{
    private ApiTokenModel $tokens;
    private UserModel     $users;

    public function __construct()
    {
        $this->tokens = new ApiTokenModel();
        $this->users  = new UserModel();
    }

    public function index(): void
    {
        require_admin();

        $companyId = (int) current_company_id();
        $rows = $this->tokens->list($companyId);

        $this->render('api_management/index', [
            'title' => 'Gerenciamento de API',
            'rows'  => $rows,
        ]);
    }

    public function create(): void
    {
        require_admin();

        $this->render('api_management/form', [
            'title'     => 'Novo Token de API',
            'action'    => route('api-management/store'),
            'tokenData' => null,
            'users'     => $this->listUsers(),
            'resources' => ApiTokenModel::RESOURCES,
            'resLabels' => ApiTokenModel::RESOURCE_LABELS,
            'capLabels' => ApiTokenModel::CAP_LABELS,
            'selected'  => [],
        ]);
    }

    public function store(): void
    {
        require_admin();
        csrf_validate();

        $name      = trim((string) post('name', ''));
        $userId    = (int) post('user_id', 0);
        $expiresAt = trim((string) post('expires_at', ''));
        $permissions = $this->collectPermissions();

        if ($name === '') {
            $this->error('Informe um nome para o token.');
            $this->redirect('api-management/create');
        }

        if ($userId <= 0) {
            $this->error('Selecione o usuário vinculado ao token.');
            $this->redirect('api-management/create');
        }

        if (empty($permissions)) {
            $this->error('Selecione ao menos uma permissão.');
            $this->redirect('api-management/create');
        }

        $result = $this->tokens->create([
            'company_id'  => current_company_id(),
            'user_id'     => $userId,
            'name'        => $name,
            'permissions' => $permissions,
            'expires_at'  => $expiresAt !== '' ? $expiresAt : null,
        ]);

        $this->render('api_management/token_created', [
            'title'     => 'Token Criado',
            'tokenId'   => $result['id'],
            'rawToken'  => $result['raw_token'],
            'tokenName' => $name,
        ]);
    }

    public function edit(int $id): void
    {
        require_admin();

        $companyId = (int) current_company_id();
        $tokenData = $this->tokens->find($id, $companyId);
        if (!$tokenData) {
            $this->error('Token não encontrado.');
            $this->redirect('api-management');
        }

        $this->render('api_management/form', [
            'title'     => 'Editar Token de API',
            'action'    => route('api-management/update?id=' . $id),
            'tokenData' => $tokenData,
            'users'     => $this->listUsers(),
            'resources' => ApiTokenModel::RESOURCES,
            'resLabels' => ApiTokenModel::RESOURCE_LABELS,
            'capLabels' => ApiTokenModel::CAP_LABELS,
            'selected'  => $tokenData['permissions'],
        ]);
    }

    public function update(int $id): void
    {
        require_admin();
        csrf_validate();

        $companyId = (int) current_company_id();
        $tokenData = $this->tokens->find($id, $companyId);
        if (!$tokenData) {
            $this->error('Token não encontrado.');
            $this->redirect('api-management');
        }

        $name      = trim((string) post('name', ''));
        $userId    = (int) post('user_id', 0);
        $expiresAt = trim((string) post('expires_at', ''));
        $permissions = $this->collectPermissions();

        if ($name === '') {
            $this->error('Informe um nome para o token.');
            $this->redirect('api-management/edit?id=' . $id);
        }

        if ($userId <= 0) {
            $this->error('Selecione o usuário vinculado ao token.');
            $this->redirect('api-management/edit?id=' . $id);
        }

        $this->tokens->update($id, [
            'name'        => $name,
            'user_id'     => $userId,
            'permissions' => $permissions,
            'expires_at'  => $expiresAt !== '' ? $expiresAt : null,
        ], $companyId);

        $this->success('Token atualizado com sucesso.');
        $this->redirect('api-management');
    }

    public function destroy(int $id): void
    {
        require_admin();
        csrf_validate();

        $companyId = (int) current_company_id();
        $this->tokens->delete($id, $companyId);

        $this->success('Token removido com sucesso.');
        $this->redirect('api-management');
    }

    public function manual(): void
    {
        require_admin();

        $this->render('api_management/manual', [
            'title'     => 'Manual da API',
            'resources' => ApiTokenModel::RESOURCES,
            'resLabels' => ApiTokenModel::RESOURCE_LABELS,
        ]);
    }

    // -------------------------------------------------------------------------

    private function collectPermissions(): array
    {
        $permissions = [];
        $posted = $_POST['permissions'] ?? [];
        if (!is_array($posted)) {
            return [];
        }

        foreach (ApiTokenModel::RESOURCES as $resource => $validCaps) {
            $caps = array_values(array_intersect(
                (array) ($posted[$resource] ?? []),
                $validCaps
            ));
            if ($caps !== []) {
                $permissions[$resource] = $caps;
            }
        }

        return $permissions;
    }

    private function listUsers(): array
    {
        $result = $this->users->list(['q' => '', 'role' => '', 'is_active' => 1], 200, 1);
        return $result['rows'];
    }
}
