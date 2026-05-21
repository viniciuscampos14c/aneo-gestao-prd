<?php
$isProfessorView = is_professor();
$canCreate = !$isProfessorView && has_permission('students.create');
$canExport = has_permission('students.export');
$canImport = !$isProfessorView && has_permission('students.import');
$canBulk = !$isProfessorView && has_permission('students.bulk');
$canEdit = !$isProfessorView && has_permission('students.edit');
$canDelete = !$isProfessorView && has_permission('students.delete');
$canStudentWhatsapp = has_permission('students.whatsapp');
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Alunos</h2>
            <p class="text-sm text-slate-500"><?= $isProfessorView ? 'Consulta rapida da base de alunos com informacoes essenciais para acompanhamento.' : 'Cadastro, manutencao, importacao e exportacao de alunos/clientes.'; ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($canCreate): ?>
                <a href="<?= route('students/create'); ?>" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">+ Novo Aluno</a>
            <?php endif; ?>
            <?php if ($canExport): ?>
                <a href="<?= route('students/export&q=' . urlencode($filters['q'])); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Exportar CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total de alunos</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $stats['total']; ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Alunos ativos / inativos</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $stats['active']; ?> / <?= (int) $stats['inactive']; ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Contatos ativos / inativos</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $stats['contacts_active']; ?> / <?= (int) $stats['contacts_inactive']; ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Logados hoje</p>
            <p class="mt-2 text-2xl font-semibold">-</p>
        </article>
    </div>

    <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_auto]">
        <form method="get" action="index.php" class="grid gap-3 md:grid-cols-4">
            <input type="hidden" name="route" value="students">
            <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Procurar..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

            <select name="is_active" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos</option>
                <option value="1" <?= $filters['is_active'] === '1' ? 'selected' : ''; ?>>Ativos</option>
                <option value="0" <?= $filters['is_active'] === '0' ? 'selected' : ''; ?>>Inativos</option>
            </select>

            <?php if (!$isProfessorView): ?>
                <select name="kanban_status_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Todos os status</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= (int) $st['id']; ?>" <?= (string) $filters['kanban_status_id'] === (string) $st['id'] ? 'selected' : ''; ?>><?= e($st['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>

            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </form>

        <?php if ($canImport): ?>
            <form method="post" action="<?= route('students/import'); ?>" enctype="multipart/form-data" class="flex items-center gap-2">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="file" name="csv_file" accept=".csv" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Importar CSV</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($canBulk): ?>
        <form id="students-bulk-form" method="post" action="<?= route('students/bulk'); ?>" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <select name="bulk_action" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Acoes em massa</option>
                <option value="activate">Ativar</option>
                <option value="deactivate">Inativar</option>
                <option value="change_status">Alterar status Kanban</option>
                <option value="delete">Excluir</option>
            </select>

            <select name="bulk_status_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Status alvo</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= (int) $st['id']; ?>"><?= e($st['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar nos selecionados</button>
        </form>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-2 py-3"><?= $canBulk ? '<input type="checkbox" onclick="document.querySelectorAll(\'.student-check\').forEach(el => el.checked = this.checked)">' : ''; ?></th>
                    <th class="px-2 py-3">ID</th>
                    <th class="px-2 py-3">Nome completo</th>
                    <th class="px-2 py-3">Contato</th>
                    <th class="px-2 py-3">Email</th>
                    <th class="px-2 py-3">Telefone</th>
                    <th class="px-2 py-3">Status</th>
                    <?php if (!$isProfessorView): ?>
                        <th class="px-2 py-3">Informacoes Adm</th>
                        <th class="px-2 py-3">Criado</th>
                        <th class="px-2 py-3">RA</th>
                        <th class="px-2 py-3">Nascimento</th>
                        <th class="px-2 py-3">RG</th>
                        <th class="px-2 py-3">CRO</th>
                    <?php endif; ?>
                    <th class="px-2 py-3"><?= $isProfessorView ? 'Resumo' : 'Acoes'; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-2 py-3"><?= $canBulk ? '<input class="student-check" type="checkbox" value="' . (int) $student['id'] . '">' : ''; ?></td>
                        <td class="px-2 py-3"><?= (int) $student['id']; ?></td>
                        <td class="px-2 py-3 font-medium"><?= e($student['full_name']); ?></td>
                        <td class="px-2 py-3"><?= e($student['primary_contact']); ?></td>
                        <td class="px-2 py-3"><?= e($student['email_primary']); ?></td>
                        <td class="px-2 py-3"><?= e($student['phone']); ?></td>
                        <td class="px-2 py-3">
                            <?php if ($canEdit): ?>
                                <form method="post" action="<?= route('students/toggle'); ?>" class="flex items-center gap-2">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $student['id']; ?>">
                                    <input type="hidden" name="active" value="<?= $student['is_active'] ? 0 : 1; ?>">
                                    <button class="rounded-full px-2 py-1 text-xs font-semibold <?= $student['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                        <?= $student['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $student['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                    <?= $student['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php if (!$isProfessorView): ?>
                            <td class="px-2 py-3"><?= e($student['admin_info']); ?></td>
                            <td class="px-2 py-3"><?= e($student['created_at']); ?></td>
                            <td class="px-2 py-3"><?= e($student['ra']); ?></td>
                            <td class="px-2 py-3"><?= e($student['birth_date']); ?></td>
                            <td class="px-2 py-3"><?= e($student['rg']); ?></td>
                            <td class="px-2 py-3"><?= e($student['cro']); ?></td>
                        <?php endif; ?>
                        <td class="px-2 py-3">
                            <div class="flex gap-2">
                                <?php if ($isProfessorView): ?>
                                    <span class="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-600">Somente leitura</span>
                                <?php else: ?>
                                    <a href="<?= route('students/show&id=' . (int) $student['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Ver</a>
                                <?php endif; ?>
                                <?php if ($canStudentWhatsapp): ?>
                                    <?php $studentWhatsappLink = whatsapp_link((string) ($student['phone'] ?? ''), 'Ola ' . ($student['full_name'] ?? '') . ', tudo bem?'); ?>
                                    <?php if ($studentWhatsappLink): ?>
                                        <a target="_blank" rel="noopener" href="<?= e($studentWhatsappLink); ?>" class="rounded border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">WhatsApp</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($canEdit): ?>
                                    <a href="<?= route('students/edit&id=' . (int) $student['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Editar</a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= route('students/delete'); ?>" onsubmit="return confirm('Excluir aluno?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $student['id']; ?>">
                                        <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($students === []): ?>
                    <tr>
                        <td colspan="<?= $isProfessorView ? '8' : '14'; ?>" class="px-2 py-6 text-center text-slate-500">Nenhum aluno encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'students', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">
                    <?= $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</section>

<script>
document.getElementById('students-bulk-form')?.addEventListener('submit', function (event) {
    this.querySelectorAll('input[name="ids[]"]').forEach((node) => node.remove());
    const selected = Array.from(document.querySelectorAll('.student-check:checked')).map((el) => el.value);
    if (selected.length === 0) {
        event.preventDefault();
        alert('Selecione ao menos um aluno.');
        return;
    }
    selected.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        this.appendChild(input);
    });
});
</script>
