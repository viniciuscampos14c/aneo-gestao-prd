<?php

class MobileAuthApiController extends BaseController
{
    private UserModel $users;
    private ApiTokenModel $tokens;

    public function __construct()
    {
        $this->users = new UserModel();
        $this->tokens = new ApiTokenModel();
    }

    public function login(): void
    {
        $payload = $this->parseBody();
        $login = trim((string) ($payload['login'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $requestedCompanyId = (int) ($payload['company_id'] ?? 0);

        if ($login === '' || $password === '') {
            ApiAuth::abort(422, 'Informe usuario/email e senha.');
        }

        $user = $this->users->findByLogin($login);
        if (!$user || !$this->isValidPassword($user, $password)) {
            ApiAuth::abort(401, 'Credenciais invalidas.');
        }

        if ($this->isLegacyPasswordHash($user, $password)) {
            $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $stmt->execute([
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => (int) $user['id'],
            ]);
        }

        $companies = $this->users->companiesForUser((int) $user['id']);
        if ($companies === []) {
            ApiAuth::abort(403, 'Usuario sem empresa vinculada.');
        }

        $company = $this->resolveCompany($companies, $requestedCompanyId);
        if ($company === null) {
            ApiAuth::abort(403, 'Empresa invalida para este usuario.');
        }

        $permissions = $this->defaultMobilePermissions();
        $tokenLabel = $this->buildTokenLabel($user);

        $result = $this->tokens->create([
            'company_id' => (int) $company['id'],
            'user_id' => (int) $user['id'],
            'name' => $tokenLabel,
            'permissions' => $permissions,
            'expires_at' => null,
        ]);

        $stmt = db()->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
        $stmt->execute([
            ':last_login_at' => now(),
            ':id' => (int) $user['id'],
        ]);

        $this->json([
            'ok' => true,
            'data' => [
                'token' => $result['raw_token'],
                'base_url' => $this->resolveApiUrl(),
                'user' => [
                    'id' => (int) $user['id'],
                    'name' => (string) ($user['name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'role' => (string) ($user['role'] ?? ''),
                ],
                'company' => [
                    'id' => (int) $company['id'],
                    'name' => $this->companyName($company),
                ],
                'permissions' => $permissions,
            ],
        ]);
    }

    private function parseBody(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function isValidPassword(array $user, string $password): bool
    {
        $stored = (string) ($user['password_hash'] ?? '');
        if ($stored === '') {
            return false;
        }

        return password_verify($password, $stored) || hash_equals($stored, $password);
    }

    private function isLegacyPasswordHash(array $user, string $password): bool
    {
        $stored = (string) ($user['password_hash'] ?? '');
        return $stored !== '' && hash_equals($stored, $password);
    }

    private function resolveCompany(array $companies, int $requestedCompanyId): ?array
    {
        if ($requestedCompanyId > 0) {
            foreach ($companies as $company) {
                if ((int) ($company['id'] ?? 0) === $requestedCompanyId) {
                    return $company;
                }
            }

            return null;
        }

        foreach ($companies as $company) {
            if ((int) ($company['is_default'] ?? 0) === 1) {
                return $company;
            }
        }

        return $companies[0] ?? null;
    }

    private function companyName(array $company): string
    {
        $trade = trim((string) ($company['trade_name'] ?? ''));
        if ($trade !== '') {
            return $trade;
        }

        $legal = trim((string) ($company['legal_name'] ?? ''));
        if ($legal !== '') {
            return $legal;
        }

        return 'Empresa #' . (int) ($company['id'] ?? 0);
    }

    private function defaultMobilePermissions(): array
    {
        return [
            'students' => ['search', 'get'],
            'invoices' => ['search', 'get'],
            'tickets' => ['create', 'search', 'get'],
        ];
    }

    private function buildTokenLabel(array $user): string
    {
        $identity = trim((string) ($user['username'] ?? ''));
        if ($identity === '') {
            $identity = trim((string) ($user['email'] ?? ''));
        }
        if ($identity === '') {
            $identity = 'user-' . (int) ($user['id'] ?? 0);
        }

        $label = 'App Mobile Diretoria - ' . $identity;
        if (strlen($label) > 120) {
            return substr($label, 0, 120);
        }

        return $label;
    }

    private function resolveApiUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $scriptName = trim((string) ($_SERVER['SCRIPT_NAME'] ?? '/api.php'));

        if ($host === '') {
            return 'api.php';
        }

        return "{$scheme}://{$host}{$scriptName}";
    }
}
