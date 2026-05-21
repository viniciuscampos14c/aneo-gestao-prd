<?php
$canCategory = has_permission('courses.category');
$canEnrollment = has_permission('courses.enrollment');
$canExam = has_permission('courses.exam');
$canComment = has_permission('courses.comment');
$canCreate = has_permission('courses.create');
$canEdit = has_permission('courses.edit');
$canDelete = has_permission('courses.delete');
$canTrial = has_permission('courses.enrollment');
$useCourseDashboard = (bool) ($useCourseDashboard ?? false);
$viewMode = (string) ($viewMode ?? ($useCourseDashboard ? 'cards' : 'list'));
$stats = $stats ?? [
    'total_courses' => (int) ($meta['total'] ?? 0),
    'published_courses' => 0,
    'enrollments_total' => 0,
    'comments_new_total' => 0,
];
$courseQueryBase = [
    'route' => 'courses',
    'q' => (string) ($filters['q'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
    'per_page' => (int) ($meta['per_page'] ?? config('app.default_pagination', 50)),
    'view' => $viewMode,
];
$buildCourseQuery = static function (array $overrides = []) use ($courseQueryBase): string {
    return build_query(array_merge($courseQueryBase, $overrides));
};
$formatRelativeTime = static function (?string $dateTime): string {
    $value = trim((string) $dateTime);
    if ($value === '') {
        return 'sem atualizacao recente';
    }

    try {
        $updatedAt = new DateTimeImmutable($value);
        $now = new DateTimeImmutable('now');
        $diff = max(0, $now->getTimestamp() - $updatedAt->getTimestamp());
    } catch (Throwable $e) {
        return 'sem atualizacao recente';
    }

    if ($diff < 60) {
        return 'agora mesmo';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' h';
    }
    if ($diff < 2592000) {
        return floor($diff / 86400) . ' dia(s)';
    }

    return floor($diff / 2592000) . ' mes(es)';
};
$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'published' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
        'archived' => 'bg-slate-200 text-slate-700 border border-slate-300',
        default => 'bg-amber-100 text-amber-700 border border-amber-200',
    };
};
?>

