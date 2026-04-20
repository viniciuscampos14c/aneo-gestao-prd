<?php
$trialAccess = current_student_trial_access();
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Aulas ao Vivo</h2>
        <p class="text-sm text-slate-500">Encontros síncronos vinculados aos seus cursos.</p>
    </div>

    <?php if ($trialAccess !== null): ?>
        <div class="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
            Seu acesso de degustação permite visualizar apenas a aula ao vivo do curso liberado para hoje.
        </div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <div class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-400">
            Nenhuma aula ao vivo agendada para os próximos dias.
        </div>
    <?php else: ?>

    <!-- Cards de aulas -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($rows as $row): ?>
            <?php
            $datetime   = (string) ($row['live_datetime'] ?? '');
            $dateFmt    = $datetime !== '' ? date('d/m/Y', strtotime($datetime)) : '—';
            $timeFmt    = $datetime !== '' ? date('H:i', strtotime($datetime)) : '—';
            $duration   = (int) ($row['duration_minutes'] ?? 0);
            $meetingId  = (string) ($row['live_meeting_id'] ?? '');
            $password   = (string) ($row['live_password'] ?? '');
            $joinUrl    = (string) ($row['live_link'] ?? '');
            $courseName = (string) ($row['name'] ?? '');
            ?>
            <article class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col">
                <!-- Cabeçalho do card -->
                <div class="bg-gradient-to-r from-sky-600 to-cyan-500 px-4 py-3 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.806v6.388a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm font-semibold text-white truncate" title="<?= e($courseName); ?>"><?= e($courseName); ?></span>
                </div>

                <!-- Corpo -->
                <div class="px-4 py-4 flex-1 space-y-3">
                    <!-- Data e hora -->
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-sky-50 border border-sky-100 px-3 py-1.5 text-center min-w-[52px]">
                            <p class="text-xs font-semibold text-sky-600 uppercase"><?= e(date('M', strtotime($datetime))); ?></p>
                            <p class="text-xl font-bold text-sky-800 leading-none"><?= e(date('d', strtotime($datetime))); ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800"><?= e($timeFmt); ?> (Horário de Brasília)</p>
                            <?php if ($duration > 0): ?>
                                <p class="text-xs text-slate-400">Duração: <?= $duration; ?> minutos</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Meeting ID e senha -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <?php if ($meetingId !== ''): ?>
                            <div class="rounded-lg bg-slate-50 border border-slate-100 px-2 py-1.5">
                                <p class="text-slate-400 font-medium uppercase tracking-wide mb-0.5">Meeting ID</p>
                                <p class="font-mono font-bold text-slate-700"><?= e($meetingId); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($password !== ''): ?>
                            <div class="rounded-lg bg-slate-50 border border-slate-100 px-2 py-1.5">
                                <p class="text-slate-400 font-medium uppercase tracking-wide mb-0.5">Senha</p>
                                <p class="font-mono font-bold text-slate-700"><?= e($password); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Botão entrar -->
                <div class="px-4 pb-4">
                    <?php if ($joinUrl !== ''): ?>
                        <a href="<?= e($joinUrl); ?>" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 w-full rounded-lg bg-sky-600 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.806v6.388a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Entrar na aula
                        </a>
                    <?php else: ?>
                        <span class="flex items-center justify-center gap-2 w-full rounded-lg bg-slate-200 py-2.5 text-sm font-semibold text-slate-400 cursor-not-allowed">
                            Link indisponível
                        </span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</section>
