<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Portal do Aluno') . ' | ' . config('app.name')); ?></title>
    <script>
        (function(){var t=localStorage.getItem('theme');if(t==='dark'||(t===null&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');}})();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/app.css?v=4">
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-50 via-white to-emerald-50 text-slate-800">
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
        ['label' => 'Progresso', 'route' => 'student/progress'],
        ['label' => 'Avaliacoes', 'route' => 'student/exams'],
        ['label' => 'Historico Academico', 'route' => 'student/academic-history'],
    ];
?>
<div class="min-h-screen">
    <header class="sticky top-0 z-30 border-b border-sky-100 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-8">
            <div class="flex items-center gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-sky-600">ANEO</p>
                    <h1 class="text-lg font-semibold text-slate-900">Portal do Aluno</h1>
                </div>
                <div class="hidden rounded-lg border border-sky-100 bg-white p-1.5 shadow-sm sm:block">
                    <img src="assets/img/logo_aneo.png" alt="Logo ANEO" class="h-10 w-auto rounded">
                </div>
            </div>
            <div class="flex items-center gap-3 text-right text-sm">
                <button class="theme-toggle" id="theme-toggle" aria-label="Alternar modo escuro" title="Alternar modo escuro/claro">
                    <svg id="icon-moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg id="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <div>
                    <p class="font-medium text-slate-900"><?= e($student['name'] ?? 'Aluno'); ?></p>
                    <p class="text-slate-500"><?= e($student['login'] ?? ''); ?></p>
                </div>
                <?php if ($studentPhoto !== '' && media_path_available($studentPhoto)): ?>
                    <img src="<?= e($studentPhoto); ?>" alt="Foto do aluno" class="h-10 w-10 rounded-full object-cover ring-2 ring-sky-100">
                <?php else: ?>
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700"><?= e($studentInitials); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <nav class="mx-auto flex max-w-7xl flex-wrap gap-2 px-4 pb-4 lg:px-8">
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

    <main class="mx-auto max-w-7xl px-4 py-6 lg:px-8 lg:py-8">
        <?php if ($msg = flash('success')): ?>
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($msg); ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($msg); ?></div>
        <?php endif; ?>
        <?= $content; ?>
    </main>
</div>
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

    // Estado inicial dos ícones
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
