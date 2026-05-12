<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Central Tecnica') . ' | ' . config('app.name')); ?></title>
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
<body class="relative min-h-screen overflow-x-hidden bg-gradient-to-br from-sky-50 via-slate-100 to-blue-100 text-slate-800 portal-modern-theme support-modern-theme">
<?php
$supportAuth = $_SESSION['support_desk_auth'] ?? [];
$supportUser = trim((string) ($supportAuth['name'] ?? '')) !== '' ? (string) ($supportAuth['name'] ?? '') : (string) ($supportAuth['username'] ?? '');
$logoBuild = '20260512-brand-kit-v1';
?>
<div class="portal-modern-shell min-h-screen">
    <div class="portal-modern-ambient" aria-hidden="true"></div>
    <div class="portal-modern-content min-h-screen">
        <header class="support-modern-header sticky top-0 z-30 border-b border-white/60 bg-white/70 backdrop-blur-xl shadow-[0_8px_30px_rgba(15,23,42,0.08)]">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 lg:px-8">
                <div class="flex items-center gap-3">
                    <a href="support.php?route=support" class="flex items-center" title="Ir para Home">
                        <span class="aneo-theme-logo-frame aneo-logo-scope-support">
                            <img src="assets/brand/aneo-wordmark-simples-dark.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO central tecnica tema escuro" class="aneo-theme-logo aneo-logo-dark aneo-logo-desktop">
                            <img src="assets/brand/aneo-wordmark-simples-dark.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO central tecnica tema escuro" class="aneo-theme-logo aneo-logo-dark aneo-logo-mobile">
                            <img src="assets/brand/aneo-wordmark-simples-light.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO central tecnica tema claro" class="aneo-theme-logo aneo-logo-light aneo-logo-desktop">
                            <img src="assets/brand/aneo-wordmark-simples-light.svg?v=<?= e($logoBuild); ?>" alt="Logo ANEO central tecnica tema claro" class="aneo-theme-logo aneo-logo-light aneo-logo-mobile">
                        </span>
                    </a>
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-cyan-600">Central Tecnica</p>
                        <h1 class="text-sm font-semibold text-slate-800">Administracao de Chamados</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-800"><?= e($supportUser !== '' ? $supportUser : 'tecnico'); ?></p>
                        <p class="text-xs text-slate-500">Equipe tecnica</p>
                    </div>
                    <button type="button" class="theme-toggle portal-theme-toggle" data-portal-theme-toggle aria-label="Alternar tema claro e escuro" title="Alternar tema">
                        <svg data-theme-icon-dark xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <svg data-theme-icon-light class="hidden" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </button>
                    <a href="support.php?route=support/logout" class="rounded-lg border border-rose-200/80 bg-rose-50/85 px-3 py-2 text-xs font-semibold text-rose-700 backdrop-blur hover:bg-rose-100">
                        Sair
                    </a>
                </div>
            </div>
        </header>

        <main class="support-modern-main mx-auto max-w-7xl px-4 py-6 lg:px-8 lg:py-8">
            <?php if ($msg = flash('success')): ?>
                <div class="mb-4 rounded-xl border border-emerald-200/90 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-700 backdrop-blur"><?= e($msg); ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="mb-4 rounded-xl border border-rose-200/90 bg-rose-50/80 px-4 py-3 text-sm text-rose-700 backdrop-blur"><?= e($msg); ?></div>
            <?php endif; ?>
            <?= $content; ?>
        </main>
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
</script>
</body>
</html>