<?php if ($useCourseDashboard): ?>
    <style>
        .course-catalog-shell {
            position: relative;
        }
        .course-catalog-shell::before {
            content: "";
            position: absolute;
            inset: -24px -16px auto;
            height: 320px;
            pointer-events: none;
            background:
                radial-gradient(520px 240px at 100% 0%, rgba(34, 211, 238, 0.12), transparent 70%),
                radial-gradient(420px 220px at 0% 10%, rgba(15, 23, 42, 0.28), transparent 75%);
            z-index: 0;
        }
        .course-catalog-shell > * {
            position: relative;
            z-index: 1;
        }
        .course-catalog-panel {
            border: 1px solid rgba(30, 41, 59, 0.72);
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(15, 23, 42, 0.9));
            box-shadow: 0 22px 44px -30px rgba(2, 6, 23, 0.9);
        }
        .course-catalog-tab {
            border: 1px solid rgba(51, 65, 85, 0.9);
            background: rgba(15, 23, 42, 0.72);
            color: rgb(226 232 240);
            transition: .18s ease;
        }
        .course-catalog-tab:hover {
            border-color: rgba(34, 211, 238, 0.38);
            background: rgba(30, 41, 59, 0.92);
            color: white;
        }
        .course-catalog-tab-green {
            border-color: rgba(52, 211, 153, 0.42);
            background: rgba(6, 95, 70, 0.18);
            color: rgb(167 243 208);
        }
        .course-catalog-tab-amber {
            border-color: rgba(251, 191, 36, 0.42);
            background: rgba(120, 53, 15, 0.2);
            color: rgb(253 230 138);
        }
        .course-catalog-field,
        .course-catalog-select {
            border: 1px solid rgba(51, 65, 85, 0.95);
            background: rgba(2, 6, 23, 0.46);
            color: rgb(241 245 249);
            transition: .18s ease;
        }
        .course-catalog-field::placeholder {
            color: rgb(100 116 139);
        }
        .course-catalog-field:focus,
        .course-catalog-select:focus {
            border-color: rgba(34, 211, 238, 0.56);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.12);
            outline: none;
        }
        .course-catalog-toggle {
            border: 1px solid rgba(51, 65, 85, 0.95);
            background: rgba(2, 6, 23, 0.5);
            padding: 4px;
        }
        .course-catalog-toggle button {
            border: 0;
            background: transparent;
            color: rgb(148 163 184);
            transition: .18s ease;
        }
        .course-catalog-toggle button.active {
            background: rgba(30, 41, 59, 0.96);
            color: white;
            box-shadow: inset 0 0 0 1px rgba(51, 65, 85, 0.78);
        }
        .course-catalog-toggle button:focus-visible,
        .course-card-action:focus-visible,
        .course-card-menu-btn:focus-visible,
        .course-card-primary:focus-visible,
        .course-card-link:focus-visible {
            outline: 2px solid rgba(34, 211, 238, 0.72);
            outline-offset: 2px;
        }
        .course-stats-strip {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .course-stat-card {
            border: 1px solid rgba(30, 41, 59, 0.72);
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(15, 23, 42, 0.84));
            box-shadow: 0 18px 34px -30px rgba(2, 6, 23, 0.95);
        }
        .course-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }
        .course-card {
            border: 1px solid rgba(30, 41, 59, 0.8);
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.98), rgba(15, 23, 42, 0.92));
            box-shadow: 0 24px 44px -34px rgba(2, 6, 23, 0.95);
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }
        .course-card:hover {
            transform: translateY(-2px);
            border-color: rgba(34, 211, 238, 0.3);
            box-shadow: 0 28px 48px -34px rgba(2, 6, 23, 0.98);
        }
        .course-card-cover {
            position: relative;
            height: 96px;
            overflow: hidden;
            border-bottom: 1px solid rgba(30, 41, 59, 0.78);
            background: linear-gradient(135deg, rgba(8, 47, 73, 0.95), rgba(15, 23, 42, 1));
        }
        .course-card-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .course-card-cover-placeholder {
            position: absolute;
            inset: 0;
            background:
                repeating-linear-gradient(135deg,
                    rgba(15, 23, 42, 0.94) 0,
                    rgba(15, 23, 42, 0.94) 12px,
                    rgba(34, 211, 238, 0.08) 12px,
                    rgba(34, 211, 238, 0.08) 24px);
        }
        .course-card-title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 3.3rem;
        }
        .course-card-action {
            border: 1px solid rgba(51, 65, 85, 0.86);
            background: rgba(15, 23, 42, 0.6);
            color: rgb(226 232 240);
            transition: .18s ease;
        }
        .course-card-action:hover {
            border-color: rgba(34, 211, 238, 0.4);
            background: rgba(30, 41, 59, 0.92);
            color: white;
        }
        .course-card-primary {
            background: linear-gradient(135deg, rgb(8 145 178), rgb(14 116 144));
            color: rgb(248 250 252);
            box-shadow: 0 14px 24px -18px rgba(8, 145, 178, 0.95);
        }
        .course-card-primary:hover {
            filter: brightness(1.05);
        }
        .course-card-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .course-card-actions-grid > * {
            min-width: 0;
        }
        .course-card-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            z-index: 20;
            min-width: 220px;
            border: 1px solid rgba(51, 65, 85, 0.86);
            background: rgba(15, 23, 42, 0.98);
            box-shadow: 0 30px 40px -28px rgba(2, 6, 23, 0.98);
        }
        .course-card-menu[hidden] {
            display: none;
        }
        .course-card-menu form,
        .course-card-menu a,
        .course-card-menu button {
            display: block;
            width: 100%;
        }
        .course-card-menu a,
        .course-card-menu button {
            padding: 10px 12px;
            text-align: left;
            color: rgb(226 232 240);
            background: transparent;
            border: 0;
            transition: .18s ease;
        }
        .course-card-menu a:hover,
        .course-card-menu button:hover {
            background: rgba(30, 41, 59, 0.96);
        }
        .course-card-menu .danger {
            color: rgb(252 165 165);
        }
        .course-card-menu .danger:hover {
            background: rgba(127, 29, 29, 0.34);
            color: rgb(254 202 202);
        }
        .course-delete-dialog {
            border: 1px solid rgba(51, 65, 85, 0.9);
            background: rgba(15, 23, 42, 0.98);
            color: rgb(241 245 249);
            box-shadow: 0 42px 70px -32px rgba(2, 6, 23, 1);
        }
        .course-delete-dialog::backdrop {
            background: rgba(2, 6, 23, 0.72);
        }
        @media (max-width: 1100px) {
            .course-stats-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .course-stats-strip,
            .course-cards-grid {
                grid-template-columns: 1fr;
            }
        }
        html.admin-theme-light .course-catalog-shell::before {
            background:
                radial-gradient(520px 240px at 100% 0%, rgba(15, 94, 140, 0.09), transparent 70%),
                radial-gradient(420px 220px at 0% 10%, rgba(148, 163, 184, 0.12), transparent 75%);
        }
        html.admin-theme-light .course-catalog-panel,
        html.admin-theme-light .course-stat-card,
        html.admin-theme-light .course-card,
        html.admin-theme-light .course-delete-dialog {
            border-color: rgba(216, 226, 236, 0.95) !important;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(246, 248, 251, 0.98)) !important;
            box-shadow: 0 18px 30px -24px rgba(37, 57, 78, 0.18) !important;
        }
        html.admin-theme-light .course-catalog-tab {
            border-color: rgba(216, 226, 236, 0.95) !important;
            background: rgba(255, 255, 255, 0.92) !important;
            color: #36506a !important;
            box-shadow: 0 8px 18px rgba(37, 57, 78, 0.06) !important;
        }
        html.admin-theme-light .course-catalog-tab:hover {
            border-color: rgba(15, 94, 140, 0.24) !important;
            background: #ffffff !important;
            color: #0f5e8c !important;
        }
        html.admin-theme-light .course-catalog-tab-green {
            border-color: rgba(52, 211, 153, 0.34) !important;
            background: rgba(240, 253, 250, 0.98) !important;
            color: #0f766e !important;
        }
        html.admin-theme-light .course-catalog-tab-amber {
            border-color: rgba(245, 158, 11, 0.32) !important;
            background: rgba(255, 251, 235, 0.98) !important;
            color: #b45309 !important;
        }
        html.admin-theme-light .course-catalog-field,
        html.admin-theme-light .course-catalog-select,
        html.admin-theme-light .course-catalog-toggle {
            border-color: rgba(216, 226, 236, 0.95) !important;
            background: rgba(255, 255, 255, 0.96) !important;
            color: #21374d !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
        }
        html.admin-theme-light .course-catalog-field::placeholder {
            color: #7b8fa4 !important;
        }
        html.admin-theme-light .course-catalog-toggle button {
            color: #5f7796 !important;
        }
        html.admin-theme-light .course-catalog-toggle button.active {
            background: linear-gradient(135deg, rgba(15, 94, 140, 0.98), rgba(15, 111, 174, 0.96)) !important;
            color: #ffffff !important;
            box-shadow: 0 12px 22px rgba(15, 94, 140, 0.16) !important;
        }
        html.admin-theme-light .course-stat-card p.text-white,
        html.admin-theme-light .course-card h3.text-white,
        html.admin-theme-light .course-card .text-white {
            color: #17324d !important;
        }
        html.admin-theme-light .course-stat-card .text-slate-400,
        html.admin-theme-light .course-card .text-slate-400,
        html.admin-theme-light .course-card .text-slate-300 {
            color: #6d8298 !important;
        }
        html.admin-theme-light .course-card-cover {
            border-bottom-color: rgba(216, 226, 236, 0.95) !important;
            background: linear-gradient(135deg, rgba(232, 240, 248, 0.98), rgba(214, 229, 242, 0.98)) !important;
        }
        html.admin-theme-light .course-card-cover-placeholder {
            background:
                repeating-linear-gradient(135deg,
                    rgba(231, 238, 246, 0.98) 0,
                    rgba(231, 238, 246, 0.98) 12px,
                    rgba(15, 94, 140, 0.08) 12px,
                    rgba(15, 94, 140, 0.08) 24px) !important;
        }
        html.admin-theme-light .course-card .border-slate-700,
        html.admin-theme-light .course-card .border-slate-800,
        html.admin-theme-light .course-card .border-slate-900 {
            border-color: rgba(216, 226, 236, 0.95) !important;
        }
        html.admin-theme-light .course-card .bg-slate-900\/60,
        html.admin-theme-light .course-card .bg-slate-900\/70,
        html.admin-theme-light .course-card .bg-slate-950\/85 {
            background: rgba(255, 255, 255, 0.92) !important;
        }
        html.admin-theme-light .course-card-primary {
            background: linear-gradient(135deg, var(--aneo-light-accent), var(--aneo-light-accent-2)) !important;
            color: #ffffff !important;
            box-shadow: 0 12px 22px rgba(15, 94, 140, 0.2) !important;
        }
        html.admin-theme-light .course-card-action {
            border-color: rgba(216, 226, 236, 0.95) !important;
            background: rgba(255, 255, 255, 0.92) !important;
            color: #31506a !important;
            box-shadow: 0 8px 18px rgba(37, 57, 78, 0.06) !important;
        }
        html.admin-theme-light .course-card-action:hover {
            border-color: rgba(15, 94, 140, 0.24) !important;
            background: #ffffff !important;
            color: #0f5e8c !important;
        }
        html.admin-theme-light .course-card-menu {
            border-color: rgba(216, 226, 236, 0.95) !important;
            background: rgba(255, 255, 255, 0.98) !important;
            box-shadow: 0 24px 38px -26px rgba(37, 57, 78, 0.28) !important;
        }
        html.admin-theme-light .course-card-menu a,
        html.admin-theme-light .course-card-menu button {
            color: #31506a !important;
        }
        html.admin-theme-light .course-card-menu a:hover,
        html.admin-theme-light .course-card-menu button:hover {
            background: #eef3f8 !important;
            color: #0f5e8c !important;
        }
        html.admin-theme-light .course-card-menu .danger {
            color: #b91c1c !important;
        }
        html.admin-theme-light .course-card-menu .danger:hover {
            background: #fff1f2 !important;
            color: #9f1239 !important;
        }
    </style>
