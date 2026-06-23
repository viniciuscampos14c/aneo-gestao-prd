<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Portal do Aluno') . ' | ' . config('app.name')); ?></title>
    <script>
        (function () {
            try {
                if (localStorage.getItem('aneo_portal_theme') !== 'light') {
                    document.documentElement.classList.add('dark');
                }
            } catch (error) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/x-icon" href="favicon.ico?v=<?= e((string) (is_file(__DIR__ . '/../../favicon.ico') ? filemtime(__DIR__ . '/../../favicon.ico') : date('YmdHis'))); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png?v=<?= e((string) (is_file(__DIR__ . '/../../favicon-32x32.png') ? filemtime(__DIR__ . '/../../favicon-32x32.png') : date('YmdHis'))); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png?v=<?= e((string) (is_file(__DIR__ . '/../../favicon-16x16.png') ? filemtime(__DIR__ . '/../../favicon-16x16.png') : date('YmdHis'))); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png?v=<?= e((string) (is_file(__DIR__ . '/../../apple-touch-icon.png') ? filemtime(__DIR__ . '/../../apple-touch-icon.png') : date('YmdHis'))); ?>">
    <link rel="manifest" href="site.webmanifest?v=<?= e((string) (is_file(__DIR__ . '/../../site.webmanifest') ? filemtime(__DIR__ . '/../../site.webmanifest') : date('YmdHis'))); ?>">
    <meta name="theme-color" content="#0a1628">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= e((string) (is_file(__DIR__ . '/../../assets/css/app.css') ? filemtime(__DIR__ . '/../../assets/css/app.css') : date('YmdHis'))); ?>">
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-50 via-white to-emerald-50 text-slate-800 portal-modern-theme student-modern-theme">
<?php
$currentRoute = parse_route();
$student = current_student();
$trialAccess = current_student_trial_access();
$isTrialAccess = $trialAccess !== null;
$studentPhoto = trim((string) ($student['profile_photo'] ?? ''));
$studentName = trim((string) ($student['name'] ?? 'Aluno'));
$nameParts = array_values(array_filter(preg_split('/\s+/', $studentName) ?: [], fn ($part) => $part !== ''));
$studentInitials = 'AL';
if ($nameParts !== []) {
    $first = strtoupper(substr($nameParts[0], 0, 1));
    $lastPart = $nameParts[count($nameParts) - 1];
    $last = strtoupper(substr($lastPart, 0, 1));
    $studentInitials = $first . ($last ?: '');
}
$menu = $isTrialAccess
    ? [
        ['label' => 'Início', 'route' => 'student/dashboard'],
        ['label' => 'Aulas ao Vivo', 'route' => 'student/live'],
    ]
    : [
        ['label' => 'Início', 'route' => 'student/dashboard'],
        ['label' => 'Meus Cursos', 'route' => 'student/courses'],
        ['label' => 'Minhas Dúvidas', 'route' => 'student/questions'],
        ['label' => 'Minha Escala', 'route' => 'student/schedule'],
        ['label' => 'Agenda', 'route' => 'student/calendar'],
        ['label' => 'Aulas ao Vivo', 'route' => 'student/live'],
        ['label' => 'Materiais', 'route' => 'student/materials'],
        ['label' => 'Arsenal', 'route' => 'student/arsenal'],
        ['label' => 'Progresso', 'route' => 'student/progress'],
        ['label' => 'Avaliações', 'route' => 'student/exams'],
    ];
$logoBuild = '20260512-brand-kit-v1';
$studentTicketAlerts = isset($studentTicketAlerts) && is_array($studentTicketAlerts) ? $studentTicketAlerts : [];
$studentTicketAlertCount = (int) ($studentTicketAlertCount ?? count($studentTicketAlerts));
$studentLiveAlerts = isset($studentLiveAlerts) && is_array($studentLiveAlerts) ? $studentLiveAlerts : [];
$studentLiveAlertCount = (int) ($studentLiveAlertCount ?? count($studentLiveAlerts));
$studentPortalAlerts = isset($studentPortalAlerts) && is_array($studentPortalAlerts) ? $studentPortalAlerts : [];
$studentPortalAlertCount = (int) ($studentPortalAlertCount ?? count($studentPortalAlerts));
$studentAlertCount = (int) ($studentAlertCount ?? ($studentTicketAlertCount + $studentLiveAlertCount + $studentPortalAlertCount));
$studentTicketAlertIds = array_values(array_filter(array_map('intval', array_column($studentTicketAlerts, 'id')), fn ($id) => $id > 0));
$studentTicketRoute = route('student/requests');
$studentLiveRoute = route('student/live');
$studentScheduleRoute = route('student/schedule');
$studentAlertReadRoute = route('student/alerts/read');
$studentCsrfToken = csrf_token();
?>
<div class="portal-modern-shell min-h-screen">
    <div class="portal-modern-ambient" aria-hidden="true"></div>
    <div class="portal-modern-content min-h-screen">
        <header class="student-modern-header sticky top-0 z-30 border-b border-sky-100 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-8">
                <div class="flex items-center gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-sky-600">ANEO</p>
                        <h1 class="text-lg font-semibold text-slate-900">Portal do Aluno</h1>
                    </div>
                    <a href="<?= route('student/dashboard'); ?>" class="hidden sm:block" title="Ir para Início">
                        <span class="aneo-theme-logo-frame aneo-logo-scope-student">
                            <img src="assets/brand/aneo-wordmark-simples-dark.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal do aluno tema escuro" class="aneo-theme-logo aneo-logo-dark aneo-logo-desktop">
                            <img src="assets/brand/aneo-wordmark-simples-dark.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal do aluno tema escuro" class="aneo-theme-logo aneo-logo-dark aneo-logo-mobile">
                            <img src="assets/brand/aneo-wordmark-simples-light.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal do aluno tema claro" class="aneo-theme-logo aneo-logo-light aneo-logo-desktop">
                            <img src="assets/brand/aneo-wordmark-simples-light.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal do aluno tema claro" class="aneo-theme-logo aneo-logo-light aneo-logo-mobile">
                        </span>
                    </a>
                </div>
                <div class="flex items-center gap-3 text-right text-sm">
                    <div>
                        <p class="font-medium text-slate-900"><?= e($student['name'] ?? 'Aluno'); ?></p>
                        <p class="text-slate-500"><?= e($student['login'] ?? ''); ?></p>
                    </div>
                    <button type="button"
                            data-student-alert-trigger
                            class="relative rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"
                            title="Notificações">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17.082a23.848 23.848 0 0 1-5.714 0M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                        </svg>
                        <?php if ($studentAlertCount > 0): ?>
                            <span data-student-alert-badge class="absolute -right-1 -top-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white"><?= (int) min(99, $studentAlertCount); ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="theme-toggle portal-theme-toggle" data-portal-theme-toggle aria-label="Alternar tema claro e escuro" title="Alternar tema">
                        <svg data-theme-icon-dark xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <svg data-theme-icon-light class="hidden" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </button>
                    <div class="relative" id="student-avatar-wrapper">
                        <button type="button" id="student-avatar-btn"
                                aria-haspopup="true" aria-expanded="false"
                                class="flex items-center focus:outline-none rounded-full ring-2 ring-sky-100 hover:ring-sky-300 transition">
                            <?php if ($studentPhoto !== '' && media_path_available($studentPhoto)): ?>
                                <img src="<?= e($studentPhoto); ?>" alt="Foto do aluno" class="h-10 w-10 rounded-full object-cover">
                            <?php else: ?>
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700"><?= e($studentInitials); ?></div>
                            <?php endif; ?>
                        </button>
                        <div id="student-avatar-menu"
                             class="absolute right-0 mt-2 z-50 hidden w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/30">
                            <div class="border-b border-slate-200 bg-gradient-to-r from-sky-50 via-white to-cyan-50 px-4 py-4 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900 dark:to-cyan-950/60">
                                <div class="flex items-center gap-3">
                                    <?php if ($studentPhoto !== '' && media_path_available($studentPhoto)): ?>
                                        <img src="<?= e($studentPhoto); ?>" alt="Foto do aluno" class="h-12 w-12 rounded-full object-cover ring-2 ring-white shadow-sm">
                                    <?php else: ?>
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-700 ring-2 ring-white shadow-sm dark:bg-sky-900/60 dark:text-sky-100 dark:ring-slate-800"><?= e($studentInitials); ?></div>
                                    <?php endif; ?>
                                    <div class="min-w-0 text-left">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($student['name'] ?? 'Aluno'); ?></p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400"><?= e($student['login'] ?? ''); ?></p>
                                        <p class="mt-1 text-[11px] uppercase tracking-[0.18em] text-sky-600 dark:text-cyan-300">Portal do aluno</p>
                                    </div>
                                </div>
                            </div>
                            <div class="px-2 py-2">
                                <p class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Acessos rápidos</p>
                                <a href="<?= route('student/exchange'); ?>"
                                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-sky-50 hover:text-sky-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-cyan-200">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-900/50 dark:text-cyan-300">
                                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 3M21 7.5H7.5"/>
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-medium">Intercâmbio ANEO</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Solicite e acompanhe seu intercâmbio</p>
                                    </div>
                                </a>
                                <a href="<?= route('student/requests'); ?>"
                                   class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-sky-50 hover:text-sky-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-cyan-200">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5M6 4h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 3V6a2 2 0 0 1 2-2z"/>
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-medium">Chamados</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Abra ou acompanhe atendimentos</p>
                                    </div>
                                </a>
                                <a href="<?= route('student/finances'); ?>"
                                   class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-sky-50 hover:text-sky-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-cyan-200">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-300">
                                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10v12m9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-medium">Financeiro</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Consulte boletos e pagamentos</p>
                                    </div>
                                </a>
                                <a href="<?= route('student/academic-history'); ?>"
                                   class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-sky-50 hover:text-sky-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-cyan-200">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-100 text-cyan-600 dark:bg-cyan-900/50 dark:text-cyan-300">
                                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5S19.832 5.477 21 6.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253"/>
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-medium">Histórico Acadêmico</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Consulte notas, provas e evolução</p>
                                    </div>
                                </a>
                            </div>
                            <div class="border-t border-slate-200 bg-slate-50/70 px-2 py-2 dark:border-slate-700 dark:bg-slate-950/60">
                                <p class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Sessão</p>
                                <a href="<?= route('student/logout'); ?>"
                                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300">
                                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v1"/>
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-medium">Sair</p>
                                        <p class="text-xs text-rose-400 dark:text-rose-400/90">Encerrar acesso ao portal</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="student-modern-nav mx-auto flex max-w-7xl flex-wrap gap-2 px-4 pb-4 lg:px-8">
                <?php foreach ($menu as $item): ?>
                    <?php
                    $isCoursePlayerRoute = $item['route'] === 'student/courses' && str_starts_with($currentRoute, 'student/course');
                    $isActiveRoute = str_starts_with($currentRoute, $item['route']) || $isCoursePlayerRoute;
                    $active = $isActiveRoute ? 'bg-sky-600 text-white shadow-sm shadow-sky-600/20' : 'border border-sky-100 bg-white/90 text-slate-700 hover:bg-sky-50';
                    ?>
                    <a href="<?= route($item['route']); ?>" class="rounded-lg px-3 py-2 text-sm font-medium <?= $active; ?>"><?= e($item['label']); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($isTrialAccess): ?>
                <div class="mx-auto max-w-7xl px-4 pb-4 lg:px-8">
                    <div class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs text-cyan-800">
                        Acesso degustação ativo para o curso <strong><?= e((string) ($trialAccess['course_name'] ?? '')); ?></strong> em <?= e(date('d/m/Y', strtotime((string) ($trialAccess['access_date'] ?? date('Y-m-d'))))); ?>.
                    </div>
                </div>
            <?php endif; ?>
        </header>

        <main class="student-modern-main mx-auto max-w-7xl px-4 py-6 lg:px-8 lg:py-8">
            <?php if ($msg = flash('success')): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($msg); ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($msg); ?></div>
            <?php endif; ?>
            <?= $content; ?>
        </main>
    </div>
</div>
<div id="student-alert-modal" data-ticket-ids="<?= e(json_encode($studentTicketAlertIds)); ?>" class="portal-alert-overlay fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/55 p-4">
    <div class="portal-alert-modal-panel w-full max-w-2xl overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-xl">
        <div class="portal-alert-modal-head flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h3 class="portal-alert-title text-lg font-semibold text-sky-700 dark:text-cyan-200">Notificações</h3>
                <p class="portal-alert-subtitle text-xs text-slate-500 dark:text-slate-400">Resumo de aulas ao vivo, chamados e escalas recentes.</p>
            </div>
            <button type="button" data-student-alert-close class="rounded-lg border border-slate-200 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Fechar</button>
        </div>
        <div class="portal-alert-modal-body max-h-[60vh] space-y-3 overflow-y-auto p-4">
            <?php if ($studentPortalAlerts !== []): ?>
                <div class="mb-3">
                    <p class="portal-alert-section mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Portal do aluno</p>
                    <div class="space-y-2">
                        <?php foreach ($studentPortalAlerts as $alert): ?>
                            <?php
                            $alertType = trim((string) ($alert['notification_type'] ?? 'general'));
                            $alertTitle = trim((string) ($alert['title'] ?? 'Nova escala'));
                            $alertMessage = trim((string) ($alert['message'] ?? 'Você recebeu uma nova alocação de escala.'));
                            $alertCreatedAt = trim((string) ($alert['created_at'] ?? ''));
                            $alertRead = (int) ($alert['is_read'] ?? 0) === 1;
                            $alertLink = trim((string) ($alert['link_url'] ?? '')) !== '' ? trim((string) $alert['link_url']) : $studentScheduleRoute;
                            $alertSectionLabel = match ($alertType) {
                                'exam_published' => 'Avaliação',
                                'exam_result' => 'Resultado',
                                'academic_activity' => 'Agenda',
                                'exchange_request' => 'Intercâmbio',
                                'live_class' => 'Aula ao vivo',
                                'support_ticket_pending' => 'Chamado',
                                'support_ticket_resolved' => 'Chamado',
                                'duty_schedule' => 'Escala',
                                default => 'Portal',
                            };
                            $alertButtonLabel = match ($alertType) {
                                'exam_published' => 'Abrir Avaliações',
                                'exam_result' => 'Ver Resultado',
                                'academic_activity' => 'Abrir Agenda',
                                'exchange_request' => 'Abrir Intercâmbio',
                                'live_class' => 'Abrir Aulas ao Vivo',
                                'support_ticket_pending' => 'Abrir Chamados',
                                'support_ticket_resolved' => 'Abrir Chamados',
                                'duty_schedule' => 'Abrir Minha Escala',
                                default => 'Abrir Notificação',
                            };
                            $alertCardClass = match ($alertType) {
                                'exam_published' => 'border-sky-100 bg-sky-50/70 dark:border-sky-900/60 dark:bg-sky-950/30',
                                'exam_result' => 'border-indigo-100 bg-indigo-50/70 dark:border-indigo-900/60 dark:bg-indigo-950/30',
                                'academic_activity' => 'border-cyan-100 bg-cyan-50/70 dark:border-cyan-900/60 dark:bg-cyan-950/30',
                                'exchange_request' => 'border-amber-100 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/30',
                                'live_class' => 'border-cyan-100 bg-cyan-50/70 dark:border-cyan-900/60 dark:bg-cyan-950/30',
                                'support_ticket_pending' => 'border-amber-100 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/30',
                                'support_ticket_resolved' => 'border-emerald-100 bg-emerald-50/70 dark:border-emerald-900/60 dark:bg-emerald-950/30',
                                default => 'border-emerald-100 bg-emerald-50/70 dark:border-emerald-900/60 dark:bg-emerald-950/30',
                            };
                            ?>
                            <article class="portal-alert-card rounded-xl border px-4 py-3 text-sm <?= $alertCardClass; ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= e($alertSectionLabel); ?></p>
                                        <p class="portal-alert-card-title font-semibold text-slate-800 dark:text-slate-100"><?= e($alertTitle); ?></p>
                                        <p class="portal-alert-card-copy mt-1 text-xs text-slate-600 dark:text-slate-300"><?= e($alertMessage); ?></p>
                                        <p class="portal-alert-card-meta mt-2 text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500">
                                            <?= e($alertCreatedAt !== '' ? date('d/m/Y H:i', strtotime($alertCreatedAt)) : 'Agora'); ?>
                                        </p>
                                    </div>
                                    <span data-portal-alert-status class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold <?= $alertRead ? 'bg-slate-200 text-slate-600' : 'bg-emerald-600 text-white'; ?>">
                                        <?= $alertRead ? 'Lida' : 'Nova'; ?>
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <a href="<?= e($alertLink); ?>" class="portal-alert-btn portal-alert-btn-card inline-flex rounded-lg px-3 py-1.5 text-xs font-semibold">
                                        <?= e($alertButtonLabel); ?>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($studentLiveAlerts !== []): ?>
                <div class="mb-3">
                    <p class="portal-alert-section mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">Aulas ao Vivo</p>
                    <div class="space-y-2">
                        <?php foreach ($studentLiveAlerts as $alert): ?>
                            <?php
                            $liveTitle = trim((string) ($alert['name'] ?? 'Aula ao vivo'));
                            $liveCourseName = trim((string) ($alert['course_name'] ?? ''));
                            $liveMeetingId = trim((string) ($alert['live_meeting_id'] ?? ''));
                            $livePassword = trim((string) ($alert['live_password'] ?? ''));
                            $liveUrl = trim((string) ($alert['live_link'] ?? ''));
                            $liveDatetime = trim((string) ($alert['live_datetime'] ?? ''));
                            ?>
                            <article class="portal-alert-card rounded-xl border border-cyan-100 bg-cyan-50/60 px-4 py-3 text-sm dark:border-cyan-900/60 dark:bg-cyan-950/25">
                                <p class="portal-alert-card-title font-semibold text-slate-800 dark:text-slate-100"><?= e($liveTitle); ?></p>
                                <p class="portal-alert-card-copy mt-1 text-xs text-slate-600 dark:text-slate-300">
                                    <?= $liveCourseName !== '' ? 'Curso: ' . e($liveCourseName) . ' | ' : ''; ?>
                                    Data: <?= e($liveDatetime !== '' ? date('d/m/Y H:i', strtotime($liveDatetime)) : '-'); ?>
                                </p>
                                <p class="portal-alert-card-copy mt-1 text-xs text-slate-600 dark:text-slate-300">
                                    ID: <span class="font-mono font-semibold text-slate-800 dark:text-slate-100"><?= e($liveMeetingId !== '' ? $liveMeetingId : '-'); ?></span>
                                    | Senha: <span class="font-mono font-semibold text-slate-800 dark:text-slate-100"><?= e($livePassword !== '' ? $livePassword : '-'); ?></span>
                                </p>
                                <?php if ($liveUrl !== ''): ?>
                                    <div class="mt-2">
                                        <a href="<?= e($liveUrl); ?>" target="_blank" rel="noopener" class="inline-flex rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">
                                            Entrar na aula
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($studentTicketAlerts !== []): ?>
                <div class="mb-3">
                    <p class="portal-alert-section mb-2 text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Chamados</p>
                    <div class="space-y-2">
                <?php foreach ($studentTicketAlerts as $alert): ?>
                    <?php
                    $ticketId = (int) ($alert['id'] ?? 0);
                    $ticketCode = trim((string) ($alert['ticket_code'] ?? ''));
                    if (!preg_match('/^ANEO\d+$/', $ticketCode) && $ticketId > 0) {
                        $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
                    }
                    ?>
                    <article class="portal-alert-card rounded-xl border border-sky-100 bg-sky-50/50 px-4 py-3 text-sm dark:border-sky-900/60 dark:bg-sky-950/25">
                        <p class="portal-alert-card-title font-semibold text-slate-800 dark:text-slate-100"><?= e((string) ($alert['subject'] ?? 'Chamado')); ?></p>
                        <p class="portal-alert-card-copy mt-1 text-xs text-slate-600 dark:text-slate-300">
                            Código: <?= e($ticketCode !== '' ? $ticketCode : ('#' . $ticketId)); ?>
                            | Status: <?= e((string) ($alert['status'] ?? 'open')); ?>
                            | Atualizado em: <?= e((string) ($alert['updated_at'] ?? $alert['created_at'] ?? '')); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($studentPortalAlerts === [] && $studentLiveAlerts === [] && $studentTicketAlerts === []): ?>
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Nenhuma notificação pendente no momento.
                </article>
            <?php endif; ?>
        </div>
        <div class="portal-alert-modal-foot flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <button type="button" data-student-alert-close class="portal-alert-btn portal-alert-btn-close rounded-lg px-4 py-2 text-sm font-semibold">Fechar</button>
            <a href="<?= e($studentScheduleRoute); ?>" class="portal-alert-btn portal-alert-btn-schedule rounded-lg px-4 py-2 text-sm font-semibold">Minha Escala</a>
            <a href="<?= e($studentLiveRoute); ?>" class="portal-alert-btn portal-alert-btn-live rounded-lg px-4 py-2 text-sm font-semibold">Aulas ao Vivo</a>
            <a href="<?= e($studentTicketRoute); ?>" class="portal-alert-btn portal-alert-btn-primary rounded-lg px-4 py-2 text-sm font-semibold">Abrir Chamados</a>
        </div>
    </div>
</div>
<script>
(function () {
    var btn  = document.querySelector('[data-portal-theme-toggle]');
    var moon = document.querySelector('[data-theme-icon-dark]');
    var sun  = document.querySelector('[data-theme-icon-light]');
    var key = 'aneo_portal_theme';
    if (!btn) { return; }

    function applyTheme(theme) {
        var isDark = theme === 'dark';

        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        if (moon) {
            moon.classList.toggle('hidden', !isDark);
        }
        if (sun) {
            sun.classList.toggle('hidden', isDark);
        }

        btn.setAttribute('aria-label', isDark ? 'Alternar para tema claro' : 'Alternar para tema escuro');
        btn.setAttribute('title', isDark ? 'Alternar para tema claro' : 'Alternar para tema escuro');
    }

    function getTheme() {
        try {
            return localStorage.getItem(key) === 'light' ? 'light' : 'dark';
        } catch (error) {
            return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        }
    }

    var currentTheme = getTheme();
    applyTheme(currentTheme);

    btn.addEventListener('click', function () {
        currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(currentTheme);
        try { localStorage.setItem(key, currentTheme); } catch(e) {}
    });
})();

(function () {
    var alertBtn = document.querySelector('[data-student-alert-trigger]');
    var alertModal = document.getElementById('student-alert-modal');
    var alertBadge = document.querySelector('[data-student-alert-badge]');
    var alertReadEndpoint = '<?= e($studentAlertReadRoute); ?>';
    var csrfToken = '<?= e($studentCsrfToken); ?>';
    var alertMarked = false;
    if (alertBtn && alertModal) {
        var openAlertModal = function () {
            alertModal.classList.remove('hidden');
            alertModal.classList.add('flex');
            if (!alertMarked) {
                alertMarked = true;
                var fd = new FormData();
                fd.append('_csrf', csrfToken);

                fetch(alertReadEndpoint, {
                    method: 'POST',
                    body: fd
                })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload || !payload.ok) {
                        return;
                    }

                    if (alertBadge) {
                        var total = Number(payload.studentAlertCount || 0);
                        if (total > 0) {
                            alertBadge.textContent = String(Math.min(99, total));
                        } else {
                            alertBadge.remove();
                            alertBadge = null;
                        }
                    }

                    alertModal.querySelectorAll('[data-portal-alert-status]').forEach(function (badge) {
                        badge.textContent = 'Lida';
                        badge.classList.remove('bg-emerald-600', 'text-white');
                        badge.classList.add('bg-slate-200', 'text-slate-600');
                    });
                })
                .catch(function () {
                    alertMarked = false;
                });
            }
        };
        var closeAlertModal = function () {
            alertModal.classList.add('hidden');
            alertModal.classList.remove('flex');
        };

        alertBtn.addEventListener('click', openAlertModal);

        alertModal.querySelectorAll('[data-student-alert-close]').forEach(function (button) {
            button.addEventListener('click', closeAlertModal);
        });

        alertModal.addEventListener('click', function (event) {
            if (event.target === alertModal) {
                closeAlertModal();
            }
        });
    }
})();

(function () {
    var avatarBtn  = document.getElementById('student-avatar-btn');
    var avatarMenu = document.getElementById('student-avatar-menu');
    var avatarWrap = document.getElementById('student-avatar-wrapper');
    if (!avatarBtn || !avatarMenu) return;

    var openMenu = function () {
        avatarMenu.classList.remove('hidden');
        avatarBtn.setAttribute('aria-expanded', 'true');
    };

    var closeMenu = function () {
        avatarMenu.classList.add('hidden');
        avatarBtn.setAttribute('aria-expanded', 'false');
    };

    avatarBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (avatarMenu.classList.contains('hidden')) {
            openMenu();
        } else {
            closeMenu();
        }
    });

    if (avatarWrap) {
        avatarWrap.addEventListener('mouseenter', openMenu);
        avatarWrap.addEventListener('mouseleave', closeMenu);
    }

    document.addEventListener('click', function () {
        if (!avatarMenu.classList.contains('hidden')) {
            closeMenu();
        }
    });
})();
</script>
</body>
</html>
