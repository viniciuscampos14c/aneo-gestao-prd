<?php $csrf = '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; ?>
<link rel="stylesheet" href="assets/css/gestao_aluno.css">

<div class="p-4 max-w-5xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-slate-800">ConfiguraÃ§Ãµes â€” GestÃ£o do Aluno</h2>
            <p class="text-xs text-slate-500">Gerencie colunas, etiquetas, campos, automaÃ§Ãµes e templates</p>
        </div>
        <a href="<?= route('gestao-aluno') ?>" class="gda-btn gda-btn-default text-sm">â† Voltar ao Board</a>
    </div>

    <!-- Abas de configuraÃ§Ã£o -->
    <div class="flex gap-2 border-b border-slate-200 mb-6 overflow-x-auto">
        <button class="gda-modal-tab active" data-stab="columns">Colunas</button>
        <button class="gda-modal-tab" data-stab="labels">Etiquetas</button>
        <button class="gda-modal-tab" data-stab="fields">Campos</button>
        <button class="gda-modal-tab" data-stab="automations">AutomaÃ§Ãµes</button>
        <button class="gda-modal-tab" data-stab="templates">Templates</button>
    </div>

    <!-- ======== COLUNAS ======== -->
    <div class="gda-stab-panel active" data-stab="columns">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 font-semibold">
                    <tr>
                        <th class="text-left px-4 py-3">Cor</th>
                        <th class="text-left px-4 py-3">Nome</th>
                        <th class="text-left px-4 py-3">Ordem</th>
                        <th class="text-left px-4 py-3">PadrÃ£o</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($columns as $col) { ?>
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-2">
                                <span class="inline-block w-5 h-5 rounded-full" style="background:<?= e($col['color']) ?>"></span>
                            </td>
                            <td class="px-4 py-2 font-semibold text-slate-700"><?= e($col['name']) ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= (int) $col['display_order'] ?></td>
                            <td class="px-4 py-2">
                                <?= $col['is_default'] ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">PadrÃ£o</span>' : '' ?>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button class="gda-btn gda-btn-default gda-btn-sm" onclick="openEditCol(<?= (int)$col['id'] ?>, '<?= e(addslashes($col['name'])) ?>', '<?= e($col['color']) ?>', <?= (int)$col['display_order'] ?>, <?= $col['is_default'] ? 1 : 0 ?>)">Editar</button>
                                <form method="post" action="<?= route('gestao-aluno/column/delete') ?>" style="display:inline" onsubmit="return confirm('Remover coluna?')">
                                    <?= $csrf ?>
                                    <input type="hidden" name="id" value="<?= (int) $col['id'] ?>">
                                    <button type="submit" class="gda-btn gda-btn-danger gda-btn-sm">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($columns)) { ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400 text-sm">Nenhuma coluna cadastrada.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-700 mb-4 text-sm" id="colFormTitle">Nova Coluna</h3>
            <form method="post" id="colForm" action="<?= route('gestao-aluno/column/store') ?>">
                <?= $csrf ?>
                <input type="hidden" name="id" id="colId" value="0">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="text-xs text-slate-500 font-semibold block mb-1">Nome *</label>
                        <input type="text" name="name" id="colName" class="gda-input text-sm" required placeholder="Ex: Em NegociaÃ§Ã£o">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 font-semibold block mb-1">Cor</label>
                        <input type="color" name="color" id="colColor" value="#0ea5e9" class="h-9 w-20 rounded border border-slate-200 cursor-pointer">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 font-semibold block mb-1">Ordem</label>
                        <input type="number" name="display_order" id="colOrder" value="99" class="gda-input text-sm w-24">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_default" id="colDefault" value="1" class="rounded">
                        <label for="colDefault" class="text-sm text-slate-600">Coluna padrÃ£o para novos alunos</label>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="gda-btn gda-btn-primary text-sm">Salvar Coluna</button>
                    <button type="button" class="gda-btn gda-btn-default text-sm" onclick="resetColForm()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======== ETIQUETAS ======== -->
    <div class="gda-stab-panel" data-stab="labels">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 font-semibold">
                    <tr>
                        <th class="text-left px-4 py-3">Cor</th>
                        <th class="text-left px-4 py-3">Nome</th>
                        <th class="text-left px-4 py-3">Ordem</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($labels as $lbl) { ?>
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-2"><span class="inline-block w-16 h-4 rounded" style="background:<?= e($lbl['color']) ?>"></span></td>
                            <td class="px-4 py-2 font-semibold text-slate-700"><?= e($lbl['name']) ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= (int) $lbl['display_order'] ?></td>
                            <td class="px-4 py-2 text-right">
                                <button class="gda-btn gda-btn-default gda-btn-sm" onclick="openEditLbl(<?= (int)$lbl['id'] ?>, '<?= e(addslashes($lbl['name'])) ?>', '<?= e($lbl['color']) ?>', <?= (int)$lbl['display_order'] ?>)">Editar</button>
                                <button class="gda-btn gda-btn-danger gda-btn-sm" onclick="deleteLabel(<?= (int)$lbl['id'] ?>)">Remover</button>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($labels)) { ?>
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400 text-sm">Nenhuma etiqueta cadastrada.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-700 mb-4 text-sm" id="lblFormTitle">Nova Etiqueta</h3>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <input type="hidden" id="lblId" value="0">
                <div>
                    <label class="text-xs text-slate-500 font-semibold block mb-1">Nome *</label>
                    <input type="text" id="lblName" class="gda-input text-sm" placeholder="Ex: VIP">
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-semibold block mb-1">Cor</label>
                    <input type="color" id="lblColor" value="#3b82f6" class="h-9 w-20 rounded border border-slate-200 cursor-pointer">
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-semibold block mb-1">Ordem</label>
                    <input type="number" id="lblOrder" value="99" class="gda-input text-sm w-24">
                </div>
            </div>
            <div class="flex gap-2">
                <button class="gda-btn gda-btn-primary text-sm" onclick="saveLabel()">Salvar Etiqueta</button>
                <button class="gda-btn gda-btn-default text-sm" onclick="resetLblForm()">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- ======== CAMPOS CUSTOMIZADOS ======== -->
    <div class="gda-stab-panel" data-stab="fields">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 font-semibold">
                    <tr>
                        <th class="text-left px-4 py-3">Nome</th>
                        <th class="text-left px-4 py-3">Tipo</th>
                        <th class="text-left px-4 py-3">OpÃ§Ãµes</th>
                        <th class="text-left px-4 py-3">Ordem</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $f) { ?>
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-2 font-semibold text-slate-700"><?= e($f['name']) ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= e($f['field_type']) ?></td>
                            <td class="px-4 py-2 text-xs text-slate-400"><?= e($f['options_json'] ?? '') ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= (int) $f['display_order'] ?></td>
                            <td class="px-4 py-2 text-right">
                                <button class="gda-btn gda-btn-default gda-btn-sm" onclick="openEditField(<?= (int)$f['id'] ?>, '<?= e(addslashes($f['name'])) ?>', '<?= e($f['field_type']) ?>', '<?= e(addslashes($f['options_json']??'')) ?>', <?= (int)$f['display_order'] ?>)">Editar</button>
                                <button class="gda-btn gda-btn-danger gda-btn-sm" onclick="deleteField(<?= (int)$f['id'] ?>)">Remover</button>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($fields)) { ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400 text-sm">Nenhum campo cadastrado.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-700 mb-4 text-sm" id="fieldFormTitle">Novo Campo</h3>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <input type="hidden" id="fieldId" value="0">
                <div>
                    <label class="text-xs text-slate-500 font-semibold block mb-1">Nome *</label>
                    <input type="text" id="fieldName" class="gda-input text-sm" placeholder="Ex: Turma">
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-semibold block mb-1">Tipo</label>
                    <select id="fieldType" class="gda-input gda-select text-sm">
                        <option value="text">Texto</option>
                        <option value="number">NÃºmero</option>
                        <option value="date">Data</option>
                        <option value="select">SeleÃ§Ã£o</option>
                        <option value="checkbox">Checkbox</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-semibold block mb-1">OpÃ§Ãµes (para tipo SeleÃ§Ã£o, JSON array)</label>
                    <input type="text" id="fieldOptions" class="gda-input text-sm" placeholder='["OpÃ§Ã£o 1","OpÃ§Ã£o 2"]'>
                </div>
                <div>
                    <label class="text-xs text-slate-500 font-semibold block mb-1">Ordem</label>
                    <input type="number" id="fieldOrder" value="99" class="gda-input text-sm w-24">
                </div>
            </div>
            <div class="flex gap-2">
                <button class="gda-btn gda-btn-primary text-sm" onclick="saveField()">Salvar Campo</button>
                <button class="gda-btn gda-btn-default text-sm" onclick="resetFieldForm()">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- ======== AUTOMAÃ‡Ã•ES ======== -->
    <div class="gda-stab-panel" data-stab="automations">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 font-semibold">
                    <tr>
                        <th class="text-left px-4 py-3">Nome</th>
                        <th class="text-left px-4 py-3">Gatilho</th>
                        <th class="text-left px-4 py-3">AÃ§Ã£o</th>
                        <th class="text-left px-4 py-3">Ativo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($automations as $a) { ?>
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-2 font-semibold text-slate-700"><?= e($a['name']) ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= e($a['trigger_type']) ?><?= $a['trigger_value'] ? ' = ' . e($a['trigger_value']) : '' ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= e($a['action_type']) ?><?= $a['action_value'] ? ' = ' . e($a['action_value']) : '' ?></td>
                            <td class="px-4 py-2"><?= $a['is_active'] ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Sim</span>' : '<span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">NÃ£o</span>' ?></td>
                            <td class="px-4 py-2 text-right">
                                <button class="gda-btn gda-btn-default gda-btn-sm" onclick="openEditAuto(<?= (int)$a['id'] ?>, '<?= e(addslashes($a['name'])) ?>', '<?= e($a['trigger_type']) ?>', '<?= e(addslashes($a['trigger_value']??'')) ?>', '<?= e($a['action_type']) ?>', '<?= e(addslashes($a['action_value']??'')) ?>', <?= $a['is_active'] ? 1 : 0 ?>)">Editar</button>
                                <button class="gda-btn gda-btn-danger gda-btn-sm" onclick="deleteAutomation(<?= (int)$a['id'] ?>)">Remover</button>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($automations)) { ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400 text-sm">Nenhuma automaÃ§Ã£o cadastrada.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-700 mb-4 text-sm" id="autoFormTitle">Nova AutomaÃ§Ã£o</h3>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <input type="hidden" id="autoId" value="0">
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Nome *</label><input type="text" id="autoName" class="gda-input text-sm" placeholder="Ex: Mover para Contrato ao pagar"></div>
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Tipo de Gatilho</label>
                    <select id="autoTriggerType" class="gda-input gda-select text-sm">
                        <option value="card_moved">Card movido</option>
                        <option value="priority_set">Prioridade definida</option>
                        <option value="label_added">Etiqueta adicionada</option>
                        <option value="due_date_passed">Prazo vencido</option>
                    </select>
                </div>
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Valor do Gatilho (ID da coluna, etc.)</label><input type="text" id="autoTriggerVal" class="gda-input text-sm"></div>
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Tipo de AÃ§Ã£o</label>
                    <select id="autoActionType" class="gda-input gda-select text-sm">
                        <option value="move_to_column">Mover para coluna</option>
                        <option value="set_priority">Definir prioridade</option>
                        <option value="add_label">Adicionar etiqueta</option>
                        <option value="notify_assigned">Notificar responsÃ¡vel</option>
                    </select>
                </div>
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Valor da AÃ§Ã£o</label><input type="text" id="autoActionVal" class="gda-input text-sm"></div>
                <div class="flex items-center gap-2"><input type="checkbox" id="autoActive" checked class="rounded"><label for="autoActive" class="text-sm text-slate-600">AutomaÃ§Ã£o ativa</label></div>
            </div>
            <div class="flex gap-2">
                <button class="gda-btn gda-btn-primary text-sm" onclick="saveAutomation()">Salvar AutomaÃ§Ã£o</button>
                <button class="gda-btn gda-btn-default text-sm" onclick="resetAutoForm()">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- ======== TEMPLATES ======== -->
    <div class="gda-stab-panel" data-stab="templates">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 font-semibold">
                    <tr>
                        <th class="text-left px-4 py-3">Nome</th>
                        <th class="text-left px-4 py-3">Prioridade</th>
                        <th class="text-left px-4 py-3">Ordem</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $t) { ?>
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-2 font-semibold text-slate-700"><?= e($t['name']) ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= e($t['priority']) ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= (int) $t['display_order'] ?></td>
                            <td class="px-4 py-2 text-right">
                                <button class="gda-btn gda-btn-danger gda-btn-sm" onclick="deleteTemplate(<?= (int)$t['id'] ?>)">Remover</button>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($templates)) { ?>
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400 text-sm">Nenhum template cadastrado.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-700 mb-4 text-sm">Novo Template</h3>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Nome *</label><input type="text" id="tplName" class="gda-input text-sm"></div>
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Prioridade padrÃ£o</label>
                    <select id="tplPriority" class="gda-input gda-select text-sm">
                        <option value="none">Nenhuma</option>
                        <option value="low">Baixa</option>
                        <option value="medium">MÃ©dia</option>
                        <option value="high">Alta</option>
                        <option value="critical">CrÃ­tica</option>
                    </select>
                </div>
                <div class="col-span-2"><label class="text-xs text-slate-500 font-semibold block mb-1">DescriÃ§Ã£o</label><input type="text" id="tplDesc" class="gda-input text-sm w-full"></div>
                <div><label class="text-xs text-slate-500 font-semibold block mb-1">Ordem</label><input type="number" id="tplOrder" value="99" class="gda-input text-sm w-24"></div>
            </div>
            <div class="flex gap-2">
                <button class="gda-btn gda-btn-primary text-sm" onclick="saveTemplate()">Salvar Template</button>
            </div>
        </div>
    </div>

</div>

<script>
(function () {
    const csrf = <?= json_encode(csrf_token()) ?>;

    // Tab switching
    document.querySelectorAll('[data-stab]').forEach(btn => {
        if (!btn.matches('.gda-stab-panel')) {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.gda-modal-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const tab = btn.dataset.stab;
                document.querySelectorAll('.gda-stab-panel').forEach(p => {
                    p.classList.toggle('active', p.dataset.stab === tab);
                });
            });
        }
    });

    async function apiPost(url, data) {
        const fd = new FormData();
        fd.append('_csrf', csrf);
        Object.entries(data).forEach(([k,v]) => fd.append(k, v ?? ''));
        const r = await fetch(url, { method: 'POST', body: fd });
        return r.json().catch(() => ({}));
    }

    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = 'fixed bottom-4 right-4 z-[9999] px-4 py-2 rounded-lg text-sm font-semibold shadow-lg text-white ' +
            (type === 'error' ? 'bg-red-500' : 'bg-green-600');
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    // ---- COLUNAS ----
    window.openEditCol = (id, name, color, order, def) => {
        document.getElementById('colId').value = id;
        document.getElementById('colName').value = name;
        document.getElementById('colColor').value = color;
        document.getElementById('colOrder').value = order;
        document.getElementById('colDefault').checked = !!def;
        document.getElementById('colFormTitle').textContent = 'Editar Coluna';
        document.getElementById('colForm').action = <?= json_encode(route('gestao-aluno/column/update')) ?>;
    };
    window.resetColForm = () => {
        document.getElementById('colId').value = '0';
        document.getElementById('colName').value = '';
        document.getElementById('colColor').value = '#0ea5e9';
        document.getElementById('colOrder').value = '99';
        document.getElementById('colDefault').checked = false;
        document.getElementById('colFormTitle').textContent = 'Nova Coluna';
        document.getElementById('colForm').action = <?= json_encode(route('gestao-aluno/column/store')) ?>;
    };

    // ---- ETIQUETAS ----
    window.openEditLbl = (id, name, color, order) => {
        document.getElementById('lblId').value = id;
        document.getElementById('lblName').value = name;
        document.getElementById('lblColor').value = color;
        document.getElementById('lblOrder').value = order;
        document.getElementById('lblFormTitle').textContent = 'Editar Etiqueta';
    };
    window.resetLblForm = () => {
        document.getElementById('lblId').value = '0';
        document.getElementById('lblName').value = '';
        document.getElementById('lblColor').value = '#3b82f6';
        document.getElementById('lblOrder').value = '99';
        document.getElementById('lblFormTitle').textContent = 'Nova Etiqueta';
    };
    window.saveLabel = async () => {
        const r = await apiPost(<?= json_encode(route('gestao-aluno/label/save')) ?>, {
            id:            document.getElementById('lblId').value,
            name:          document.getElementById('lblName').value,
            color:         document.getElementById('lblColor').value,
            display_order: document.getElementById('lblOrder').value,
        });
        r.ok ? (toast('Etiqueta salva.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };
    window.deleteLabel = async (id) => {
        if (!confirm('Remover etiqueta?')) return;
        const r = await apiPost(<?= json_encode(route('gestao-aluno/label/delete')) ?>, { id });
        r.ok ? (toast('Removida.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };

    // ---- CAMPOS ----
    window.openEditField = (id, name, type, opts, order) => {
        document.getElementById('fieldId').value = id;
        document.getElementById('fieldName').value = name;
        document.getElementById('fieldType').value = type;
        document.getElementById('fieldOptions').value = opts;
        document.getElementById('fieldOrder').value = order;
        document.getElementById('fieldFormTitle').textContent = 'Editar Campo';
    };
    window.resetFieldForm = () => {
        document.getElementById('fieldId').value = '0';
        document.getElementById('fieldName').value = '';
        document.getElementById('fieldType').value = 'text';
        document.getElementById('fieldOptions').value = '';
        document.getElementById('fieldOrder').value = '99';
        document.getElementById('fieldFormTitle').textContent = 'Novo Campo';
    };
    window.saveField = async () => {
        const r = await apiPost(<?= json_encode(route('gestao-aluno/custom-field/save')) ?>, {
            id:            document.getElementById('fieldId').value,
            name:          document.getElementById('fieldName').value,
            field_type:    document.getElementById('fieldType').value,
            options_json:  document.getElementById('fieldOptions').value,
            display_order: document.getElementById('fieldOrder').value,
        });
        r.ok ? (toast('Campo salvo.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };
    window.deleteField = async (id) => {
        if (!confirm('Remover campo?')) return;
        const r = await apiPost(<?= json_encode(route('gestao-aluno/custom-field/delete')) ?>, { id });
        r.ok ? (toast('Removido.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };

    // ---- AUTOMAÃ‡Ã•ES ----
    window.openEditAuto = (id, name, tType, tVal, aType, aVal, active) => {
        document.getElementById('autoId').value = id;
        document.getElementById('autoName').value = name;
        document.getElementById('autoTriggerType').value = tType;
        document.getElementById('autoTriggerVal').value = tVal;
        document.getElementById('autoActionType').value = aType;
        document.getElementById('autoActionVal').value = aVal;
        document.getElementById('autoActive').checked = !!active;
        document.getElementById('autoFormTitle').textContent = 'Editar AutomaÃ§Ã£o';
    };
    window.resetAutoForm = () => {
        ['autoId','autoName','autoTriggerVal','autoActionVal'].forEach(n => document.getElementById(n).value = n === 'autoId' ? '0' : '');
        document.getElementById('autoActive').checked = true;
        document.getElementById('autoFormTitle').textContent = 'Nova AutomaÃ§Ã£o';
    };
    window.saveAutomation = async () => {
        const r = await apiPost(<?= json_encode(route('gestao-aluno/automation/save')) ?>, {
            id:            document.getElementById('autoId').value,
            name:          document.getElementById('autoName').value,
            trigger_type:  document.getElementById('autoTriggerType').value,
            trigger_value: document.getElementById('autoTriggerVal').value,
            action_type:   document.getElementById('autoActionType').value,
            action_value:  document.getElementById('autoActionVal').value,
            is_active:     document.getElementById('autoActive').checked ? 1 : 0,
        });
        r.ok ? (toast('AutomaÃ§Ã£o salva.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };
    window.deleteAutomation = async (id) => {
        if (!confirm('Remover automaÃ§Ã£o?')) return;
        const r = await apiPost(<?= json_encode(route('gestao-aluno/automation/delete')) ?>, { id });
        r.ok ? (toast('Removida.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };

    // ---- TEMPLATES ----
    window.saveTemplate = async () => {
        const r = await apiPost(<?= json_encode(route('gestao-aluno/template/save')) ?>, {
            name:          document.getElementById('tplName').value,
            description:   document.getElementById('tplDesc').value,
            priority:      document.getElementById('tplPriority').value,
            display_order: document.getElementById('tplOrder').value,
        });
        r.ok ? (toast('Template salvo.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };
    window.deleteTemplate = async (id) => {
        if (!confirm('Remover template?')) return;
        const r = await apiPost(<?= json_encode(route('gestao-aluno/template/delete')) ?>, { id });
        r.ok ? (toast('Removido.', 'success'), setTimeout(() => location.reload(), 800))
              : toast(r.message || 'Erro.', 'error');
    };

})();
</script>

<style>
.gda-stab-panel { display: none; }
.gda-stab-panel.active { display: block; }
</style>


