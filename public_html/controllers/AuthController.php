<?php

class AuthController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    public function showLogin(): void
    {
        if (is_logged_in()) {
            $this->redirect(current_company_id() ? default_admin_route() : 'select-company');
        }

        $this->render('auth/login', ['title' => 'Entrar'], 'layouts/guest');
    }

    public function login(): void
    {
        csrf_validate();

        $login = trim((string) post('login'));
        $password = (string) post('password');

        if ($login === '' || $password === '') {
            $this->error('Informe usuário/email e senha.');
            $this->redirect('login');
        }

        $user = $this->users->findByLogin($login);

        $validPassword = false;
        if ($user) {
            $validPassword = password_verify($password, $user['password_hash']) || hash_equals((string) $user['password_hash'], $password);
        }

        if (!$user || !$validPassword) {
            $this->error('Credenciais inválidas.');
            $this->redirect('login');
        }

        if (hash_equals((string) $user['password_hash'], $password)) {
            $rehash = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $rehash->execute([
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => (int) $user['id'],
            ]);
        }

        session_regenerate_id(true);
        unset($_SESSION['student']);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role'],
            'permission_keys' => $this->users->permissionKeys((int) $user['id']),
        ];

        $companies = $this->users->companiesForUser((int) $user['id']);
        if ($companies === []) {
            $_SESSION = [];
            $this->error('Usuário sem empresa vinculada. Vincule ao menos um CNPJ para liberar o acesso.');
            $this->redirect('login');
        }

        $_SESSION['user_companies'] = $companies;
        clear_current_company();

        $stmt = db()->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
        $stmt->execute([
            ':last_login_at' => now(),
            ':id' => (int) $user['id'],
        ]);

        $this->success('Login realizado. Selecione a empresa para continuar.');
        $this->redirect('select-company');
    }

    public function selectCompany(): void
    {
        require_auth();

        $companies = $this->users->companiesForUser((int) current_user()['id']);
        $_SESSION['user_companies'] = $companies;

        if ($companies === []) {
            $this->error('Nenhuma empresa ativa vinculada ao usuário.');
            $this->redirect('logout');
        }

        $this->render('auth/select_company', [
            'title' => 'Selecionar Empresa',
            'companies' => $companies,
            'currentCompanyId' => current_company_id(),
        ], 'layouts/guest');
    }

    public function setCompany(): void
    {
        require_auth();
        csrf_validate();

        $userId = (int) current_user()['id'];
        $companies = $this->users->companiesForUser($userId);
        $_SESSION['user_companies'] = $companies;

        $companyId = (int) post('company_id');
        if ($companyId <= 0 || !$this->users->userCanAccessCompany($userId, $companyId)) {
            $this->error('Empresa inválida para este usuário.');
            $this->redirect('select-company');
        }

        foreach ($companies as $company) {
            if ((int) ($company['id'] ?? 0) === $companyId) {
                set_current_company($company);
                $this->success('Empresa selecionada com sucesso.');
                $this->redirect(default_admin_route());
            }
        }

        $this->error('Empresa não encontrada na sessão atual.');
        $this->redirect('select-company');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $this->success('Sessão encerrada.');
        $this->redirect('login');
    }
}
