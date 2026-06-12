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
            if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'support.php') {
                flash('error', 'Sessao expirada. Atualize a pagina e tente novamente.');
                $route = trim((string) ($_GET['route'] ?? ''), '/');
                $fallbackRoute = $route === 'support/login' ? 'support/login' : 'support';
                header('Location: support.php?route=' . rawurlencode($fallbackRoute));
                exit;
            }

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

function session_idle_timeout_seconds(): int
{
    $minutes = (int) config('security.session_idle_timeout_minutes', 60);
    return max(0, $minutes) * 60;
}

function enforce_session_idle_timeout(string $scope, array $authKeys, string $loginRoute, bool $supportScript = false): void
{
    $hasAuth = false;
    foreach ($authKeys as $key) {
        if (isset($_SESSION[$key])) {
            $hasAuth = true;
            break;
        }
    }

    if (!$hasAuth) {
        return;
    }

    $timeout = session_idle_timeout_seconds();
    if ($timeout <= 0) {
        return;
    }

    $now = time();
    $activityKey = '_last_activity_' . $scope;
    $lastActivity = (int) ($_SESSION[$activityKey] ?? 0);

    if ($lastActivity > 0 && ($now - $lastActivity) > $timeout) {
        foreach ($authKeys as $key) {
            unset($_SESSION[$key]);
        }
        unset($_SESSION[$activityKey]);

        flash('error', 'Sessao expirada por inatividade. Entre novamente para continuar.');
        session_regenerate_id(true);

        if ($supportScript) {
            header('Location: support.php?route=' . rawurlencode($loginRoute));
            exit;
        }

        redirect($loginRoute);
    }

    $_SESSION[$activityKey] = $now;
}

function is_admin(): bool
{
    $user = current_user();
    return is_array($user) && ((string) ($user['role'] ?? '')) === 'admin';
}

function is_professor(): bool
{
    $user = current_user();
    return is_array($user) && ((string) ($user['role'] ?? '')) === 'professor';
}

function is_certificador(): bool
{
    $user = current_user();
    return is_array($user) && ((string) ($user['role'] ?? '')) === 'certificador';
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

    // O perfil professor precisa sempre conseguir voltar para a home dedicada.
    if (($user['role'] ?? '') === 'professor' && $module === 'dashboard') {
        return true;
    }

    if (($user['role'] ?? '') === 'suporte') {
        if ($customPermissions !== []) {
            return in_array($module, $customPermissions, true);
        }

        return in_array($module, $rolePermissions, true);
    }

    return in_array($module, $customPermissions, true) || in_array($module, $rolePermissions, true);
}

function default_admin_route(): string
{
    if (!is_logged_in()) {
        return 'login';
    }

    if (current_company_id() === null) {
        return 'select-company';
    }

    if (is_professor()) {
        return 'students';
    }

    if (is_certificador()) {
        return 'certification';
    }

    $priority = [
        'dashboard' => 'dashboard',
        'certification' => 'certification',
        'gda' => 'gestao-aluno',
        'students' => 'students',
        'courses' => 'courses',
        'leads' => 'leads',
        'kanban' => 'kanban',
        'finance' => 'finance/invoices',
        'chatwoot' => 'chatwoot',
        'signatures' => 'signatures',
        'arsenal' => 'arsenal',
        'requests' => 'requests',
        'automations' => 'automations',
        'help' => 'help',
        'companies' => 'companies',
        'users' => 'users',
    ];

    foreach ($priority as $permission => $route) {
        if (has_permission($permission)) {
            return $route;
        }
    }

    return 'logout';
}

function require_auth(): void
{
    enforce_session_idle_timeout('admin', ['user', 'user_companies', 'company'], 'login');

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
        redirect(default_admin_route());
    }
}

