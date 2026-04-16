<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Central Tecnica') . ' | ' . config('app.name')); ?></title>
    <script>
        (function(){var t=localStorage.getItem('theme');if(t==='dark'||(t===null&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');}})();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/app.css?v=4">
</head>
<body class="relative min-h-screen overflow-x-hidden bg-gradient-to-br from-sky-50 via-slate-100 to-blue-100 text-slate-800">
<div class="pointer-events-none fixed inset-0 -z-10">
    <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-cyan-300/35 blur-3xl"></div>
    <div class="absolute right-[-120px] top-12 h-96 w-96 rounded-full bg-blue-300/25 blur-3xl"></div>
    <div class="absolute bottom-[-140px] left-1/3 h-96 w-96 rounded-full bg-sky-200/25 blur-3xl"></div>
</div>
<?php
$supportAuth = $_SESSION['support_desk_auth'] ?? [];
$supportUser = trim((string) ($supportAuth['name'] ?? '')) !== '' ? (string) $supportAuth['name'] : (string) ($supportAuth['username'] ?? '');
?>
<header class="sticky top-0 z-30 border-b border-white/60 bg-white/70 backdrop-blur-xl shadow-[0_8px_30px_rgba(15,23,42,0.08)]">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 lg:px-8">
        <div class="flex items-center gap-3">
            <a href="support.php?route=support" class="flex items-center rounded-lg border border-slate-800/20 bg-slate-900 px-2 py-1 shadow-sm">
                <img src="assets/img/logo_aneo.png" alt="Logo ANEO" class="h-9 w-auto rounded">
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
            <button class="theme-toggle" id="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro/claro">
                <svg id="icon-moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg id="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            </button>
            <a href="support.php?route=support/logout" class="rounded-lg border border-rose-200/80 bg-rose-50/85 px-3 py-2 text-xs font-semibold text-rose-700 backdrop-blur hover:bg-rose-100">
                Sair
            </a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-6 lg:px-8 lg:py-8">
    <?php if ($msg = flash('success')): ?>
        <div class="mb-4 rounded-xl border border-emerald-200/90 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-700 backdrop-blur"><?= e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 rounded-xl border border-rose-200/90 bg-rose-50/80 px-4 py-3 text-sm text-rose-700 backdrop-blur"><?= e($msg); ?></div>
    <?php endif; ?>
    <?= $content; ?>
</main>
<script>
(function () {
    var btn  = document.getElementById('theme-toggle');
    var moon = document.getElementById('icon-moon');
    var sun  = document.getElementById('icon-sun');
    if (!btn) return;

    function applyTheme(dark) {
        if (dark) {
            document.documentElement.classList.add('dark');
            if (moon) moon.style.display = 'none';
            if (sun)  sun.style.display  = 'block';
        } else {
            document.documentElement.classList.remove('dark');
            if (moon) moon.style.display = 'block';
            if (sun)  sun.style.display  = 'none';
        }
    }

    applyTheme(document.documentElement.classList.contains('dark'));

    btn.addEventListener('click', function () {
        var isDark = document.documentElement.classList.contains('dark');
        applyTheme(!isDark);
        try { localStorage.setItem('theme', isDark ? 'light' : 'dark'); } catch(e) {}
    });
})();
</script>
</body>
</html>
