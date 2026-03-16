<?php
$today = date('Y-m-d');
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Acesso de Degustacao</h2>
            <p class="text-sm text-slate-500">Crie um login simples para teste em aula ao vivo, com curso e dia especificos.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar para Cursos</a>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Funcionalidade nao habilitada no banco. Execute a migration
            <code>migrations/20260316_courses_trial_access.sql</code>.
        </div>
    <?php else: ?>
        <form method="post" action="<?= route('courses/trial-access/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-3">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <input type="text" name="student_name" required placeholder="Nome do aluno para degustacao" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="email" name="student_email" placeholder="E-mail (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="text" name="student_phone" placeholder="Telefone (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

            <select name="course_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Curso publicado...</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id']; ?>"><?= e($course['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="access_date" value="<?= e($today); ?>" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Criar acesso rapido</button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">ID</th>
                        <th class="px-3 py-3">Aluno</th>
                        <th class="px-3 py-3">Curso</th>
                        <th class="px-3 py-3">Data liberada</th>
                        <th class="px-3 py-3">Login portal</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Ultimo login</th>
                        <th class="px-3 py-3">Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $status = (string) ($row['status'] ?? 'active');
                        $accessDate = trim((string) ($row['access_date'] ?? ''));
                        if ($status === 'active' && $accessDate !== '' && $accessDate < $today) {
                            $status = 'expired';
                        }
                        $statusClass = 'bg-slate-100 text-slate-700';
                        $statusLabel = 'Inativo';
                        if ($status === 'active' && $accessDate === $today) {
                            $statusClass = 'bg-emerald-100 text-emerald-700';
                            $statusLabel = 'Ativo hoje';
                        } elseif ($status === 'active') {
                            $statusClass = 'bg-sky-100 text-sky-700';
                            $statusLabel = 'Agendado';
                        } elseif ($status === 'expired') {
                            $statusClass = 'bg-amber-100 text-amber-700';
                            $statusLabel = 'Expirado';
                        } elseif ($status === 'revoked') {
                            $statusClass = 'bg-rose-100 text-rose-700';
                            $statusLabel = 'Revogado';
                        }
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                            <td class="px-3 py-3">
                                <p class="font-medium"><?= e($row['student_name']); ?></p>
                                <p class="text-xs text-slate-500"><?= e($row['student_email'] ?: '-'); ?></p>
                            </td>
                            <td class="px-3 py-3"><?= e($row['course_name']); ?></td>
                            <td class="px-3 py-3"><?= $accessDate !== '' ? e(date('d/m/Y', strtotime($accessDate))) : '-'; ?></td>
                            <td class="px-3 py-3 font-mono text-xs"><?= e($row['portal_login'] ?: '-'); ?></td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $statusClass; ?>"><?= e($statusLabel); ?></span>
                            </td>
                            <td class="px-3 py-3"><?= e($row['last_login_at'] ?: '-'); ?></td>
                            <td class="px-3 py-3">
                                <?php if ((string) ($row['status'] ?? '') === 'active'): ?>
                                    <form method="post" action="<?= route('courses/trial-access/revoke'); ?>" onsubmit="return confirm('Revogar este acesso de degustacao?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Revogar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">Nenhum acesso de degustacao cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
            <div class="flex gap-2">
                <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                    <a href="index.php?<?= build_query(['route' => 'courses/trial-access', 'per_page' => (int) $meta['per_page'], 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
