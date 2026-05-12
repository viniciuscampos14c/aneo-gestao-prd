<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Sistema') . ' | ' . config('app.name')); ?></title>
    <script>
        (function () {
            try {
                if (localStorage.getItem('aneo_admin_theme') === 'light') {
                    document.documentElement.classList.add('admin-theme-light');
                }
                if (localStorage.getItem('aneo_admin_sidebar') === 'collapsed') {
                    document.documentElement.classList.add('admin-sidebar-collapsed');
                }
            } catch (error) {
                // Ignora bloqueio de localStorage e segue com tema padrao.
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
    <link rel="stylesheet" href="assets/css/app.css?v=<?= e((string) (is_file(__DIR__ . '/../../assets/css/app.css') ? filemtime(__DIR__ . '/../../assets/css/app.css') : date('YmdHis'))); ?>&build=20260423-arsenal">
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 admin-modern-theme">
<?php
$currentRoute = parse_route();
$user = current_user();
$company = current_company();
$menu = [
    ['module' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'chart-bar', 'route' => 'dashboard'],
    ['module' => 'gda', 'label' => 'Gestão do Aluno', 'icon' => 'user-group', 'route' => 'gestao-aluno'],
    ['module' => 'students', 'label' => 'Alunos', 'icon' => 'users', 'route' => 'students'],
    ['module' => 'student_schedule', 'label' => 'Escala Aluno', 'icon' => 'calendar-days', 'route' => 'escala-aluno'],
    ['module' => 'leads', 'label' => 'Leads', 'icon' => 'sparkles', 'route' => 'leads'],
    ['module' => 'finance', 'label' => 'Financeiro', 'icon' => 'currency-dollar', 'route' => 'finance/invoices'],
    ['module' => 'chatwoot', 'label' => 'Atendimento', 'icon' => 'chat-bubble-left-right', 'route' => 'chatwoot'],
    ['module' => 'courses', 'label' => 'Cursos EAD', 'icon' => 'academic-cap', 'route' => 'courses'],
    ['module' => 'signatures', 'label' => 'Assinaturas', 'icon' => 'document-check', 'route' => 'signatures'],
    ['module' => 'arsenal', 'label' => 'Arsenal Digital', 'icon' => 'book-open', 'route' => 'arsenal'],
    ['module' => 'requests', 'label' => 'Solicitações', 'icon' => 'inbox-arrow-down', 'route' => 'requests'],
    ['module' => 'students', 'label' => 'Intercâmbio Aluno', 'icon' => 'arrow-path', 'route' => 'exchange'],
    ['module' => 'help', 'label' => 'Chat IA Jully', 'icon' => 'question-mark-circle', 'route' => 'help'],
];

try {
    if (class_exists('SystemModuleRuntime')) {
        foreach ((new SystemModuleRuntime())->activeMenuItems('main') as $moduleMenuItem) {
            $menu[] = $moduleMenuItem;
        }
    }
} catch (Throwable $e) {
    // Se o banco ainda nao tem as tabelas de modulos, apenas nao exibe menus dinamicos.
}

$cadastroMenu = [
    ['module' => 'users', 'label' => 'Usuarios', 'icon' => 'users', 'route' => 'users'],
    ['module' => 'companies', 'label' => 'Empresas', 'icon' => 'building-office', 'route' => 'companies'],
    ['module' => 'companies', 'label' => 'SMTP Email', 'icon' => 'envelope', 'route' => 'companies/smtp'],
    ['module' => 'data_imports', 'label' => 'Importação de Dados', 'icon' => 'inbox-arrow-down', 'route' => 'data-imports'],
    ['module' => 'system_modules', 'label' => 'Modulos do Sistema', 'icon' => 'archive-box', 'route' => 'system-modules'],
];

try {
    if (class_exists('SystemModuleRuntime')) {
        foreach ((new SystemModuleRuntime())->activeMenuItems('cadastro') as $moduleMenuItem) {
            $cadastroMenu[] = $moduleMenuItem;
        }
    }
} catch (Throwable $e) {
    // Mantem o menu base caso o runtime de modulos ainda nao esteja pronto.
}

if (has_permission('automations')) {
    $cadastroMenu[] = ['module' => 'automations', 'label' => 'Automações', 'icon' => 'bolt', 'route' => 'automations'];
}

if (class_exists('BanksController')) {
    $cadastroMenu[] = ['module' => 'companies', 'label' => 'Bancos', 'icon' => 'banknotes', 'route' => 'banks'];
}

if (($user['role'] ?? '') === 'admin') {
    $cadastroMenu[] = ['module' => 'dashboard', 'label' => 'Gerenciamento de API', 'icon' => 'code-bracket', 'route' => 'api-management'];
    $cadastroMenu[] = ['module' => 'dashboard', 'label' => 'Manual da API', 'icon' => 'document-text', 'route' => 'api-management/manual'];
    $cadastroMenu[] = ['module' => 'dashboard', 'label' => 'Logs de Sistema', 'icon' => 'clipboard-document-list', 'route' => 'system/logs'];
    $cadastroMenu[] = ['module' => 'dashboard', 'label' => 'Cron Jobs', 'icon' => 'clock', 'route' => 'cron'];
}

$cadastroItemsVisible = [];
foreach ($cadastroMenu as $cadItem) {
    if (has_permission($cadItem['module'])) {
        $cadastroItemsVisible[] = $cadItem;
    }
}
$showCadastro = $cadastroItemsVisible !== [];
$cadastroActive = false;
foreach ($cadastroItemsVisible as $cadItem) {
    if (str_starts_with($currentRoute, $cadItem['route'])) {
        $cadastroActive = true;
        break;
    }
}
$isUsersPreviewRoute = str_starts_with($currentRoute, 'users');
$isDashboardPreviewRoute = $currentRoute === 'dashboard';
$previewMainClass = 'admin-modern-main';
if ($isUsersPreviewRoute) {
    $previewMainClass .= ' users-preview-main';
} elseif ($isDashboardPreviewRoute) {
    $previewMainClass .= ' dashboard-preview-main';
}
$appJsPath = __DIR__ . '/../../assets/js/app.js';
$appJsVersion = is_file($appJsPath) ? (string) filemtime($appJsPath) : date('YmdHis');
$logoBuild = '20260512-brand-kit-v1';
$mobileNegotiationAlerts = isset($mobileNegotiationAlerts) && is_array($mobileNegotiationAlerts) ? $mobileNegotiationAlerts : [];
$mobileNegotiationAlertCount = (int) ($mobileNegotiationAlertCount ?? count($mobileNegotiationAlerts));
$mobileNegotiationAlertIds = array_values(array_filter(array_map('intval', array_column($mobileNegotiationAlerts, 'id')), fn ($id) => $id > 0));
$mobileQueueRoute = route('requests&source=api&mobile_flow=1&status=pending');
?>
<div class="admin-modern-shell flex min-h-screen">
    <aside id="sidebar" class="admin-modern-sidebar fixed inset-y-0 left-0 z-40 flex flex-col transform bg-slate-900 text-slate-100 shadow-xl transition-transform lg:translate-x-0 -translate-x-full">
        <div class="flex h-16 items-center justify-between border-b border-slate-800 px-6 admin-sidebar-head">
            <div class="admin-sidebar-brand">
                <p class="text-xs uppercase tracking-[0.2em] text-cyan-400">ANEO</p>
                <h1 class="text-lg font-semibold admin-sidebar-brand-title">Gestao Integrada</h1>
            </div>
            <button class="lg:hidden" data-sidebar-close>
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="admin-sidebar-nav min-h-0 flex-1 overflow-y-auto p-4">
            <ul class="space-y-1">
                <?php foreach ($menu as $item): ?>
                    <?php if (!has_permission($item['module'])) { continue; } ?>
                    <?php $active = str_starts_with($currentRoute, $item['route']) ? 'bg-slate-800 text-cyan-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>
                    <li>
                        <a href="<?= route($item['route']); ?>" class="admin-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition <?= $active; ?>" title="<?= e($item['label']); ?>">
                            <?= menu_icon_svg((string) ($item['icon'] ?? 'squares-2x2'), 'h-4 w-4 flex-shrink-0'); ?>
                            <span class="admin-sidebar-link-label"><?= e($item['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if ($showCadastro): ?>
                    <li class="pt-1 admin-sidebar-group" data-admin-group="cadastro">
                        <button type="button" data-cadastro-trigger aria-haspopup="true" aria-expanded="false" class="admin-sidebar-group-trigger flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition <?= $cadastroActive ? 'bg-slate-800 text-cyan-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>" title="Cadastro">
                            <span class="flex items-center gap-3 min-w-0">
                                <?= menu_icon_svg('squares-2x2', 'h-4 w-4 flex-shrink-0'); ?>
                                <span class="admin-sidebar-link-label">Cadastro</span>
                            </span>
                            <svg data-cadastro-chevron class="admin-sidebar-group-chevron h-4 w-4 text-slate-400 transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 6l6 6-6 6"/>
                            </svg>
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if ($showCadastro): ?>
            <div data-cadastro-panel class="hidden fixed z-[80] min-w-[240px] rounded-2xl border border-slate-700 bg-slate-900 p-2 shadow-2xl">
                <div class="admin-sidebar-popout-head px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-cyan-400">Cadastro</p>
                </div>
                <?php foreach ($cadastroItemsVisible as $cadItem): ?>
                    <?php $cadActive = str_starts_with($currentRoute, $cadItem['route']) ? 'bg-slate-800 text-cyan-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white'; ?>
                    <a href="<?= route($cadItem['route']); ?>" class="admin-sidebar-popout-link flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition <?= $cadActive; ?>">
                        <?= menu_icon_svg((string) ($cadItem['icon'] ?? 'squares-2x2'), 'h-4 w-4 flex-shrink-0'); ?>
                        <?= e($cadItem['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="admin-sidebar-footer flex-shrink-0 border-t border-slate-800 px-6 py-4">
            <button type="button" data-sidebar-collapse class="admin-sidebar-collapse-btn hidden w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-300 transition hover:border-slate-600 hover:bg-slate-800 hover:text-white lg:flex" aria-pressed="false">
                <span class="admin-sidebar-collapse-copy flex items-center gap-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 6l-6 6 6 6"/></svg>
                    <span class="admin-sidebar-link-label">Recolher</span>
                </span>
                <span class="admin-sidebar-collapse-icon hidden">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 6l6 6-6 6"/></svg>
                </span>
            </button>
            <?php $companyName = $company ? (trim((string) ($company['trade_name'] ?? '')) !== '' ? (string) $company['trade_name'] : (string) ($company['legal_name'] ?? 'Empresa')) : 'Empresa'; ?>
            <section class="admin-sidebar-session-card">
                <?php if ($company): ?>
                    <div class="admin-sidebar-session-block admin-sidebar-link-label">
                        <div class="admin-sidebar-session-line">
                            <p class="admin-sidebar-session-label admin-sidebar-session-label-company">Empresa</p>
                            <a href="<?= route('select-company'); ?>" class="admin-sidebar-session-link">Trocar</a>
                        </div>
                        <p class="admin-sidebar-session-title truncate"><?= e($companyName); ?></p>
                    </div>
                <?php endif; ?>
                <div class="admin-sidebar-session-divider admin-sidebar-link-label"></div>
                <div class="admin-sidebar-session-block admin-sidebar-link-label">
                    <div class="admin-sidebar-session-line">
                        <p class="admin-sidebar-session-label admin-sidebar-session-label-user">Usuário</p>
                        <a href="<?= route('logout'); ?>" class="admin-sidebar-session-link admin-sidebar-session-logout">Sair</a>
                    </div>
                    <p class="admin-sidebar-session-title truncate"><?= e($user['name'] ?? ''); ?></p>
                    <p class="admin-sidebar-session-role truncate"><?= e(role_label($user['role'] ?? '')); ?></p>
                </div>
            </section>
        </div>
    </aside>

    <div class="admin-modern-content flex min-w-0 flex-1 flex-col">
        <header class="admin-modern-header sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex h-16 items-center gap-3 px-4 lg:px-8">
                <button class="rounded-lg border border-slate-200 p-2 lg:hidden" data-sidebar-open>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="<?= route('dashboard'); ?>" class="hidden items-center md:flex" title="Ir para Home">
                    <span class="aneo-theme-logo-frame aneo-logo-scope-admin">
                        <img src="assets/brand/aneo-wordmark-simples-dark.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO administrativo tema escuro" class="aneo-theme-logo aneo-logo-dark aneo-logo-desktop">
                        <img src="assets/brand/aneo-wordmark-simples-dark.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO administrativo tema escuro" class="aneo-theme-logo aneo-logo-dark aneo-logo-mobile">
                        <img src="assets/brand/aneo-wordmark-simples-light.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO administrativo tema claro" class="aneo-theme-logo aneo-logo-light aneo-logo-desktop">
                        <img src="assets/brand/aneo-wordmark-simples-light.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO administrativo tema claro" class="aneo-theme-logo aneo-logo-light aneo-logo-mobile">
                    </span>
                </a>
                <form class="flex-1" action="<?= route('search'); ?>" method="get">
                    <input type="hidden" name="route" value="search">
                    <input type="text" name="q" value="<?= e($_GET['q'] ?? ''); ?>" placeholder="Busca global..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm outline-none focus:border-cyan-500 focus:bg-white">
                </form>
                <a href="<?= route('logout'); ?>" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100" title="Sair do sistema">
                    Sair
                </a>
                <button type="button"
                        data-mobile-neg-trigger
                        data-mobile-neg-queue="<?= e($mobileQueueRoute); ?>"
                        class="relative rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"
                        title="Notificacoes de negociacao mobile">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17.082a23.848 23.848 0 0 1-5.714 0M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                    <?php if ($mobileNegotiationAlertCount > 0): ?>
                        <span class="absolute -right-1 -top-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white"><?= (int) min(99, $mobileNegotiationAlertCount); ?></span>
                    <?php endif; ?>
                </button>
                <button type="button" data-admin-theme-toggle class="theme-toggle admin-theme-toggle" aria-label="Alternar tema claro e escuro" title="Alternar tema">
                    <svg data-theme-icon-dark class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 0 1 11.21 3 7 7 0 1 0 21 12.79z"/>
                    </svg>
                    <svg data-theme-icon-light class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="4.5" stroke-width="1.8"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 2.7v2.2m0 14.2v2.2m9.3-9.3h-2.2M4.9 12H2.7m16.32 6.32-1.56-1.56M6.54 6.54 4.98 4.98m14.04 0-1.56 1.56M6.54 17.46l-1.56 1.56"/>
                    </svg>
                </button>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8 <?= e($previewMainClass); ?>">
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

<div class="fixed bottom-6 right-6 z-50">
    <button id="fab-toggle" class="flex h-14 w-14 items-center justify-center rounded-full bg-cyan-600 text-white shadow-lg shadow-cyan-600/30 hover:bg-cyan-700">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m-7-7h14"/></svg>
    </button>
    <div id="fab-menu" class="mt-3 hidden w-56 space-y-2 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
        <?php if (has_permission('students.create')): ?>
            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50" href="<?= route('students/create'); ?>">+ Novo Aluno</a>
        <?php endif; ?>
        <?php if (has_permission('leads.create')): ?>
            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50" href="<?= route('leads/create'); ?>">+ Novo Lead</a>
        <?php endif; ?>
        <?php if (has_permission('finance.invoice.create')): ?>
            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50" href="<?= route('finance/invoices/create'); ?>">+ Nova Fatura</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($mobileNegotiationAlerts !== []): ?>
    <div id="mobile-negotiation-modal" data-ticket-ids="<?= e(json_encode($mobileNegotiationAlertIds)); ?>" class="admin-alert-overlay fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/55 p-4">
        <div class="admin-alert-modal-panel w-full max-w-2xl overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-xl">
            <div class="admin-alert-modal-head flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-indigo-700">Novas negociacoes do app</h3>
                    <p class="text-xs text-slate-500">A equipe da diretoria enviou solicitacoes financeiras para tratamento.</p>
                </div>
                <button type="button" data-mobile-neg-close class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">Fechar</button>
            </div>
            <div class="admin-alert-modal-body max-h-[60vh] space-y-3 overflow-y-auto p-4">
                <?php foreach ($mobileNegotiationAlerts as $alert): ?>
                    <?php
                    $ticketId = (int) ($alert['id'] ?? 0);
                    $ticketCode = trim((string) ($alert['ticket_code'] ?? ''));
                    if (!preg_match('/^ANEO\d+$/', $ticketCode) && $ticketId > 0) {
                        $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
                    }
                    ?>
                    <article class="admin-alert-card rounded-xl border border-indigo-100 bg-indigo-50/50 px-4 py-3 text-sm">
                        <p class="font-semibold text-slate-800"><?= e((string) ($alert['subject'] ?? 'Negociacao financeira')); ?></p>
                        <p class="mt-1 text-xs text-slate-600">
                            Codigo: <?= e($ticketCode !== '' ? $ticketCode : ('#' . $ticketId)); ?>
                            | Status: <?= e((string) ($alert['status'] ?? 'open')); ?>
                            | Recebido em: <?= e((string) ($alert['created_at'] ?? '')); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="admin-alert-modal-foot flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
                <button type="button" data-mobile-neg-close class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Dispensar</button>
                <a href="<?= e($mobileQueueRoute); ?>" data-mobile-neg-open-queue class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Abrir fila de negociacoes</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="assets/js/app.js?v=<?= e($appJsVersion); ?>"></script>
<?php if ($mobileNegotiationAlerts !== []): ?>
    <script>
        (function () {
            const modal = document.getElementById('mobile-negotiation-modal');
            const trigger = document.querySelector('[data-mobile-neg-trigger]');
            if (!modal) return;

            let ids = [];
            try {
                ids = JSON.parse(modal.dataset.ticketIds || '[]');
            } catch (error) {
                ids = [];
            }
            ids = ids.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0);
            if (ids.length === 0) return;

            const params = new URLSearchParams(window.location.search || '');
            const isQueueRoute = params.get('route') === 'requests' && params.get('mobile_flow') === '1';
            if (isQueueRoute) return;

            const seenKey = (id) => 'aneo_mobile_negotiation_seen_' + id;
            const isSeen = (id) => {
                try {
                    if (localStorage.getItem(seenKey(id))) {
                        return true;
                    }
                } catch (error) {
                }
                try {
                    if (sessionStorage.getItem(seenKey(id))) {
                        return true;
                    }
                } catch (error) {
                }
                return false;
            };

            const unseen = ids.filter((id) => {
                return !isSeen(id);
            });

            const markAsSeen = function () {
                unseen.forEach((id) => {
                    try {
                        localStorage.setItem(seenKey(id), '1');
                    } catch (error) {
                    }
                    try {
                        sessionStorage.setItem(seenKey(id), '1');
                    } catch (error) {
                    }
                });
            };

            const close = function () {
                markAsSeen();
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            const open = function () {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            if (trigger) {
                trigger.addEventListener('click', function () {
                    open();
                });
            }

            if (unseen.length > 0) {
                open();
            }

            modal.querySelectorAll('[data-mobile-neg-close]').forEach((button) => {
                button.addEventListener('click', close);
            });

            const openQueueButton = modal.querySelector('[data-mobile-neg-open-queue]');
            if (openQueueButton) {
                openQueueButton.addEventListener('click', close);
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    close();
                }
            });
        })();
    </script>
<?php else: ?>
    <script>
        (function () {
            const trigger = document.querySelector('[data-mobile-neg-trigger]');
            if (!trigger) return;
            trigger.addEventListener('click', function () {
                const queueRoute = trigger.getAttribute('data-mobile-neg-queue') || '';
                if (queueRoute !== '') {
                    window.location.href = queueRoute;
                }
            });
        })();
    </script>
<?php endif; ?>
</body>
</html>
