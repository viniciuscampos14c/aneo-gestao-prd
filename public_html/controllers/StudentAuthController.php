<?php

class StudentAuthController extends BaseController
{
    private StudentPortalModel $portal;

    public function __construct()
    {
        $this->portal = new StudentPortalModel();
    }

    public function showLogin(): void
    {
        if (is_student_logged_in()) {
            $this->redirect('student/dashboard');
        }

        $this->render('student_auth/login', ['title' => 'Portal do Aluno'], 'layouts/student_guest');
    }

    public function login(): void
    {
        csrf_validate();

        $login = trim((string) post('login'));
        $password = (string) post('password');

        if ($login === '' || $password === '') {
            $this->error('Informe login e senha do aluno.');
            $this->redirect('student/login');
        }

        if (!$this->portal->portalFeatureAvailable()) {
            $this->error('Portal do aluno ainda nao configurado no banco. Execute o SQL atualizado.');
            $this->redirect('student/login');
        }

        $account = $this->portal->findAccountByLogin($login);
        if (!$account || (int) $account['is_active'] !== 1 || (int) $account['student_is_active'] !== 1) {
            $this->error('Acesso do aluno invalido ou inativo.');
            $this->redirect('student/login');
        }

        $validPassword = password_verify($password, (string) $account['password_hash'])
            || hash_equals((string) $account['password_hash'], $password);

        if (!$validPassword) {
            $this->error('Credenciais invalidas.');
            $this->redirect('student/login');
        }

        $trialContext = $this->portal->trialAccessContext((int) $account['student_id']);
        if (!empty($trialContext['is_trial']) && empty($trialContext['allowed_today'])) {
            $accessDate = trim((string) ($trialContext['access_date'] ?? ''));
            $formattedDate = $accessDate !== '' ? date('d/m/Y', strtotime($accessDate)) : '-';
            $status = trim((string) ($trialContext['status'] ?? ''));

            if ($status === 'revoked') {
                $this->error('Este acesso de degustacao foi revogado pelo administrador.');
                $this->redirect('student/login');
            }

            if ($status === 'expired') {
                $this->error('Este acesso de degustacao expirou. O dia liberado foi ' . $formattedDate . '.');
                $this->redirect('student/login');
            }

            $this->error('Acesso de degustacao permitido apenas em ' . $formattedDate . '.');
            $this->redirect('student/login');
        }

        if (hash_equals((string) $account['password_hash'], $password)) {
            $stmt = db()->prepare('UPDATE student_portal_accounts SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
            $stmt->execute([
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':updated_at' => now(),
                ':id' => (int) $account['id'],
            ]);
        }

        session_regenerate_id(true);

        $_SESSION['student'] = [
            'account_id' => (int) $account['id'],
            'id' => (int) $account['student_id'],
            'company_id' => (int) ($account['company_id'] ?? 0),
            'name' => $account['full_name'],
            'email' => $account['email_primary'],
            'phone' => $account['phone'],
            'profile_photo' => $account['profile_photo'],
            'login' => $account['login'],
            'trial_access' => !empty($trialContext['is_trial']) ? [
                'is_trial' => true,
                'allowed_today' => true,
                'course_id' => (int) ($trialContext['course_id'] ?? 0),
                'course_name' => (string) ($trialContext['course_name'] ?? ''),
                'access_date' => (string) ($trialContext['access_date'] ?? ''),
                'status' => (string) ($trialContext['status'] ?? ''),
                'access_scope' => (string) ($trialContext['access_scope'] ?? ''),
            ] : null,
        ];

        $this->portal->updateLastLogin((int) $account['id']);
        if (!empty($trialContext['is_trial'])) {
            $this->portal->registerTrialLogin((int) $account['student_id']);
        }

        $this->success('Login do aluno realizado com sucesso.');
        $this->redirect('student/dashboard');
    }

    public function logout(): void
    {
        unset($_SESSION['student']);
        session_regenerate_id(true);
        $this->success('Sessao do aluno encerrada.');
        $this->redirect('student/login');
    }
}
