<?php
$profileData = is_array($profile ?? null) ? $profile : [];
$studentName = trim((string) ($profileData['full_name'] ?? ($student['name'] ?? 'Aluno')));
$studentRa = trim((string) ($ra ?? ''));
$studentBirthDate = trim((string) ($profileData['birth_date'] ?? ''));
$studentRg = trim((string) ($profileData['rg'] ?? ''));
$studentCro = trim((string) ($profileData['cro'] ?? ''));
$studentEmail = trim((string) ($profileData['email_primary'] ?? ($student['email'] ?? '')));
$studentPhone = trim((string) ($profileData['phone'] ?? ($student['phone'] ?? '')));
$companyName = trim((string) ($profileData['company_trade_name'] ?? ''));
if ($companyName === '') {
    $companyName = trim((string) ($profileData['company_legal_name'] ?? 'ANEO'));
}
$companyCnpj = trim((string) ($profileData['company_cnpj'] ?? ''));
$issuedAtValue = trim((string) ($issuedAt ?? now()));
$issuedAtLabel = date('d/m/Y H:i', strtotime($issuedAtValue));
$birthDateLabel = $studentBirthDate !== '' ? date('d/m/Y', strtotime($studentBirthDate)) : '-';
$backRoute = (string) ($backRoute ?? route('student/exams'));
$backLabel = (string) ($backLabel ?? 'Voltar para Avaliacoes');
$issuedByLabel = trim((string) ($issuedByLabel ?? 'Documento emitido automaticamente pelo Portal do Aluno em '));
?>
<style>
.transcript-paper {
    border: 2px solid #111827;
    background: #ffffff !important;
    color: #111827 !important;
}

.transcript-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.transcript-table th,
.transcript-table td {
    border: 1px solid #111827;
    padding: 5px 6px;
    vertical-align: top;
    color: #111827 !important;
}

.transcript-table th {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
    background: #f3f4f6;
}

.transcript-subtitle {
    border: 1px solid #111827;
    border-bottom: 0;
    background: #f3f4f6;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 6px;
    text-transform: uppercase;
    color: #111827 !important;
}

#academic-history-paper,
#academic-history-paper * {
    color: #111827 !important;
    border-color: #111827 !important;
}

#academic-history-paper,
#academic-history-paper header,
#academic-history-paper section,
#academic-history-paper footer {
    background: #ffffff !important;
}

#academic-history-paper .transcript-table td {
    background: #ffffff !important;
}

#academic-history-paper .transcript-table th,
#academic-history-paper .transcript-subtitle {
    background: #f3f4f6 !important;
}

#academic-history-paper footer,
#academic-history-paper footer p,
#academic-history-paper footer div {
    color: #111827 !important;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

@media print {
    body * {
        visibility: hidden !important;
    }

    #academic-history-paper,
    #academic-history-paper * {
        visibility: visible !important;
    }

    #academic-history-paper {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
    }

    .no-print {
        display: none !important;
    }
}
</style>

