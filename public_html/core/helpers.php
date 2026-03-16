<?php

function config(?string $path = null, $default = null)
{
    $config = $GLOBALS['config'] ?? [];
    if ($path === null) {
        return $config;
    }

    $segments = explode('.', $path);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function db(): PDO
{
    return $GLOBALS['db'];
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_validate(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? '';
        if (!$token || !hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
            http_response_code(419);
            exit('Token CSRF invalido. Atualize a pagina e tente novamente.');
        }
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function available_companies(): array
{
    $companies = $_SESSION['user_companies'] ?? [];
    return is_array($companies) ? $companies : [];
}

function current_company(): ?array
{
    $company = $_SESSION['company'] ?? null;
    return is_array($company) ? $company : null;
}

function current_company_id(): ?int
{
    $company = current_company();
    if (!$company || empty($company['id'])) {
        return null;
    }

    return (int) $company['id'];
}

function has_company_access(int $companyId): bool
{
    foreach (available_companies() as $company) {
        if ((int) ($company['id'] ?? 0) === $companyId) {
            return true;
        }
    }

    return false;
}

function set_current_company(array $company): void
{
    $_SESSION['company'] = [
        'id' => (int) ($company['id'] ?? 0),
        'legal_name' => (string) ($company['legal_name'] ?? ''),
        'trade_name' => (string) ($company['trade_name'] ?? ''),
        'cnpj' => (string) ($company['cnpj'] ?? ''),
    ];
}

function clear_current_company(): void
{
    unset($_SESSION['company']);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return is_array($user) && ((string) ($user['role'] ?? '')) === 'admin';
}

function current_student(): ?array
{
    return $_SESSION['student'] ?? null;
}

function is_student_logged_in(): bool
{
    return current_student() !== null;
}

function current_student_trial_access(): ?array
{
    $student = current_student();
    if (!is_array($student)) {
        return null;
    }

    $trial = $student['trial_access'] ?? null;
    if (!is_array($trial) || empty($trial['is_trial'])) {
        return null;
    }

    return $trial;
}

function is_student_trial_access(): bool
{
    return current_student_trial_access() !== null;
}

function role_label(?string $role): string
{
    return config('roles.' . $role . '.label', ucfirst((string) $role));
}

function has_permission(string $module): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $rolePermissions = config('roles.' . $user['role'] . '.permissions', []);
    if (in_array('*', $rolePermissions, true)) {
        return true;
    }

    $customPermissions = $user['permission_keys'] ?? [];

    if (($user['role'] ?? '') === 'suporte') {
        if ($customPermissions !== []) {
            return in_array($module, $customPermissions, true);
        }

        return in_array($module, $rolePermissions, true);
    }

    return in_array($module, $customPermissions, true) || in_array($module, $rolePermissions, true);
}

function require_auth(): void
{
    if (!is_logged_in()) {
        redirect('login');
    }

    $route = parse_route();
    if (!in_array($route, ['select-company', 'set-company', 'logout'], true) && current_company_id() === null) {
        redirect('select-company');
    }

    enforce_current_company_license($route);
}

function require_permission(string $module): void
{
    if (!has_permission($module)) {
        flash('error', 'Voce nao possui permissao para este modulo.');
        redirect('dashboard');
    }
}

function require_admin(): void
{
    require_auth();
    if (!is_admin()) {
        flash('error', 'Acesso restrito ao administrador.');
        redirect('dashboard');
    }
}

function licensing_enabled(): bool
{
    return (bool) config('licensing.enabled', false);
}

function licensing_enforced(): bool
{
    return licensing_enabled() && (bool) config('licensing.enforce', false);
}

function enforce_current_company_license(?string $route = null): void
{
    if (!licensing_enforced()) {
        return;
    }

    $route = trim((string) ($route ?? parse_route()));
    if ($route === '') {
        return;
    }

    $allowedRoutes = [
        'select-company',
        'set-company',
        'logout',
        'companies/license',
        'companies/license/activate',
    ];
    if (in_array($route, $allowedRoutes, true)) {
        return;
    }

    $companyId = (int) (current_company_id() ?? 0);
    if ($companyId <= 0) {
        return;
    }

    $license = new LicenseService();
    if (!$license->available()) {
        return;
    }

    $status = $license->currentStatus($companyId);
    if (!empty($status['is_valid']) || !empty($status['within_grace'])) {
        return;
    }

    if (is_admin()) {
        flash('error', 'Licenca expirada para a empresa atual. Renove em Cadastro > Licenca.');
        redirect('companies/license&company_id=' . $companyId);
    }

    flash('error', 'Licenca expirada para a empresa atual. Contate um administrador.');
    redirect('logout');
}

function require_student_auth(): void
{
    if (!is_student_logged_in()) {
        redirect('student/login');
    }

    enforce_student_trial_access(parse_route());
}

function enforce_student_trial_access(?string $route = null): void
{
    $student = current_student();
    if (!is_array($student)) {
        return;
    }

    $trial = current_student_trial_access();
    $studentId = (int) ($student['id'] ?? 0);
    if ($studentId > 0) {
        $portal = new StudentPortalModel();
        $freshTrial = $portal->trialAccessContext($studentId);
        if (!empty($freshTrial['is_trial'])) {
            $_SESSION['student']['trial_access'] = [
                'is_trial' => true,
                'allowed_today' => !empty($freshTrial['allowed_today']),
                'course_id' => (int) ($freshTrial['course_id'] ?? 0),
                'course_name' => (string) ($freshTrial['course_name'] ?? ''),
                'access_date' => (string) ($freshTrial['access_date'] ?? ''),
                'status' => (string) ($freshTrial['status'] ?? ''),
                'access_scope' => (string) ($freshTrial['access_scope'] ?? ''),
            ];
            $trial = $_SESSION['student']['trial_access'];
        } else {
            unset($_SESSION['student']['trial_access']);
            $trial = null;
        }
    }

    if ($trial === null || empty($trial['is_trial'])) {
        return;
    }

    $accessDate = trim((string) ($trial['access_date'] ?? ''));
    $today = date('Y-m-d');
    $status = trim((string) ($trial['status'] ?? ''));

    if ($status === 'revoked') {
        unset($_SESSION['student']);
        flash('error', 'Este acesso de degustacao foi revogado pelo administrador.');
        redirect('student/login');
    }

    if ($status === 'expired') {
        unset($_SESSION['student']);
        $formattedDate = $accessDate !== '' ? date('d/m/Y', strtotime($accessDate)) : '-';
        flash('error', 'Este acesso de degustacao expirou. O dia liberado foi ' . $formattedDate . '.');
        redirect('student/login');
    }

    if ($accessDate === '' || $accessDate !== $today || empty($trial['allowed_today'])) {
        unset($_SESSION['student']);
        $formattedDate = $accessDate !== '' ? date('d/m/Y', strtotime($accessDate)) : '-';
        flash('error', 'Acesso de degustacao permitido apenas em ' . $formattedDate . '.');
        redirect('student/login');
    }

    $route = trim((string) ($route ?? parse_route()));
    $allowedRoutes = [
        'student',
        'student/dashboard',
        'student/live',
        'student/logout',
    ];

    if (!in_array($route, $allowedRoutes, true)) {
        flash('error', 'Acesso de degustacao permite apenas aula ao vivo do curso liberado.');
        redirect('student/live');
    }
}

function route(string $path = ''): string
{
    if ($path === '') {
        return 'index.php';
    }

    $normalized = str_replace('?', '&', $path);
    $parts = array_values(array_filter(explode('&', $normalized), fn ($part) => $part !== ''));
    $route = trim((string) array_shift($parts), '/');

    $query = ['route' => $route];
    foreach ($parts as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
        if ($key !== '') {
            $query[$key] = urldecode($value);
        }
    }

    return 'index.php?' . http_build_query($query);
}

function redirect(string $path): void
{
    header('Location: ' . route($path));
    exit;
}

function view(string $view, array $data = [], string $layout = 'layouts/app'): void
{
    $viewPath = __DIR__ . '/../views/' . $view . '.php';
    $layoutPath = __DIR__ . '/../views/' . $layout . '.php';

    if (!is_file($viewPath)) {
        http_response_code(500);
        exit('View nao encontrada: ' . e($view));
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $viewPath;
    $content = ob_get_clean();

    require $layoutPath;
}

function request(string $key, $default = null)
{
    return $_REQUEST[$key] ?? $default;
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function format_currency($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function parse_decimal(string $value): float
{
    $clean = preg_replace('/[^0-9,.-]/', '', $value);
    $hasComma = str_contains($clean, ',');
    $hasDot = str_contains($clean, '.');

    if ($hasComma && $hasDot) {
        $normalized = str_replace('.', '', $clean);
        $normalized = str_replace(',', '.', $normalized);
    } elseif ($hasComma) {
        $normalized = str_replace(',', '.', $clean);
    } else {
        $normalized = $clean;
    }

    return (float) $normalized;
}

function invoice_status_label(?string $status): string
{
    $status = strtolower(trim((string) $status));

    return match ($status) {
        'draft' => 'Rascunho',
        'open' => 'Em aberto',
        'partial' => 'Parcial',
        'paid' => 'Pago',
        'overdue' => 'Vencido',
        default => $status !== '' ? ucfirst($status) : '-',
    };
}

function parse_route(): string
{
    $route = $_GET['route'] ?? '';

    if ($route !== '') {
        return trim($route, '/');
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');

    if ($script !== '/' && str_starts_with($uri, $script)) {
        $uri = substr($uri, strlen($script));
    }

    $uri = trim($uri, '/');
    return ($uri === '' || $uri === 'index.php') ? 'dashboard' : $uri;
}

function pagination_meta(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);

    return [
        'total' => $total,
        'per_page' => $perPage,
        'page' => $page,
        'pages' => $pages,
    ];
}

function build_query(array $extra = []): string
{
    $query = $_GET;
    foreach ($extra as $k => $v) {
        $query[$k] = $v;
    }
    return http_build_query($query);
}

function media_path_available(?string $path): bool
{
    $path = trim((string) $path);
    if ($path === '') {
        return false;
    }

    if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
        return true;
    }

    $baseDir = realpath(__DIR__ . '/..');
    if ($baseDir === false) {
        return false;
    }

    $candidate = realpath(__DIR__ . '/../' . ltrim($path, '/\\'));
    if ($candidate === false || !is_file($candidate)) {
        return false;
    }

    return str_starts_with($candidate, $baseDir);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function whatsapp_number(?string $phone, ?string $countryCode = null): ?string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return null;
    }

    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    $countryCode = preg_replace('/\D+/', '', (string) ($countryCode ?? config('whatsapp.default_country_code', '55')));
    if ($countryCode === '') {
        $countryCode = '55';
    }

    if (!str_starts_with($digits, $countryCode)) {
        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }
        $digits = $countryCode . $digits;
    }

    return strlen($digits) >= 10 ? $digits : null;
}

function whatsapp_link(?string $phone, string $message = ''): ?string
{
    $number = whatsapp_number($phone);
    if (!$number) {
        return null;
    }

    $url = 'https://wa.me/' . rawurlencode($number);
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }

    return $url;
}
