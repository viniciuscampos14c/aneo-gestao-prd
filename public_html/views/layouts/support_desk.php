<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Central Tecnica') . ' | ' . config('app.name')); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/app.css">
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
</body>
</html>