<section class="space-y-4">
    <div class="no-print flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <div>
            <h2 class="text-2xl font-semibold">Historico Academico</h2>
            <p class="text-sm text-slate-500">Resumo academico consolidado por curso, pronto para impressao em A4.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e($backRoute); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><?= e($backLabel); ?></a>
            <button type="button" onclick="window.print()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Imprimir A4</button>
        </div>
    </div>

    <article id="academic-history-paper" class="transcript-paper rounded-none bg-white p-5 text-slate-900">
        <header class="border-b-2 border-slate-900 pb-3 text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.12em]">ANEO - Gestao Integrada</p>
            <p class="text-sm font-semibold uppercase"><?= e($companyName !== '' ? $companyName : 'Instituicao de Ensino'); ?></p>
            <h1 class="text-3xl font-bold uppercase tracking-wide">Historico Escolar</h1>
            <p class="mt-1 text-[11px]"><?= e($issuedByLabel); ?><?= e($issuedAtLabel); ?>.</p>
        </header>

        <table class="transcript-table mt-3">
            <tbody>
                <tr>
                    <th style="width: 16%">Nome</th>
                    <td style="width: 44%"><?= e($studentName); ?></td>
                    <th style="width: 14%">RA</th>
                    <td style="width: 26%"><?= e($studentRa); ?></td>
                </tr>
                <tr>
                    <th>Nascimento</th>
                    <td><?= e($birthDateLabel); ?></td>
                    <th>RG</th>
                    <td><?= e($studentRg !== '' ? $studentRg : '-'); ?></td>
                </tr>
                <tr>
                    <th>E-mail</th>
                    <td><?= e($studentEmail !== '' ? $studentEmail : '-'); ?></td>
                    <th>Telefone</th>
                    <td><?= e($studentPhone !== '' ? $studentPhone : '-'); ?></td>
                </tr>
                <tr>
                    <th>Instituicao</th>
                    <td><?= e($companyName !== '' ? $companyName : '-'); ?></td>
                    <th>CNPJ</th>
                    <td><?= e($companyCnpj !== '' ? $companyCnpj : '-'); ?></td>
                </tr>
                <tr>
                    <th>Registro Profissional</th>
                    <td colspan="3"><?= e($studentCro !== '' ? $studentCro : '-'); ?></td>
                </tr>
            </tbody>
        </table>

        <?php foreach ($terms as $term): ?>
            <section class="mt-4">
                <div class="transcript-subtitle"><?= e((string) ($term['term_label'] ?? 'Periodo')); ?></div>
                <table class="transcript-table">
                    <thead>
                        <tr>
                            <th style="width: 6%">N</th>
                            <th style="width: 40%">Disciplinas</th>
                            <th style="width: 12%">C/H</th>
                            <th style="width: 14%">Media Final</th>
                            <th style="width: 10%">Faltas</th>
                            <th style="width: 18%">Situacao Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($term['rows'] ?? []) as $idx => $row): ?>
                            <?php
                            $approved = (string) ($row['status'] ?? '') === 'approved';
                            $discipline = trim((string) ($row['course_name'] ?? ''));
                            $examTitle = trim((string) ($row['exam_title'] ?? ''));
                            if ($examTitle !== '') {
                                $discipline .= ($discipline !== '' ? ' - ' : '') . $examTitle;
                            }
                            $workload = (float) ($row['workload'] ?? 0);
                            ?>
                            <tr>
                                <td><?= (int) $idx + 1; ?></td>
                                <td><?= e($discipline !== '' ? $discipline : '-'); ?></td>
                                <td><?= $workload > 0 ? e(number_format($workload, 0, ',', '.')) : '-'; ?></td>
                                <td><?= e(number_format((float) ($row['score'] ?? 0), 2, ',', '.')); ?></td>
                                <td><?= (int) ($row['absences'] ?? 0); ?></td>
                                <td><?= $approved ? 'Aprovado' : 'Reprovado'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($term['rows'] ?? []) === []): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">Sem disciplinas registradas no periodo.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        <?php endforeach; ?>

        <?php if ($terms === []): ?>
            <section class="mt-4">
                <div class="transcript-subtitle">Sem resultados registrados</div>
                <table class="transcript-table">
                    <tbody>
                        <tr>
                            <td style="text-align:center;">Nenhuma materia com pontuacao foi registrada para este aluno ate o momento.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <section class="mt-4">
            <table class="transcript-table">
                <tbody>
                    <tr>
                        <th style="width: 30%">Carga Horaria Total dos Cursos</th>
                        <td style="width: 20%"><?= e(number_format((float) $totalWorkload, 0, ',', '.')); ?></td>
                        <th style="width: 30%">Media Geral</th>
                        <td style="width: 20%"><?= e(number_format((float) $averageScore, 2, ',', '.')); ?></td>
                    </tr>
                    <tr>
                        <th>Total de Avaliacoes</th>
                        <td><?= (int) $totalResults; ?></td>
                        <th>Aprovado / Reprovado</th>
                        <td><?= (int) $approvedCount; ?> / <?= (int) $failedCount; ?></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <footer class="mt-5 border-t-2 border-slate-900 pt-3 text-[11px]">
            <p><strong>Descricao:</strong> Historico escolar consolidado para comprovacao academica interna da ANEO, com os resultados finais registrados no sistema.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="border border-dashed border-slate-900 p-5 text-center uppercase tracking-wide">Assinatura Responsavel ANEO</div>
                <div class="border border-dashed border-slate-900 p-5 text-center uppercase tracking-wide">Carimbo Oficial ANEO</div>
            </div>
        </footer>
    </article>
</section>
