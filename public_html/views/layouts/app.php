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
            } catch (error) {
                // Ignora bloqueio de localStorage e segue com tema padrao escuro.
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= e((string) (is_file(__DIR__ . '/../../assets/css/app.css') ? filemtime(__DIR__ . '/../../assets/css/app.css') : date('YmdHis'))); ?>">
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 admin-modern-theme">
<?php
$currentRoute = parse_route();
$user = current_user();
$company = current_company();
$menu = [
    ['module' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'chart-bar', 'route' => 'dashboard'],
    ['module' => 'gda', 'label' => 'Gestão do Aluno', 'icon' => 'user-group', 'route' => 'gestao-aluno'],
    ['module' => 'kanban', 'label' => 'Kanban Cliente', 'icon' => 'view-columns', 'route' => 'kanban'],
    ['module' => 'students', 'label' => 'Alunos', 'icon' => 'users', 'route' => 'students'],
    ['module' => 'leads', 'label' => 'Leads', 'icon' => 'sparkles', 'route' => 'leads'],
    ['module' => 'finance', 'label' => 'Financeiro', 'icon' => 'currency-dollar', 'route' => 'finance/invoices'],
    ['module' => 'chatwoot', 'label' => 'Atendimento', 'icon' => 'chat-bubble-left-right', 'route' => 'chatwoot'],
    ['module' => 'courses', 'label' => 'Cursos EAD', 'icon' => 'academic-cap', 'route' => 'courses'],
    ['module' => 'signatures', 'label' => 'Assinaturas', 'icon' => 'document-check', 'route' => 'signatures'],
    ['module' => 'arsenal', 'label' => 'Arsenal Digital', 'icon' => 'book-open', 'route' => 'arsenal'],
    ['module' => 'requests', 'label' => 'Solicitações', 'icon' => 'inbox-arrow-down', 'route' => 'requests'],
    ['module' => 'students', 'label' => 'Intercâmbio Aluno', 'icon' => 'arrow-path', 'route' => 'exchange'],
    ['module' => 'automations', 'label' => 'Automações', 'icon' => 'bolt', 'route' => 'automations'],
    ['module' => 'help', 'label' => 'Chat IA Jully', 'icon' => 'question-mark-circle', 'route' => 'help'],
];

$cadastroMenu = [
    ['module' => 'users', 'label' => 'Usuarios', 'route' => 'users'],
    ['module' => 'companies', 'label' => 'Empresas', 'route' => 'companies'],
    ['module' => 'companies', 'label' => 'SMTP Email', 'route' => 'companies/smtp'],
];

if (class_exists('BanksController')) {
    $cadastroMenu[] = ['module' => 'companies', 'label' => 'Bancos', 'route' => 'banks'];
}

if (($user['role'] ?? '') === 'admin') {
    $cadastroMenu[] = ['module' => 'dashboard', 'label' => 'Logs de Sistema', 'route' => 'system/logs'];
    $cadastroMenu[] = ['module' => 'dashboard', 'label' => 'Cron Jobs',       'route' => 'cron'];
}

$cadastroItemsVisible = [];
foreach ($cadastroMenu as $cadItem) {
    if (has_permission($cadItem['module'])) {
        $cadastroItemsVisible[] = $cadItem;
    }
}
$showCadastro = $cadastroItemsVisible !== [];
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
$mobileNegotiationAlerts = isset($mobileNegotiationAlerts) && is_array($mobileNegotiationAlerts) ? $mobileNegotiationAlerts : [];
$mobileNegotiationAlertCount = (int) ($mobileNegotiationAlertCount ?? count($mobileNegotiationAlerts));
$mobileNegotiationAlertIds = array_values(array_filter(array_map('intval', array_column($mobileNegotiationAlerts, 'id')), fn ($id) => $id > 0));
$mobileQueueRoute = route('requests&source=api&mobile_flow=1&status=pending');
?>
<div class="admin-modern-shell flex min-h-screen">
    <div class="admin-modern-ambient" aria-hidden="true"></div>
    <aside id="sidebar" class="admin-modern-sidebar fixed inset-y-0 left-0 z-40 w-72 transform bg-slate-900 text-slate-100 shadow-xl transition-transform lg:translate-x-0 -translate-x-full">
        <div class="flex h-16 items-center justify-between border-b border-slate-800 px-6">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-cyan-400">ANEO</p>
                <h1 class="text-lg font-semibold">Gestao Integrada</h1>
            </div>
            <button class="lg:hidden" data-sidebar-close>
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="h-[calc(100vh-10rem)] overflow-y-auto p-4">
            <ul class="space-y-1">
                <?php foreach ($menu as $item): ?>
                    <?php if (!has_permission($item['module'])) { continue; } ?>
                    <?php $active = str_starts_with($currentRoute, $item['route']) ? 'bg-slate-800 text-cyan-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>
                    <li>
                        <a href="<?= route($item['route']); ?>" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition <?= $active; ?>">
                            <span class="h-2 w-2 rounded-full bg-cyan-400/80"></span>
                            <?= e($item['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if ($showCadastro): ?>
                    <?php $cadastroActive = str_starts_with($currentRoute, 'users') || str_starts_with($currentRoute, 'companies') || str_starts_with($currentRoute, 'system/logs') || str_starts_with($currentRoute, 'cron'); ?>
                    <li class="pt-1">
                        <button type="button" data-cadastro-trigger aria-haspopup="true" aria-expanded="false" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition <?= $cadastroActive ? 'bg-slate-800 text-cyan-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                            <span class="flex items-center gap-3">
                                <span class="h-2 w-2 rounded-full bg-cyan-400/80"></span>
                                Cadastro
                            </span>
                            <svg data-cadastro-chevron class="h-4 w-4 text-slate-400 transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 6l6 6-6 6"/>
                            </svg>
                        </button>
                    </li>
                <?php endif; ?>

                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <?php $apiActive = str_starts_with($currentRoute, 'api-management'); ?>
                    <li class="pt-1">
                        <button type="button" data-api-trigger aria-haspopup="true" aria-expanded="false" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition <?= $apiActive ? 'bg-slate-800 text-cyan-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                            <span class="flex items-center gap-3">
                                <span class="h-2 w-2 rounded-full bg-cyan-400/80"></span>
                                API
                            </span>
                            <svg data-api-chevron class="h-4 w-4 text-slate-400 transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 6l6 6-6 6"/>
                            </svg>
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if ($showCadastro): ?>
            <div data-cadastro-panel class="hidden fixed z-[80] min-w-[220px] rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-2xl">
                <?php foreach ($cadastroItemsVisible as $cadItem): ?>
                    <?php $cadActive = str_starts_with($currentRoute, $cadItem['route']) ? 'bg-slate-800 text-cyan-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white'; ?>
                    <a href="<?= route($cadItem['route']); ?>" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition <?= $cadActive; ?>">
                        <span class="h-2 w-2 rounded-full bg-cyan-400/80"></span>
                        <?= e($cadItem['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <div data-api-panel class="hidden fixed z-[80] min-w-[220px] rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-2xl">
                <?php
                    $apiMenuItems = [
                        ['route' => 'api-management',        'label' => 'Gerenciamento de API'],
                        ['route' => 'api-management/manual', 'label' => 'Manual da API'],
                    ];
                    foreach ($apiMenuItems as $apiItem):
                        $aActive = str_starts_with($currentRoute, $apiItem['route']) ? 'bg-slate-800 text-cyan-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white';
                ?>
                    <a href="<?= route($apiItem['route']); ?>" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition <?= $aActive; ?>">
                        <span class="h-2 w-2 rounded-full bg-cyan-400/80"></span>
                        <?= e($apiItem['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="admin-sidebar-footer border-t border-slate-800 px-6 py-4">
            <?php if ($company): ?>
                <?php $companyName = trim((string) ($company['trade_name'] ?? '')) !== '' ? (string) $company['trade_name'] : (string) ($company['legal_name'] ?? 'Empresa'); ?>
                <section class="admin-sidebar-company-card">
                    <p class="admin-sidebar-kicker text-xs uppercase tracking-[0.16em] text-cyan-400">Empresa</p>
                    <p class="admin-sidebar-company-name text-sm font-semibold"><?= e($companyName); ?></p>
                    <p class="admin-sidebar-company-doc text-[11px] text-slate-400"><?= e((string) ($company['cnpj'] ?? '')); ?></p>
                    <a href="<?= route('select-company'); ?>" class="admin-sidebar-company-switch mt-1 inline-flex text-[11px] text-cyan-300 hover:text-cyan-200">Trocar empresa</a>
                </section>
            <?php endif; ?>
            <section class="admin-sidebar-user-card">
                <p class="admin-sidebar-user-name text-sm font-semibold"><?= e($user['name'] ?? ''); ?></p>
                <p class="admin-sidebar-user-role text-xs text-slate-400"><?= e(role_label($user['role'] ?? '')); ?></p>
                <a href="<?= route('logout'); ?>" class="admin-sidebar-logout mt-2 inline-flex text-xs text-rose-300 hover:text-rose-200">Sair</a>
            </section>
        </div>
    </aside>

    <div class="flex w-full flex-col">
        <header class="admin-modern-header sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex h-16 items-center gap-3 px-4 lg:px-8">
                <button class="rounded-lg border border-slate-200 p-2 lg:hidden" data-sidebar-open>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="<?= route('dashboard'); ?>" class="hidden items-center rounded-lg border border-slate-800/20 bg-slate-900 px-2 py-1 shadow-sm md:flex">
                    <img src="assets/img/logo_aneo.png" alt="Logo ANEO" class="h-10 w-auto rounded">
                </a>
                <form class="flex-1" action="<?= route('search'); ?>" method="get">
                    <input type="hidden" name="route" value="search">
                    <input type="text" name="q" value="<?= e($_GET['q'] ?? ''); ?>" placeholder="Busca global..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm outline-none focus:border-cyan-500 focus:bg-white">
                </form>
                <a href="<?= route('logout'); ?>" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100" title="Sair do sistema">
                    Sair
                </a>
                <button class="relative rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50" title="Notificacoes de negociacao mobile">
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
    <div id="mobile-negotiation-modal" data-ticket-ids="<?= e(json_encode($mobileNegotiationAlertIds)); ?>" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/55 p-4">
        <div class="w-full max-w-2xl rounded-xl border border-indigo-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="text-lg font-semibold text-indigo-700">Novas negociacoes do app</h3>
                    <p class="text-xs text-slate-500">A equipe da diretoria enviou solicitacoes financeiras para tratamento.</p>
                </div>
                <button type="button" data-mobile-neg-close class="rounded-lg border border-slate-200 px-3 py-1 text-xs hover:bg-slate-50">Fechar</button>
            </div>
            <div class="max-h-[60vh] space-y-2 overflow-y-auto p-4">
                <?php foreach ($mobileNegotiationAlerts as $alert): ?>
                    <?php
                    $ticketId = (int) ($alert['id'] ?? 0);
                    $ticketCode = trim((string) ($alert['ticket_code'] ?? ''));
                    if (!preg_match('/^ANEO\d+$/', $ticketCode) && $ticketId > 0) {
                        $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
                    }
                    ?>
                    <article class="rounded-lg border border-indigo-100 bg-indigo-50/50 px-3 py-2 text-sm">
                        <p class="font-semibold text-slate-800"><?= e((string) ($alert['subject'] ?? 'Negociacao financeira')); ?></p>
                        <p class="mt-1 text-xs text-slate-600">
                            Codigo: <?= e($ticketCode !== '' ? $ticketCode : ('#' . $ticketId)); ?>
                            | Status: <?= e((string) ($alert['status'] ?? 'open')); ?>
                            | Recebido em: <?= e((string) ($alert['created_at'] ?? '')); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
                <button type="button" data-mobile-neg-close class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Dispensar</button>
                <a href="<?= e($mobileQueueRoute); ?>" data-mobile-neg-open-queue class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Abrir fila de negociacoes</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="assets/js/app.js?v=<?= e($appJsVersion); ?>"></script>
<?php if ($mobileNegotiationAlerts !== []): ?>
    <script>
        (function () {
            const modal = document.getElementById('mobile-negotiation-modal');
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
            if (unseen.length === 0) return;

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

            modal.classList.remove('hidden');
            modal.classList.add('flex');

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
<?php endif; ?>
</body>
</html>
