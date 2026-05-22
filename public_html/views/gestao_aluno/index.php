<?php
$user   = current_user();
$userId = (int) ($user['id'] ?? 0);
$gdaCssPath = __DIR__ . '/../../assets/css/gestao_aluno.css';
$gdaCssVersion = is_file($gdaCssPath) ? (string) filemtime($gdaCssPath) : date('YmdHis');
$gdaJsPath = __DIR__ . '/../../assets/js/gestao_aluno.js';
$gdaJsVersion = is_file($gdaJsPath) ? (string) filemtime($gdaJsPath) : date('YmdHis');
$gdaInitials = static function (?string $name): string {
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $first = $parts[0] ?? '?';
    $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
    $initials = mb_substr($first, 0, 1) . ($last !== '' ? mb_substr($last, 0, 1) : mb_substr($first, 1, 1));
    return mb_strtoupper($initials ?: '?');
};
?>
<link rel="stylesheet" href="assets/css/gestao_aluno.css?v=<?= e($gdaCssVersion); ?>">

<div class="p-4">

    <!-- Toolbar -->
    <div class="gda-toolbar">
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-tight">Gestão do Aluno</h2>
            <p class="text-xs text-slate-500">Board Kanban dos alunos</p>
        </div>

        <div class="gda-toolbar-right">
            <!-- Busca -->
            <form method="get" action="" class="flex gap-2">
                <input type="text" name="q" value="<?= e($search) ?>"
                    placeholder="Buscar aluno..."
                    class="gda-input w-44 text-xs">
                <button type="submit" class="gda-btn gda-btn-default text-xs">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.41-1.41l4.38 4.38-1.41 1.41-4.38-4.38zM8 14A6 6 0 108 2a6 6 0 000 12z" clip-rule="evenodd"/>
                    </svg>
                    Buscar
                </button>
                <?php if ($search !== '') { ?>
                    <a href="<?= route('gestao-aluno') ?>" class="gda-btn gda-btn-default text-xs">Limpar</a>
                <?php } ?>
            </form>

            <!-- Filtros -->
            <button id="gdaToggleFilter" class="gda-btn gda-btn-default text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L13 10.414V15a1 1 0 01-.553.894l-4 2A1 1 0 017 17v-6.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/>
                </svg>
                Filtros
            </button>

            <!-- Meus cards -->
            <button id="gdaMyCards" data-uid="<?= $userId ?>" class="gda-btn gda-btn-default text-xs">
                Meus Cards
            </button>

            <!-- Arquivados -->
            <a href="<?= route('gestao-aluno' . ($archived ? '' : '&archived=1')) ?>" class="gda-btn gda-btn-default text-xs <?= $archived ? 'ring-2 ring-amber-400' : '' ?>">
                <?= $archived ? 'Ver Ativos' : 'Arquivados' ?>
            </a>

            <!-- Calendário -->
            <a href="<?= route('gestao-aluno/calendar') ?>" class="gda-btn gda-btn-default text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
                Calendário
            </a>

            <!-- Configurar -->
            <a href="<?= route('gestao-aluno/settings') ?>" class="gda-btn gda-btn-default text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                </svg>
                Configurar
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="gda-filter-bar" id="gdaFilterBar">
        <div class="flex flex-col gap-1">
            <label class="text-xs text-slate-500 font-semibold">Prioridade</label>
            <select id="fdPriority" class="gda-input gda-select text-xs w-36">
                <option value="">Todas</option>
                <option value="critical">Crítica</option>
                <option value="high">Alta</option>
                <option value="medium">Média</option>
                <option value="low">Baixa</option>
                <option value="none">Nenhuma</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs text-slate-500 font-semibold">Prazo</label>
            <select id="fdDue" class="gda-input gda-select text-xs w-36">
                <option value="">Todos</option>
                <option value="overdue">Vencido</option>
                <option value="today">Hoje</option>
                <option value="soon">Próximos 3 dias</option>
                <option value="none">Sem prazo</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs text-slate-500 font-semibold">Etiqueta</label>
            <select id="fdLabel" class="gda-input gda-select text-xs w-40">
                <option value="">Todas</option>
                <?php foreach ($labels as $lbl) { ?>
                    <option value="<?= (int) $lbl['id'] ?>"><?= e($lbl['name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs text-slate-500 font-semibold">Responsável</label>
            <select id="fdAssigned" class="gda-input gda-select text-xs w-44">
                <option value="">Todos</option>
                <?php foreach ($users as $u) { ?>
                    <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="gdaApplyFilter" class="gda-btn gda-btn-primary text-xs">Aplicar</button>
            <button id="gdaClearFilter" class="gda-btn gda-btn-default text-xs">Limpar</button>
        </div>
    </div>

    <!-- Board -->
    <div class="gda-board-wrap" id="gdaBoard">
        <?php foreach ($columns as $col) { ?>
            <div class="gda-column"
                data-col-id="<?= (int) $col['id'] ?>"
                data-col-name="<?= e($col['name']) ?>"
                data-col-color="<?= e($col['color']) ?>">

                <div class="gda-column-header">
                    <span class="gda-column-dot" style="background:<?= e($col['color']) ?>"></span>
                    <span class="gda-column-name"><?= e($col['name']) ?></span>
                    <span class="gda-column-count"><?= (int) $col['total_students'] ?></span>
                </div>

                <div class="gda-cards-list" data-col-id="<?= (int) $col['id'] ?>">
                    <?php foreach ($col['students'] as $s) { ?>
                        <?php
                        $priority  = $s['gda_priority'] ?? 'none';
                        $dueDate   = $s['gda_due_date'] ?? '';
                        $dueClass  = '';
                        $dueLabel  = '';
                        if ($dueDate) {
                            $today = date('Y-m-d');
                            $soon  = date('Y-m-d', strtotime('+3 days'));
                            if ($dueDate < $today)       { $dueClass = 'gda-due-overdue'; $dueLabel = date('d/m', strtotime($dueDate)); }
                            elseif ($dueDate === $today)  { $dueClass = 'gda-due-today';   $dueLabel = 'Hoje'; }
                            elseif ($dueDate <= $soon)   { $dueClass = 'gda-due-soon';    $dueLabel = date('d/m', strtotime($dueDate)); }
                            else                          { $dueLabel = date('d/m', strtotime($dueDate)); }
                        }
                        $labelIds = array_column($s['labels'] ?? [], 'id');
                        $members = $s['members'] ?? [];
                        $memberNames = array_map(static fn ($member) => (string) ($member['name'] ?? ''), $members);
                        ?>
                        <div class="gda-card"
                            draggable="true"
                            data-id="<?= (int) $s['id'] ?>"
                            data-col-id="<?= (int) $col['id'] ?>"
                            data-priority="<?= e($priority) ?>"
                            data-due="<?= e($dueDate) ?>"
                            data-label-ids="<?= e(implode(',', $labelIds)) ?>"
                            data-assigned="<?= (int) ($s['gda_assigned_to'] ?? 0) ?>">

                            <?php if (!empty($s['gda_cover_color'])) { ?>
                                <div class="gda-card-cover" style="background:<?= e($s['gda_cover_color']) ?>"></div>
                            <?php } ?>

                            <?php if (!empty($s['labels'])) { ?>
                                <div class="gda-card-labels px-3 pt-2">
                                    <?php foreach ($s['labels'] as $lbl) { ?>
                                        <span class="gda-label-chip" style="background:<?= e($lbl['color']) ?>" title="<?= e($lbl['name']) ?>"></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <div class="gda-card-body">
                                <div class="gda-card-name"><?= e($s['full_name']) ?></div>
                                <div class="gda-card-sub"><?= e($s['email_primary'] ?? '') ?><?= !empty($s['phone']) ? ' · ' . e($s['phone']) : '' ?></div>
                                <?php if (!empty($s['city'])) { ?>
                                    <div class="gda-card-sub"><?= e($s['city']) ?></div>
                                <?php } ?>

                                <div class="gda-card-footer">
                                    <?php if ($priority !== 'none') { ?>
                                        <span class="gda-badge">
                                            <span class="gda-priority-dot gda-priority-<?= e($priority) ?>"></span>
                                            <?= e(ucfirst($priority)) ?>
                                        </span>
                                    <?php } ?>

                                    <?php if ($dueLabel) { ?>
                                        <span class="gda-badge <?= $dueClass ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                            </svg>
                                            <?= e($dueLabel) ?>
                                        </span>
                                    <?php } ?>

                                    <?php if ((int) ($s['checklist_total'] ?? 0) > 0) { ?>
                                        <span class="gda-badge">
                                            ✓ <?= (int) $s['checklist_done'] ?>/<?= (int) $s['checklist_total'] ?>
                                        </span>
                                    <?php } ?>

                                    <?php if ((int) ($s['notes_count'] ?? 0) > 0) { ?>
                                        <span class="gda-badge">💬 <?= (int) $s['notes_count'] ?></span>
                                    <?php } ?>

                                    <?php if ((int) ($s['attachment_count'] ?? 0) > 0) { ?>
                                        <span class="gda-badge">📎 <?= (int) $s['attachment_count'] ?></span>
                                    <?php } ?>

                                    <?php if ((int) ($s['finance_open_count'] ?? 0) > 0) { ?>
                                        <span class="gda-badge gda-badge-finance <?= ((int) ($s['finance_overdue_count'] ?? 0) > 0) ? 'gda-fin-overdue' : 'gda-fin-open' ?>">
                                            Fin <?= (int) $s['finance_open_count'] ?> - <?= e(format_currency($s['finance_open_amount'] ?? 0)) ?>
                                        </span>
                                    <?php } ?>

                                    <?php if (!empty($members)) { ?>
                                        <span class="gda-card-members" title="<?= e('Membros: ' . implode(', ', array_filter($memberNames))) ?>">
                                            <?php foreach (array_slice($members, 0, 3) as $member) { ?>
                                                <span class="gda-avatar gda-member-avatar" title="<?= e($member['name'] ?? '') ?>">
                                                    <?= e($gdaInitials($member['name'] ?? '')) ?>
                                                </span>
                                            <?php } ?>
                                            <?php if (count($members) > 3) { ?>
                                                <span class="gda-avatar gda-member-avatar gda-avatar-more">+<?= count($members) - 3 ?></span>
                                            <?php } ?>
                                        </span>
                                    <?php } elseif (!empty($s['assigned_name'])) { ?>
                                        <span class="gda-card-members" title="<?= e('Responsavel: ' . $s['assigned_name']) ?>">
                                            <span class="gda-avatar gda-member-avatar">
                                                <?= e($gdaInitials($s['assigned_name'])) ?>
                                            </span>
                                        </span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Quick Add -->
                <div class="gda-quick-add-wrap">
                    <div class="gda-quick-add-input-wrap" data-col-id="<?= (int) $col['id'] ?>">
                        <input type="text" class="gda-quick-add-input" placeholder="Buscar aluno para adicionar..." autocomplete="off">
                        <div class="gda-quick-results" style="display:none"></div>
                    </div>
                    <button class="gda-quick-add-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Adicionar aluno
                    </button>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Modal Card -->
<?php require __DIR__ . '/partials/modal_card.php'; ?>

<script>
window.gdaConfig = {
    csrf:              <?= json_encode(csrf_token()) ?>,
    currentUserId:     <?= $userId ?>,
    moveUrl:           <?= json_encode(route('gestao-aluno/move')) ?>,
    reorderUrl:        <?= json_encode(route('gestao-aluno/reorder')) ?>,
    getCardUrl:        <?= json_encode(route('gestao-aluno/card')) ?>,
    updateMetaUrl:     <?= json_encode(route('gestao-aluno/card/meta')) ?>,
    archiveUrl:        <?= json_encode(route('gestao-aluno/card/archive')) ?>,
    saveNoteUrl:       <?= json_encode(route('gestao-aluno/note/save')) ?>,
    deleteNoteUrl:     <?= json_encode(route('gestao-aluno/note/delete')) ?>,
    saveChecklistUrl:  <?= json_encode(route('gestao-aluno/checklist/save')) ?>,
    delChecklistUrl:   <?= json_encode(route('gestao-aluno/checklist/delete')) ?>,
    saveItemUrl:       <?= json_encode(route('gestao-aluno/checklist/item/save')) ?>,
    toggleItemUrl:     <?= json_encode(route('gestao-aluno/checklist/item/toggle')) ?>,
    delItemUrl:        <?= json_encode(route('gestao-aluno/checklist/item/delete')) ?>,
    setLabelsUrl:      <?= json_encode(route('gestao-aluno/card/labels')) ?>,
    setMembersUrl:     <?= json_encode(route('gestao-aluno/card/members')) ?>,
    saveCfValueUrl:    <?= json_encode(route('gestao-aluno/custom-field/value')) ?>,
    applyTemplateUrl:  <?= json_encode(route('gestao-aluno/template/apply')) ?>,
    uploadAttUrl:      <?= json_encode(route('gestao-aluno/attachment/upload')) ?>,
    deleteAttUrl:      <?= json_encode(route('gestao-aluno/attachment/delete')) ?>,
    downloadAttUrl:    <?= json_encode(route('gestao-aluno/attachment/download')) ?>,
    studentShowUrl:    <?= json_encode(route('students/show')) ?>,
    searchUrl:         <?= json_encode(route('gestao-aluno/search')) ?>,
    quickAddUrl:       <?= json_encode(route('gestao-aluno/quick-add')) ?>,
};
</script>
<script src="assets/js/gestao_aluno.js?v=<?= e($gdaJsVersion) ?>"></script>