<?php endif; ?>

<section class="space-y-6 <?= $useCourseDashboard ? 'course-catalog-shell' : ''; ?>">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold <?= $useCourseDashboard ? 'text-white' : ''; ?>">Cursos EAD</h2>
            <p class="text-sm <?= $useCourseDashboard ? 'text-slate-300' : 'text-slate-500'; ?>">Catalogo de cursos, publicacao e acompanhamento.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($canCategory): ?>
                <a href="<?= route('courses/categories'); ?>" class="course-catalog-tab rounded-lg px-3 py-2 text-sm <?= $useCourseDashboard ? '' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">Categorias</a>
            <?php endif; ?>
            <?php if ($canEnrollment): ?>
                <a href="<?= route('courses/enrollments'); ?>" class="course-catalog-tab rounded-lg px-3 py-2 text-sm <?= $useCourseDashboard ? '' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">Matriculas</a>
            <?php endif; ?>
            <?php if ($canTrial): ?>
                <a href="<?= route('courses/trial-access'); ?>" class="rounded-lg px-3 py-2 text-sm font-semibold <?= $useCourseDashboard ? 'course-catalog-tab course-catalog-tab-green' : 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'; ?>">Degustacao</a>
            <?php endif; ?>
            <a href="<?= route('courses/calendar'); ?>" class="rounded-lg px-3 py-2 text-sm font-semibold <?= $useCourseDashboard ? 'course-catalog-tab course-catalog-tab-amber' : 'border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'; ?>">Agenda Academica</a>
            <?php if ($canExam): ?>
                <a href="<?= route('courses/exams'); ?>" class="course-catalog-tab rounded-lg px-3 py-2 text-sm <?= $useCourseDashboard ? '' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">Exames</a>
            <?php endif; ?>
            <?php if ($canComment): ?>
                <a href="<?= route('courses/comments'); ?>" class="course-catalog-tab rounded-lg px-3 py-2 text-sm <?= $useCourseDashboard ? '' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">Comentarios</a>
            <?php endif; ?>
            <?php if ($canCreate): ?>
                <a href="<?= route('courses/create'); ?>" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700">+ Criar novo curso</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl p-4 md:grid-cols-5 <?= $useCourseDashboard ? 'course-catalog-panel' : 'border border-slate-200 bg-white'; ?>">
        <input type="hidden" name="route" value="courses">
        <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar curso..." class="rounded-lg px-3 py-2 text-sm <?= $useCourseDashboard ? 'course-catalog-field' : 'border border-slate-200'; ?>">

        <select name="status" class="rounded-lg px-3 py-2 text-sm <?= $useCourseDashboard ? 'course-catalog-select' : 'border border-slate-200'; ?>">
            <option value="">Todas situacoes</option>
            <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Publicado</option>
            <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Rascunho</option>
            <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Arquivado</option>
        </select>

        <select name="per_page" class="rounded-lg px-3 py-2 text-sm <?= $useCourseDashboard ? 'course-catalog-select' : 'border border-slate-200'; ?>">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (int) ($meta['per_page'] ?? 0) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
            <?php endforeach; ?>
        </select>

        <?php if ($useCourseDashboard): ?>
            <div class="course-catalog-toggle inline-flex items-center rounded-xl">
                <button type="submit" name="view" value="cards" class="active rounded-lg px-3 py-2 text-sm <?= $viewMode === 'cards' ? 'active' : ''; ?>">Cards</button>
                <button type="submit" name="view" value="list" class="rounded-lg px-3 py-2 text-sm <?= $viewMode === 'list' ? 'active' : ''; ?>">Lista</button>
            </div>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        <?php else: ?>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 md:col-span-2">Filtrar</button>
        <?php endif; ?>
    </form>

    <?php if ($useCourseDashboard): ?>
        <section class="course-stats-strip">
            <article class="course-stat-card rounded-2xl p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-[0.18em] text-cyan-300">Cursos</span>
                    <span class="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-2 py-0.5 text-[11px] font-semibold text-cyan-100">Total</span>
                </div>
                <p class="text-3xl font-semibold text-white"><?= (int) ($stats['total_courses'] ?? 0); ?></p>
                <p class="mt-1 text-sm text-slate-400">No filtro atual</p>
            </article>
            <article class="course-stat-card rounded-2xl p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-[0.18em] text-emerald-300">Status</span>
                    <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-100">Publicados</span>
                </div>
                <p class="text-3xl font-semibold text-white"><?= (int) ($stats['published_courses'] ?? 0); ?></p>
                <p class="mt-1 text-sm text-slate-400">Disponiveis agora</p>
            </article>
            <article class="course-stat-card rounded-2xl p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-[0.18em] text-sky-300">Volume</span>
                    <span class="rounded-full border border-sky-400/20 bg-sky-400/10 px-2 py-0.5 text-[11px] font-semibold text-sky-100">Matriculas</span>
                </div>
                <p class="text-3xl font-semibold text-white"><?= (int) ($stats['enrollments_total'] ?? 0); ?></p>
                <p class="mt-1 text-sm text-slate-400">Ativas e concluidas</p>
            </article>
            <article class="course-stat-card rounded-2xl p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs uppercase tracking-[0.18em] text-violet-300">Interacao</span>
                    <span class="rounded-full border border-violet-400/20 bg-violet-400/10 px-2 py-0.5 text-[11px] font-semibold text-violet-100">Comentarios novos</span>
                </div>
                <p class="text-3xl font-semibold text-white"><?= (int) ($stats['comments_new_total'] ?? 0); ?></p>
                <p class="mt-1 text-sm text-slate-400">Ultimos 7 dias</p>
            </article>
        </section>
    <?php endif; ?>

    <?php if ($useCourseDashboard && $viewMode === 'cards'): ?>
        <?php require __DIR__ . '/partials/course_grid.php'; ?>

        <dialog id="course-delete-dialog" class="course-delete-dialog w-full max-w-md rounded-2xl p-0">
            <form method="dialog" class="border-b border-slate-700 px-5 py-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-white">Excluir curso</h3>
                    <button type="submit" class="rounded-lg border border-slate-600 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800">Fechar</button>
                </div>
            </form>
            <div class="space-y-4 px-5 py-4 text-sm text-slate-300">
                <p>Esta acao remove o curso selecionado. Confirme apenas se tiver certeza.</p>
                <form id="course-delete-confirm-form" method="post" action="<?= route('courses/delete'); ?>" class="flex justify-end gap-2">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="id" id="course-delete-id" value="">
                    <button type="button" data-course-delete-cancel class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Cancelar</button>
                    <button class="rounded-lg border border-rose-400/40 bg-rose-500/15 px-4 py-2 text-sm font-semibold text-rose-200 hover:bg-rose-500/25">Excluir curso</button>
                </form>
            </div>
        </dialog>

        <script>
            (() => {
                const menuButtons = Array.from(document.querySelectorAll('[data-course-menu-trigger]'));
                const deleteDialog = document.getElementById('course-delete-dialog');
                const deleteIdInput = document.getElementById('course-delete-id');

                const closeMenus = (exceptId = null) => {
                    document.querySelectorAll('[data-course-menu]').forEach((menu) => {
                        if (exceptId && menu.dataset.courseMenu === exceptId) {
                            return;
                        }
                        menu.hidden = true;
                    });
                    menuButtons.forEach((button) => {
                        if (!exceptId || button.dataset.courseMenuTrigger !== exceptId) {
                            button.setAttribute('aria-expanded', 'false');
                        }
                    });
                };

                menuButtons.forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        const menuId = button.dataset.courseMenuTrigger;
                        const menu = document.querySelector(`[data-course-menu="${menuId}"]`);
                        if (!menu) {
                            return;
                        }
                        const willOpen = menu.hidden;
                        closeMenus(willOpen ? menuId : null);
                        menu.hidden = !willOpen;
                        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                        if (willOpen) {
                            const focusable = menu.querySelector('a,button');
                            if (focusable) {
                                focusable.focus();
                            }
                        }
                    });
                });

                document.addEventListener('click', (event) => {
                    if (event.target.closest('[data-course-menu-wrap]')) {
                        return;
                    }
                    closeMenus();
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeMenus();
                        if (deleteDialog?.open) {
                            deleteDialog.close();
                        }
                    }
                });

                document.querySelectorAll('[data-course-delete-trigger]').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        const courseId = button.dataset.courseDeleteTrigger;
                        if (!deleteDialog || !deleteIdInput || !courseId) {
                            return;
                        }
                        closeMenus();
                        deleteIdInput.value = courseId;
                        if (typeof deleteDialog.showModal === 'function') {
                            deleteDialog.showModal();
                        }
                    });
                });

                document.querySelector('[data-course-delete-cancel]')?.addEventListener('click', () => {
                    deleteDialog?.close();
                });
            })();
        </script>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">ID</th>
                        <th class="px-3 py-3">Capa</th>
                        <th class="px-3 py-3">Nome do curso</th>
                        <th class="px-3 py-3">Categoria</th>
                        <th class="px-3 py-3">Situacao</th>
                        <th class="px-3 py-3">Opcoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                            <td class="px-3 py-3">
                                <?php $coverImage = trim((string) ($row['cover_image'] ?? '')); ?>
                                <?php if ($coverImage !== '' && media_path_available($coverImage)): ?>
                                    <img src="<?= e($coverImage); ?>" alt="Capa" class="h-10 w-16 rounded object-cover">
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Sem capa</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3 font-medium"><?= e((string) ($row['name'] ?? '')); ?></td>
                            <td class="px-3 py-3"><?= e((string) ($row['category_name'] ?? 'Sem categoria')); ?></td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $statusBadgeClass((string) ($row['status'] ?? 'draft')); ?>">
                                    <?= e(match ((string) ($row['status'] ?? 'draft')) { 'published' => 'Publicado', 'archived' => 'Arquivado', default => 'Rascunho' }); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <?php if ($canEdit): ?>
                                        <a href="<?= route('courses/edit&id=' . (int) $row['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Editar</a>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="<?= route('courses/delete'); ?>" onsubmit="return confirm('Excluir curso?');">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                            <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhum curso encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm <?= $useCourseDashboard ? 'text-slate-300' : ''; ?>">
        <p>Total: <?= (int) ($meta['total'] ?? 0); ?> registros | Pagina <?= (int) ($meta['page'] ?? 1); ?>/<?= (int) ($meta['pages'] ?? 1); ?></p>
        <div class="flex flex-wrap gap-2">
            <?php for ($p = 1; $p <= (int) ($meta['pages'] ?? 1); $p++): ?>
                <?php $pageQuery = $buildCourseQuery(['page' => $p]); ?>
                <a href="index.php?<?= $pageQuery; ?>" class="rounded px-3 py-1 <?= $p === (int) ($meta['page'] ?? 1) ? 'bg-slate-900 text-white' : ($useCourseDashboard ? 'border border-slate-700 bg-slate-900/70 text-slate-100 hover:border-cyan-400/50 hover:bg-slate-800' : 'border border-slate-200 bg-white hover:bg-slate-50'); ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
