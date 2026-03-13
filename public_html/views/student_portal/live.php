<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Proximas Aulas ao Vivo</h2>
        <p class="text-sm text-slate-500">Encontros sincronos vinculados aos seus cursos ativos.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">Curso</th>
                    <th class="px-3 py-3">Data e horario</th>
                    <th class="px-3 py-3">Meeting ID</th>
                    <th class="px-3 py-3">Senha</th>
                    <th class="px-3 py-3">Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3 font-medium"><?= e($row['name']); ?></td>
                        <td class="px-3 py-3"><?= e(date('d/m/Y H:i', strtotime((string) $row['live_datetime']))); ?></td>
                        <td class="px-3 py-3"><?= e($row['live_meeting_id'] ?: '-'); ?></td>
                        <td class="px-3 py-3"><?= e($row['live_password'] ?: '-'); ?></td>
                        <td class="px-3 py-3">
                            <a href="<?= e($row['live_link']); ?>" target="_blank" rel="noopener" class="inline-flex rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 font-medium text-cyan-700 hover:bg-cyan-100">Entrar na aula</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-slate-500">Nenhuma aula ao vivo agendada para os proximos dias.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
