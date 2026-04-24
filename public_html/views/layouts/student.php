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
        ['label' => 'Inicio', 'route' => 'student/dashboard'],
        ['label' => 'Aulas ao Vivo', 'route' => 'student/live'],
    ]
    : [
        ['label' => 'Inicio', 'route' => 'student/dashboard'],
        ['label' => 'Meus Cursos', 'route' => 'student/courses'],
        ['label' => 'Agenda', 'route' => 'student/calendar'],
        ['label' => 'Aulas ao Vivo', 'route' => 'student/live'],
        ['label' => 'Materiais', 'route' => 'student/materials'],
        ['label' => 'Arsenal', 'route' => 'student/arsenal'],
        ['label' => 'Chamados', 'route' => 'student/requests'],
        ['label' => 'Financeiro', 'route' => 'student/finances'],
        ['label' => 'Progresso', 'route' => 'student/progress'],
        ['label' => 'Avaliacoes', 'route' => 'student/exams'],
        ['label' => 'Historico Academico', 'route' => 'student/academic-history'],
    ];
$logoBuild = '20260423-logos-r2';
$studentTicketAlerts = isset($studentTicketAlerts) && is_array($studentTicketAlerts) ? $studentTicketAlerts : [];
$studentTicketAlertCount = (int) ($studentTicketAlertCount ?? count($studentTicketAlerts));
$studentTicketAlertIds = array_values(array_filter(array_map('intval', array_column($studentTicketAlerts, 'id')), fn ($id) => $id > 0));
$studentTicketRoute = route('student/requests');
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
                    <a href="<?= route('student/dashboard'); ?>" class="hidden sm:block" title="Ir para Inicio">
                        <span class="aneo-theme-logo-frame aneo-logo-scope-student">
                            <img src="assets/img/aneo_escura_portal_aluno_desktop_48px.png?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal aluno escuro desktop" class="aneo-theme-logo aneo-logo-dark aneo-logo-desktop">
                            <img src="assets/img/aneo_escura_portal_aluno_mobile_40px.png?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal aluno escuro mobile" class="aneo-theme-logo aneo-logo-dark aneo-logo-mobile">
                            <img src="assets/img/aneo_clara_portal_aluno_desktop_48px.png?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal aluno claro desktop" class="aneo-theme-logo aneo-logo-light aneo-logo-desktop">
                            <img src="assets/img/aneo_clara_portal_aluno_mobile_40px.png?v=<?= e($logoBuild); ?>" alt="Logo ANEO portal aluno claro mobile" class="aneo-theme-logo aneo-logo-light aneo-logo-mobile">
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
                            title="Notificacoes">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17.082a23.848 23.848 0 0 1-5.714 0M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                        </svg>
                        <?php if ($studentTicketAlertCount > 0): ?>
                            <span class="absolute -right-1 -top-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white"><?= (int) min(99, $studentTicketAlertCount); ?></span>
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
                             class="absolute right-0 mt-2 w-52 rounded-xl border border-slate-200 bg-white shadow-lg z-50 hidden py-1">
                            <a href="<?= route('student/exchange'); ?>"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-sky-50 hover:text-sky-700 transition">
                                <svg class="h-4 w-4 text-sky-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 3M21 7.5H7.5"/>
                                </svg>
                                Intercambio Aneo
                            </a>
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
                <a href="<?= route('student/logout'); ?>" class="rounded-lg border border-rose-200 bg-white/90 px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Sair</a>
            </nav>
            <?php if ($isTrialAccess): ?>
                <div class="mx-auto max-w-7xl px-4 pb-4 lg:px-8">
                    <div class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs text-cyan-800">
                        Acesso degustacao ativo para o curso <strong><?= e((string) ($trialAccess['course_name'] ?? '')); ?></strong> em <?= e(date('d/m/Y', strtotime((string) ($trialAccess['access_date'] ?? date('Y-m-d'))))); ?>.
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
<div id="student-alert-modal" data-ticket-ids="<?= e(json_encode($studentTicketAlertIds)); ?>" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/55 p-4">
    <div class="w-full max-w-2xl rounded-xl border border-sky-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <h3 class="text-lg font-semibold text-sky-700">Notificacoes</h3>
                <p class="text-xs text-slate-500">Resumo dos seus chamados em aberto.</p>
            </div>
            <button type="button" data-student-alert-close class="rounded-lg border border-slate-200 px-3 py-1 text-xs hover:bg-slate-50">Fechar</button>
        </div>
        <div class="max-h-[60vh] space-y-2 overflow-y-auto p-4">
            <?php if ($studentTicketAlerts === []): ?>
                <article class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600">
                    Nenhuma notificacao pendente no momento.
                </article>
            <?php else: ?>
                <?php foreach ($studentTicketAlerts as $alert): ?>
                    <?php
                    $ticketId = (int) ($alert['id'] ?? 0);
                    $ticketCode = trim((string) ($alert['ticket_code'] ?? ''));
                    if (!preg_match('/^ANEO\d+$/', $ticketCode) && $ticketId > 0) {
                        $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
                    }
                    ?>
                    <article class="rounded-lg border border-sky-100 bg-sky-50/50 px-3 py-2 text-sm">
                        <p class="font-semibold text-slate-800"><?= e((string) ($alert['subject'] ?? 'Chamado')); ?></p>
                        <p class="mt-1 text-xs text-slate-600">
                            Codigo: <?= e($ticketCode !== '' ? $ticketCode : ('#' . $ticketId)); ?>
                            | Status: <?= e((string) ($alert['status'] ?? 'open')); ?>
                            | Atualizado em: <?= e((string) ($alert['updated_at'] ?? $alert['created_at'] ?? '')); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <button type="button" data-student-alert-close class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Fechar</button>
            <a href="<?= e($studentTicketRoute); ?>" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Abrir Chamados</a>
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
    if (alertBtn && alertModal) {
        var openAlertModal = function () {
            alertModal.classList.remove('hidden');
            alertModal.classList.add('flex');
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
    if (!avatarBtn || !avatarMenu) return;

    avatarBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = avatarMenu.classList.toggle('hidden');
        avatarBtn.setAttribute('aria-expanded', !open ? 'true' : 'false');
    });

    document.addEventListener('click', function () {
        if (!avatarMenu.classList.contains('hidden')) {
            avatarMenu.classList.add('hidden');
            avatarBtn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
</body>
</html>
