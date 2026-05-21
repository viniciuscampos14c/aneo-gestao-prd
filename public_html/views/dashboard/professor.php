<?php
$studentStats = $studentStats ?? [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'contacts_active' => 0,
    'contacts_inactive' => 0,
];

$cards = [
    [
        'label' => 'Total de alunos',
        'value' => (int) ($studentStats['total'] ?? 0),
        'meta' => 'Abrir lista geral',
        'href' => route('students'),
    ],
    [
        'label' => 'Alunos ativos',
        'value' => (int) ($studentStats['active'] ?? 0),
        'meta' => 'Filtrar alunos ativos',
        'href' => route('students&is_active=1'),
    ],
    [
        'label' => 'Alunos inativos',
        'value' => (int) ($studentStats['inactive'] ?? 0),
        'meta' => 'Filtrar alunos inativos',
        'href' => route('students&is_active=0'),
    ],
    [
        'label' => 'Escala aluno',
        'value' => 'Abrir',
        'meta' => 'Visualizar grade e unidades',
        'href' => route('escala-aluno'),
    ],
];
?>
<section class="space-y-6">
    <div>
        <p class="text-xs uppercase tracking-[0.24em] text-cyan-300">Area do professor</p>
        <h2 class="mt-2 text-4xl font-semibold text-slate-900">Inicio</h2>
        <p class="mt-2 text-sm text-slate-500">Acompanhe os alunos e acesse rapidamente as telas de consulta do perfil professor.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($cards as $card): ?>
            <a href="<?= e((string) $card['href']); ?>" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-md">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500"><?= e((string) $card['label']); ?></p>
                <p class="mt-3 text-4xl font-semibold text-slate-900"><?= e((string) $card['value']); ?></p>
                <p class="mt-2 text-sm text-cyan-700"><?= e((string) $card['meta']); ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Visao rapida</h3>
        <p class="mt-2 text-sm text-slate-600">Neste perfil, o sistema foi simplificado para acompanhamento pedagogico: alunos em modo leitura, Escala Aluno apenas para consulta e atalhos diretos para cursos, exames e comentarios.</p>
    </div>
</section>