function require_admin(): void
{
    require_auth();
    if (!is_admin()) {
        flash('error', 'Acesso restrito ao administrador.');
        redirect(default_admin_route());
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
    enforce_session_idle_timeout('student', ['student'], 'student/login');

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
    $target = route($path);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    }

    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . e($target) . '">';
    echo '<title>Redirecionando...</title>';
    echo '</head><body>';
    echo '<script>window.location.replace(' . json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ');</script>';
    echo '<p>Redirecionando... <a href="' . e($target) . '">Clique aqui se a pagina nao abrir automaticamente</a>.</p>';
    echo '</body></html>';
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
        'renegotiated' => 'Renegociada',
        'overdue' => 'Vencido',
        'cancelled' => 'Cancelado',
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

function menu_icon_svg(string $name, string $class = 'h-4 w-4'): string
{
    static $icons = [
        'home' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10.5 12 4l9 6.5v8.5a1 1 0 0 1-1 1h-5.5V14h-5v6H4a1 1 0 0 1-1-1z" />
        ',
        'chart-bar' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h16M7 16V9m5 7V6m5 10v-4" />
        ',
        'user-group' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 18.5v-1a3 3 0 0 0-3-3H8.5a3 3 0 0 0-3 3v1M9 10a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm8 8.5v-1a2.5 2.5 0 0 0-2.5-2.5H14m.5-5a2.25 2.25 0 1 0 0-4.5" />
        ',
        'users' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19v-1.2A2.8 2.8 0 0 0 12.2 15H8.8A2.8 2.8 0 0 0 6 17.8V19m4.5-7a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm8.5 7v-1a2.5 2.5 0 0 0-2.5-2.5h-.5m1-4.5a2 2 0 1 0 0-4" />
        ',
        'sparkles' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 3 1.4 3.6L17 8l-3.6 1.4L12 13l-1.4-3.6L7 8l3.6-1.4zM5.5 13l.8 2.1L8.5 16l-2.2.9L5.5 19l-.8-2.1L2.5 16l2.2-.9zM18.5 13l.8 2.1 2.2.9-2.2.9-.8 2.1-.8-2.1-2.2-.9 2.2-.9z" />
        ',
        'currency-dollar' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m3.8-12.5c0-1.1-1.1-2-3.1-2s-3.2.9-3.2 2 1 1.8 3.2 2.4c2.2.5 3.1 1.4 3.1 2.6 0 1.2-1.1 2-3.1 2s-3.2-.9-3.2-2" />
        ',
        'chat-bubble-left-right' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 18H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2m2 12 4-3h1a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h3z" />
        ',
        'academic-cap' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 9 9-4 9 4-9 4z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 11v3.5c0 1.7 2.2 3 5 3s5-1.3 5-3V11" />
        ',
        'document-check' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 14 2 2 4-4" />
        ',
        'book-open' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6.5A2.5 2.5 0 0 1 6.5 4H11a3 3 0 0 1 3 3V20H8a4 4 0 0 0-4 0zM20 6.5A2.5 2.5 0 0 0 17.5 4H13a3 3 0 0 0-3 3V20h6a4 4 0 0 1 4 0z" />
        ',
        'inbox-arrow-down' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16l-1.5 10H15l-2 3h-2l-2-3H5.5z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 9.5 0 5m0 0-2-2m2 2 2-2" />
        ',
        'arrow-path' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.5 12a8.5 8.5 0 0 1 14.7-5.8L20 8M20.5 12a8.5 8.5 0 0 1-14.7 5.8L4 16" />
        ',
        'bolt' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 3 5 13h6l-1 8 8-10h-6z" />
        ',
        'question-mark-circle' => '
            <circle cx="12" cy="12" r="9" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.5 9a2.5 2.5 0 1 1 4.5 1.4c-.7.8-2 1.3-2 2.6m0 3h.01" />
        ',
        'building-office' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 20V6a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v14M9 20v-3h6v3M8 9h.01M12 9h.01M16 9h.01M8 12h.01M12 12h.01M16 12h.01" />
        ',
        'envelope' => '
            <rect x="3" y="6" width="18" height="12" rx="2" ry="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4 8 8 6 8-6" />
        ',
        'banknotes' => '
            <rect x="3" y="7" width="18" height="10" rx="2" ry="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 12h.01M17 12h.01M12 12a2 2 0 1 0 0 .01" />
        ',
        'clipboard-document-list' => '
            <rect x="6" y="4" width="12" height="16" rx="2" ry="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 8h6M9 12h6M9 16h4M10 3h4a1 1 0 0 1 1 1v1H9V4a1 1 0 0 1 1-1z" />
        ',
        'clock' => '
            <circle cx="12" cy="12" r="9" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 7v5l3 2" />
        ',
        'code-bracket' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m8 7-4 5 4 5M16 7l4 5-4 5M13.5 5l-3 14" />
        ',
        'document-text' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 10h6M9 14h6M9 18h4" />
        ',
        'calendar-days' => '
            <rect x="3" y="5" width="18" height="16" rx="2" ry="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 3v4m8-4v4M3 10h18" />
        ',
        'video-camera' => '
            <rect x="3" y="7" width="12" height="10" rx="2" ry="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 10 6-3v10l-6-3z" />
        ',
        'folder-open' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v1H3z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 11h18l-1.2 7a2 2 0 0 1-2 1.7H6.2A2 2 0 0 1 4.2 18z" />
        ',
        'archive-box' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16v4H4zM5 11h14v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 14h4" />
        ',
        'wallet' => '
            <rect x="3" y="6" width="18" height="12" rx="2" ry="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 12h7M16 12h.01" />
        ',
        'clipboard-check' => '
            <rect x="6" y="4" width="12" height="16" rx="2" ry="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 3h4a1 1 0 0 1 1 1v1H9V4a1 1 0 0 1 1-1zm-.5 11 2 2 3.5-4" />
        ',
        'arrow-right-on-rectangle' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M14 16l4-4-4-4M18 12H9" />
        ',
        'squares-2x2' => '
            <rect x="4" y="4" width="7" height="7" rx="1.2" stroke-width="1.8" />
            <rect x="13" y="4" width="7" height="7" rx="1.2" stroke-width="1.8" />
            <rect x="4" y="13" width="7" height="7" rx="1.2" stroke-width="1.8" />
            <rect x="13" y="13" width="7" height="7" rx="1.2" stroke-width="1.8" />
        ',
    ];

    $key = trim(strtolower($name));
    $paths = $icons[$key] ?? '
        <circle cx="12" cy="12" r="8" stroke-width="1.8" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01" />
    ';

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">' . $paths . '</svg>';
}
