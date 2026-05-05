/* ============================================================
   GESTÃO DO ALUNO — Board JS
   ============================================================ */
(function () {
    'use strict';

    const cfg = window.gdaConfig || {};
    let currentStudentId = null;
    let currentCardData  = null;

    // --------------------------------------------------------
    // CSRF helper
    // --------------------------------------------------------
    function getCsrf() { return cfg.csrf || ''; }

    async function post(url, data) {
        const fd = new FormData();
        fd.append('_csrf', getCsrf());
        for (const [k, v] of Object.entries(data)) {
            if (Array.isArray(v)) {
                v.forEach(x => fd.append(k + '[]', x));
            } else {
                fd.append(k, v ?? '');
            }
        }
        const res = await fetch(url, { method: 'POST', body: fd });
        const json = await res.json().catch(() => ({}));
        if (json.csrf) cfg.csrf = json.csrf;
        return json;
    }

    async function get(url, params) {
        const qs = new URLSearchParams(params || {}).toString();
        const sep = url.includes('?') ? '&' : '?';
        const res = await fetch(url + (qs ? sep + qs : ''));
        return res.json().catch(() => ({}));
    }

    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = 'fixed bottom-4 right-4 z-[9999] px-4 py-2 rounded-lg text-sm font-semibold shadow-lg text-white transition-opacity duration-300 ' +
            (type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-600' : 'bg-slate-700');
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3000);
    }

    // --------------------------------------------------------
    // DRAG & DROP
    // --------------------------------------------------------
    let dragSrcCard = null;
    let dragSrcCol  = null;

    function initDragDrop() {
        document.querySelectorAll('.gda-card').forEach(bindCard);
        document.querySelectorAll('.gda-cards-list').forEach(bindList);
    }

    function bindCard(card) {
        card.addEventListener('dragstart', e => {
            dragSrcCard = card;
            dragSrcCol  = card.closest('.gda-cards-list');
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            document.querySelectorAll('.gda-cards-list').forEach(l => l.classList.remove('drag-over'));
        });
        card.addEventListener('click', () => openModal(parseInt(card.dataset.id)));
    }

    function bindList(list) {
        list.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            list.classList.add('drag-over');

            const afterEl = getDragAfter(list, e.clientY);
            if (afterEl == null) {
                list.appendChild(dragSrcCard);
            } else {
                list.insertBefore(dragSrcCard, afterEl);
            }
        });

        list.addEventListener('dragleave', () => list.classList.remove('drag-over'));

        list.addEventListener('drop', async e => {
            e.preventDefault();
            list.classList.remove('drag-over');

            const destColId = parseInt(list.dataset.colId);
            const srcColId  = parseInt(dragSrcCard.dataset.colId);
            const studentId = parseInt(dragSrcCard.dataset.id);

            dragSrcCard.dataset.colId = destColId;

            if (destColId !== srcColId) {
                const r = await post(cfg.moveUrl, { student_id: studentId, column_id: destColId });
                if (!r.ok) {
                    toast(r.message || 'Erro ao mover card.', 'error');
                    // reverter
                    dragSrcCol.appendChild(dragSrcCard);
                    dragSrcCard.dataset.colId = srcColId;
                } else {
                    updateColumnCounts();
                }
            } else {
                // reordenar na mesma coluna
                const ids = [...list.querySelectorAll('.gda-card')].map(c => parseInt(c.dataset.id));
                await post(cfg.reorderUrl, { column_id: destColId, student_ids: ids });
            }
        });
    }

    function getDragAfter(list, y) {
        const cards = [...list.querySelectorAll('.gda-card:not(.dragging)')];
        return cards.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function updateColumnCounts() {
        document.querySelectorAll('.gda-column').forEach(col => {
            const count = col.querySelectorAll('.gda-card').length;
            const badge = col.querySelector('.gda-column-count');
            if (badge) badge.textContent = count;
        });
    }

    // --------------------------------------------------------
    // FILTROS
    // --------------------------------------------------------
    document.getElementById('gdaToggleFilter')?.addEventListener('click', () => {
        document.getElementById('gdaFilterBar')?.classList.toggle('open');
    });

    document.getElementById('gdaApplyFilter')?.addEventListener('click', applyFilters);
    document.getElementById('gdaClearFilter')?.addEventListener('click', () => {
        ['fdPriority','fdDue','fdLabel','fdAssigned'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        applyFilters();
    });

    document.getElementById('gdaMyCards')?.addEventListener('click', () => {
        const uid = document.getElementById('gdaMyCards').dataset.uid;
        const sel = document.getElementById('fdAssigned');
        if (sel) {
            sel.value = uid;
            document.getElementById('gdaFilterBar')?.classList.add('open');
            applyFilters();
        }
    });

    function applyFilters() {
        const priority = document.getElementById('fdPriority')?.value || '';
        const due      = document.getElementById('fdDue')?.value || '';
        const labelId  = document.getElementById('fdLabel')?.value || '';
        const assigned = document.getElementById('fdAssigned')?.value || '';

        const today   = new Date().toISOString().slice(0,10);
        const soon    = new Date(Date.now() + 3*86400000).toISOString().slice(0,10);

        document.querySelectorAll('.gda-card').forEach(card => {
            let show = true;

            if (priority && card.dataset.priority !== priority) show = false;

            if (due) {
                const d = card.dataset.due;
                if (due === 'none'    && d)        show = false;
                if (due === 'overdue' && (!d || d >= today)) show = false;
                if (due === 'today'   && d !== today)        show = false;
                if (due === 'soon'    && (!d || d < today || d > soon)) show = false;
            }

            if (labelId) {
                const ids = (card.dataset.labelIds || '').split(',').filter(Boolean);
                if (!ids.includes(labelId)) show = false;
            }

            if (assigned && card.dataset.assigned !== assigned) show = false;

            card.style.display = show ? '' : 'none';
        });

        updateColumnCounts();
    }

    // --------------------------------------------------------
    // QUICK ADD
    // --------------------------------------------------------
    function initQuickAdd() {
        document.querySelectorAll('.gda-quick-add-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrap = btn.closest('.gda-quick-add-wrap').querySelector('.gda-quick-add-input-wrap');
                const allWraps = document.querySelectorAll('.gda-quick-add-input-wrap');
                allWraps.forEach(w => { if (w !== wrap) w.classList.remove('open'); });
                wrap.classList.toggle('open');
                if (wrap.classList.contains('open')) wrap.querySelector('input').focus();
            });
        });

        document.querySelectorAll('.gda-quick-add-input-wrap').forEach(wrap => {
            const input   = wrap.querySelector('input');
            const results = wrap.querySelector('.gda-quick-results');
            const colId   = parseInt(wrap.dataset.colId);
            let debounce;

            input.addEventListener('input', () => {
                clearTimeout(debounce);
                const q = input.value.trim();
                if (q.length < 2) { results.style.display = 'none'; return; }
                debounce = setTimeout(async () => {
                    const r = await get(cfg.searchUrl, { q, col_id: colId });
                    results.innerHTML = '';
                    if (r.results && r.results.length) {
                        r.results.forEach(s => {
                            const item = document.createElement('div');
                            item.className = 'gda-quick-result-item';
                            item.textContent = s.full_name + (s.email_primary ? ' (' + s.email_primary + ')' : '');
                            item.addEventListener('click', async () => {
                                const r2 = await post(cfg.quickAddUrl, { student_id: s.id, column_id: colId });
                                if (r2.ok) {
                                    toast('Aluno adicionado ao board.', 'success');
                                    setTimeout(() => location.reload(), 800);
                                } else {
                                    toast(r2.message || 'Erro ao adicionar.', 'error');
                                }
                                wrap.classList.remove('open');
                            });
                            results.appendChild(item);
                        });
                        results.style.display = 'block';
                    } else {
                        results.innerHTML = '<div class="gda-quick-result-item text-slate-400">Nenhum resultado.</div>';
                        results.style.display = 'block';
                    }
                }, 350);
            });
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('.gda-quick-add-input-wrap') && !e.target.closest('.gda-quick-add-btn')) {
                document.querySelectorAll('.gda-quick-add-input-wrap').forEach(w => w.classList.remove('open'));
            }
        });
    }

    // --------------------------------------------------------
    // MODAL
    // --------------------------------------------------------
    const backdrop = document.getElementById('gdaModalBackdrop');
    const modalBody = document.getElementById('gdaModalBody');
    const modalTitle = document.getElementById('gdaModalTitle');
    const modalColBadge = document.getElementById('gdaModalColBadge');
    const modalLink = document.getElementById('gdaModalStudentLink');

    document.getElementById('gdaModalClose')?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    async function openModal(studentId) {
        currentStudentId = studentId;
        backdrop.classList.add('open');
        modalTitle.textContent = 'Carregando...';
        modalColBadge.style.display = 'none';
        modalLink.style.display = 'none';
        modalBody.innerHTML = '<div class="flex items-center justify-center py-12"><span class="gda-spinner"></span></div>';

        const r = await get(cfg.getCardUrl, { id: studentId });
        const normalizedCard = normalizeCard(r.card);
        if (!r.ok || !normalizedCard || !normalizedCard.student) {
            modalBody.innerHTML = '<p class="text-red-500 text-sm">Erro ao carregar o card.</p>';
            return;
        }

        currentCardData = normalizedCard;
        renderModal(normalizedCard);
    }

    function closeModal() {
        backdrop.classList.remove('open');
        currentStudentId = null;
        currentCardData  = null;
    }

    // Aceita payload novo (card.student + colecoes) e legado (campos planos no card).
    function normalizeCard(card) {
        if (!card || typeof card !== 'object') {
            return null;
        }

        if (card.student && typeof card.student === 'object') {
            return card;
        }

        const legacy = { ...card };
        const normalized = {
            student: { ...legacy },
            notes: Array.isArray(legacy.notes) ? legacy.notes : [],
            attachments: Array.isArray(legacy.attachments) ? legacy.attachments : [],
            history: Array.isArray(legacy.history) ? legacy.history : [],
            labels: Array.isArray(legacy.labels) ? legacy.labels : [],
            all_labels: Array.isArray(legacy.all_labels) ? legacy.all_labels : [],
            members: Array.isArray(legacy.members) ? legacy.members : [],
            all_users: Array.isArray(legacy.all_users) ? legacy.all_users : [],
            checklists: Array.isArray(legacy.checklists) ? legacy.checklists : [],
            custom_fields: Array.isArray(legacy.custom_fields) ? legacy.custom_fields : [],
            all_templates: Array.isArray(legacy.all_templates)
                ? legacy.all_templates
                : (Array.isArray(legacy.templates) ? legacy.templates : []),
        };

        delete normalized.student.notes;
        delete normalized.student.attachments;
        delete normalized.student.history;
        delete normalized.student.labels;
        delete normalized.student.all_labels;
        delete normalized.student.members;
        delete normalized.student.all_users;
        delete normalized.student.checklists;
        delete normalized.student.custom_fields;
        delete normalized.student.templates;
        delete normalized.student.all_templates;

        return normalized;
    }

    function renderModal(card) {
        const student = card.student;
        modalTitle.textContent = student.full_name;

        if (student.column_name) {
            modalColBadge.textContent = student.column_name;
            modalColBadge.style.background = student.column_color || '#3b82f6';
            modalColBadge.style.display = '';
        }

        if (student.id) {
            modalLink.href = 'students/show?id=' + student.id;
            modalLink.style.display = '';
        }

        const tpl = document.getElementById('gdaTplModalContent');
        const clone = tpl.content.cloneNode(true);
        modalBody.innerHTML = '';
        modalBody.appendChild(clone);

        // Preencher abas
        fillInfo(card);
        fillMeta(card);
        fillDesc(card);
        fillNotas(card);
        fillAnexos(card);
        fillHistorico(card);
        fillMembros(card);
        fillEtiquetas(card);
        fillChecklists(card);
        fillCampos(card);
        fillTemplates(card);

        // Layout em card unico: todos os paineis ficam visiveis no mesmo modal.
    }

    // --- INFO ---
    function fillInfo(card) {
        const s = card.student;
        set('mdInfoNome',  s.full_name);
        set('mdInfoCpf',   s.cpf || '—');
        set('mdInfoEmail', s.email_primary || '—');
        set('mdInfoPhone', s.phone || '—');
        set('mdInfoCity',  [s.city, s.state].filter(Boolean).join(' / ') || '—');
        set('mdInfoCol',   s.column_name || '—');
    }

    // --- META ---
    function fillMeta(card) {
        const s = card.student;
        val('mdMetaPriority', s.gda_priority || 'none');
        val('mdMetaDue', s.gda_due_date || '');
        val('mdMetaCover', s.gda_cover_color || '#ffffff');
        val('mdMetaAssigned', s.gda_assigned_to || '');

        const selAssigned = id('mdMetaAssigned');
        (card.all_users || []).forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id; opt.textContent = u.name;
            selAssigned.appendChild(opt);
        });
        selAssigned.value = s.gda_assigned_to || '';

        id('mdMetaSave')?.addEventListener('click', async () => {
            const coverVal = id('mdMetaCover').value;
            const r = await post(cfg.updateMetaUrl, {
                student_id:     currentStudentId,
                gda_priority:   val('mdMetaPriority'),
                gda_due_date:   val('mdMetaDue'),
                gda_cover_color: coverVal === '#ffffff' ? '' : coverVal,
                gda_assigned_to: val('mdMetaAssigned'),
            });
            r.ok ? toast('Meta salva.', 'success') : toast(r.message || 'Erro.', 'error');
        });

        id('mdMetaCoverClear')?.addEventListener('click', async () => {
            await post(cfg.updateMetaUrl, { student_id: currentStudentId, gda_cover_color: '' });
            id('mdMetaCover').value = '#ffffff';
            toast('Cor removida.', 'success');
        });

        id('mdArchive')?.addEventListener('click', async () => {
            if (!confirm('Arquivar este card?')) return;
            const r = await post(cfg.archiveUrl, { student_id: currentStudentId, archive: 1 });
            if (r.ok) { closeModal(); location.reload(); }
            else toast(r.message || 'Erro.', 'error');
        });
    }

    // --- DESCRIÇÃO ---
    function fillDesc(card) {
        val('mdDesc', card.student.gda_description || '');
        id('mdDescSave')?.addEventListener('click', async () => {
            const r = await post(cfg.updateMetaUrl, {
                student_id:      currentStudentId,
                gda_description: val('mdDesc'),
            });
            r.ok ? toast('Descrição salva.', 'success') : toast(r.message || 'Erro.', 'error');
        });
    }

    // --- NOTAS ---
    function fillNotas(card) {
        renderNotas(card.notes || []);

        id('mdNoteSave')?.addEventListener('click', async () => {
            const note = val('mdNoteInput').trim();
            if (!note) return;
            const r = await post(cfg.saveNoteUrl, { student_id: currentStudentId, note });
            if (r.ok) {
                val('mdNoteInput', '');
                const r2 = await get(cfg.getCardUrl, { id: currentStudentId });
                if (r2.ok) renderNotas(r2.card.notes || []);
            } else toast(r.message || 'Erro.', 'error');
        });

        id('mdNoteInput')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') id('mdNoteSave')?.click();
        });
    }

    function renderNotas(notes) {
        const list = id('mdNotesList');
        if (!list) return;
        list.innerHTML = '';
        if (!notes.length) {
            list.innerHTML = '<p class="text-xs text-slate-400">Nenhuma nota ainda.</p>';
            return;
        }
        notes.forEach(n => {
            const div = document.createElement('div');
            div.className = 'bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm';
            div.innerHTML = `
                <div class="flex justify-between items-start gap-2">
                    <div class="flex-1">
                        <p class="text-slate-800">${esc(n.note)}</p>
                        <p class="text-xs text-slate-400 mt-1">${esc(n.created_at)} — ${esc(n.user_name || '')}</p>
                    </div>
                    <button class="gda-btn gda-btn-danger gda-btn-sm" data-note-id="${n.id}">✕</button>
                </div>`;
            div.querySelector('[data-note-id]')?.addEventListener('click', async () => {
                const r = await post(cfg.deleteNoteUrl, { id: n.id });
                if (r.ok) {
                    const r2 = await get(cfg.getCardUrl, { id: currentStudentId });
                    if (r2.ok) renderNotas(r2.card.notes || []);
                }
            });
            list.appendChild(div);
        });
    }

    // --- ANEXOS ---
    function fillAnexos(card) {
        renderAnexos(card.attachments || []);

        id('mdAttTrigger')?.addEventListener('click', () => id('mdAttFile')?.click());

        id('mdAttFile')?.addEventListener('change', async function() {
            await uploadFiles(this.files);
            this.value = '';
        });

        const dropZone = id('mdAttTrigger')?.closest('.border-dashed');
        if (dropZone) {
            dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-blue-400'); });
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-blue-400'));
            dropZone.addEventListener('drop', async e => {
                e.preventDefault();
                dropZone.classList.remove('border-blue-400');
                await uploadFiles(e.dataTransfer.files);
            });
        }
    }

    async function uploadFiles(files) {
        if (!files || !files.length) return;
        const prog = id('mdAttProgress');
        if (prog) { prog.classList.remove('hidden'); prog.textContent = 'Enviando...'; }

        for (const file of files) {
            const fd = new FormData();
            fd.append('_csrf', getCsrf());
            fd.append('student_id', currentStudentId);
            fd.append('attachment', file);
            const res = await fetch(cfg.uploadAttUrl, { method: 'POST', body: fd });
            const r = await res.json().catch(() => ({}));
            if (!r.ok) toast(r.message || 'Erro no upload.', 'error');
        }

        if (prog) prog.classList.add('hidden');
        const r2 = await get(cfg.getCardUrl, { id: currentStudentId });
        if (r2.ok) renderAnexos(r2.card.attachments || []);
    }

    function renderAnexos(attachments) {
        const list = id('mdAttList');
        if (!list) return;
        list.innerHTML = '';
        if (!attachments.length) {
            list.innerHTML = '<p class="text-xs text-slate-400">Nenhum anexo.</p>';
            return;
        }
        attachments.forEach(a => {
            const div = document.createElement('div');
            div.className = 'gda-attachment-item';
            div.innerHTML = `
                <span class="text-lg">📎</span>
                <span class="gda-attachment-name">${esc(a.original_file_name)}</span>
                <a href="${cfg.downloadAttUrl}?id=${a.id}" class="gda-btn gda-btn-default gda-btn-sm" download>⬇</a>
                <button class="gda-btn gda-btn-danger gda-btn-sm" data-att-id="${a.id}">✕</button>`;
            div.querySelector('[data-att-id]')?.addEventListener('click', async () => {
                const r = await post(cfg.deleteAttUrl, { id: a.id });
                if (r.ok) {
                    const r2 = await get(cfg.getCardUrl, { id: currentStudentId });
                    if (r2.ok) renderAnexos(r2.card.attachments || []);
                }
            });
            list.appendChild(div);
        });
    }

    // --- HISTÓRICO ---
    function fillHistorico(card) {
        const list = id('mdHistoryList');
        if (!list) return;
        const history = card.history || [];
        if (!history.length) {
            list.innerHTML = '<p class="text-xs text-slate-400">Sem histórico.</p>';
            return;
        }
        history.forEach(h => {
            const div = document.createElement('div');
            div.className = 'gda-history-item';
            div.innerHTML = `
                <span class="gda-history-dot"></span>
                <div>
                    <div class="gda-history-text">${esc(h.from_name || '—')} → ${esc(h.to_name || '—')}</div>
                    <div class="gda-history-meta">${esc(h.changed_at)} — ${esc(h.user_name || '')}</div>
                    ${h.note ? `<div class="text-xs text-slate-500 mt-1">${esc(h.note)}</div>` : ''}
                </div>`;
            list.appendChild(div);
        });
    }

    // --- MEMBROS ---
    function fillMembros(card) {
        let memberIds = (card.members || []).map(m => parseInt(m.user_id));
        renderMembros(card.members || [], card.all_users || [], memberIds);

        const sel = id('mdMemberSelect');
        (card.all_users || []).forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id; opt.textContent = u.name;
            sel?.appendChild(opt);
        });

        id('mdMemberAdd')?.addEventListener('click', async () => {
            const uid = parseInt(val('mdMemberSelect'));
            if (!uid || memberIds.includes(uid)) return;
            memberIds.push(uid);
            const r = await post(cfg.setMembersUrl, { student_id: currentStudentId, user_ids: memberIds });
            if (r.ok) {
                const r2 = await get(cfg.getCardUrl, { id: currentStudentId });
                if (r2.ok) {
                    memberIds = (r2.card.members || []).map(m => parseInt(m.user_id));
                    renderMembros(r2.card.members || [], r2.card.all_users || [], memberIds);
                }
            }
        });
    }

    function renderMembros(members, allUsers, memberIds) {
        const list = id('mdMembersList');
        if (!list) return;
        list.innerHTML = '';
        if (!members.length) { list.innerHTML = '<p class="text-xs text-slate-400">Nenhum membro.</p>'; return; }
        members.forEach(m => {
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.innerHTML = `
                <span class="gda-avatar text-xs" style="width:28px;height:28px;">${esc((m.name||'?').slice(0,2).toUpperCase())}</span>
                <span class="text-sm flex-1">${esc(m.name || '')}</span>
                <button class="gda-btn gda-btn-danger gda-btn-sm" data-uid="${m.user_id}">✕</button>`;
            div.querySelector('[data-uid]')?.addEventListener('click', async () => {
                const remaining = memberIds.filter(x => x !== parseInt(m.user_id));
                const r = await post(cfg.setMembersUrl, { student_id: currentStudentId, user_ids: remaining });
                if (r.ok) {
                    const r2 = await get(cfg.getCardUrl, { id: currentStudentId });
                    if (r2.ok) {
                        const newIds = (r2.card.members || []).map(x => parseInt(x.user_id));
                        renderMembros(r2.card.members || [], r2.card.all_users || [], newIds);
                    }
                }
            });
            list.appendChild(div);
        });
    }

    // --- ETIQUETAS ---
    function fillEtiquetas(card) {
        const cardLabelIds = (card.labels || []).map(l => parseInt(l.id));
        renderEtiquetas(card.all_labels || [], cardLabelIds);
    }

    function renderEtiquetas(allLabels, cardLabelIds) {
        const list = id('mdLabelsList');
        if (!list) return;
        list.innerHTML = '';
        allLabels.forEach(lbl => {
            const active = cardLabelIds.includes(parseInt(lbl.id));
            const span = document.createElement('span');
            span.className = 'gda-label-pill cursor-pointer ' + (active ? 'ring-2 ring-offset-1 ring-white' : 'opacity-60');
            span.style.background = lbl.color;
            span.style.color = '#fff';
            span.textContent = lbl.name;
            span.addEventListener('click', async () => {
                const idx = cardLabelIds.indexOf(parseInt(lbl.id));
                if (idx === -1) cardLabelIds.push(parseInt(lbl.id));
                else cardLabelIds.splice(idx, 1);
                const r = await post(cfg.setLabelsUrl, { student_id: currentStudentId, label_ids: cardLabelIds });
                if (r.ok) renderEtiquetas(allLabels, cardLabelIds);
            });
            list.appendChild(span);
        });
        if (!allLabels.length) list.innerHTML = '<p class="text-xs text-slate-400">Nenhuma etiqueta cadastrada. Crie em Configurações.</p>';
    }

    // --- CHECKLISTS ---
    function fillChecklists(card) {
        renderChecklists(card.checklists || []);

        id('mdChecklistAdd')?.addEventListener('click', async () => {
            const title = val('mdNewChecklist').trim();
            if (!title) return;
            const r = await post(cfg.saveChecklistUrl, { student_id: currentStudentId, title });
            if (r.ok) {
                val('mdNewChecklist', '');
                refreshChecklists();
            }
        });
    }

    async function refreshChecklists() {
        const r = await get(cfg.getCardUrl, { id: currentStudentId });
        if (r.ok) renderChecklists(r.card.checklists || []);
    }

    function renderChecklists(checklists) {
        const wrap = id('mdChecklistsWrap');
        if (!wrap) return;
        wrap.innerHTML = '';
        checklists.forEach(cl => {
            const done  = (cl.items || []).filter(i => i.is_done).length;
            const total = (cl.items || []).length;
            const pct   = total ? Math.round(done / total * 100) : 0;

            const div = document.createElement('div');
            div.innerHTML = `
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-semibold text-slate-700">${esc(cl.title)}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">${done}/${total}</span>
                        <button class="gda-btn gda-btn-danger gda-btn-sm" data-del-cl="${cl.id}">✕</button>
                    </div>
                </div>
                <div class="w-full bg-slate-200 rounded h-1 mb-2">
                    <div class="bg-blue-500 h-1 rounded" style="width:${pct}%"></div>
                </div>
                <div class="flex flex-col gap-1 mb-2" id="clItems_${cl.id}"></div>
                <div class="flex gap-2">
                    <input type="text" class="gda-input text-xs flex-1 cl-item-input" data-cl-id="${cl.id}" placeholder="Novo item...">
                    <button class="gda-btn gda-btn-default gda-btn-sm cl-item-add" data-cl-id="${cl.id}">Adicionar</button>
                </div>`;

            const itemsWrap = div.querySelector(`#clItems_${cl.id}`);
            (cl.items || []).forEach(item => {
                const row = document.createElement('div');
                row.className = 'gda-checklist-item';
                row.innerHTML = `
                    <input type="checkbox" id="cli_${item.id}" ${item.is_done ? 'checked' : ''}>
                    <label for="cli_${item.id}">${esc(item.text)}</label>
                    <button class="gda-btn gda-btn-danger gda-btn-sm" data-del-item="${item.id}">✕</button>`;
                row.querySelector(`#cli_${item.id}`)?.addEventListener('change', async function() {
                    await post(cfg.toggleItemUrl, { id: item.id, done: this.checked ? 1 : 0 });
                    refreshChecklists();
                });
                row.querySelector(`[data-del-item="${item.id}"]`)?.addEventListener('click', async () => {
                    await post(cfg.delItemUrl, { id: item.id });
                    refreshChecklists();
                });
                itemsWrap.appendChild(row);
            });

            div.querySelector(`[data-del-cl="${cl.id}"]`)?.addEventListener('click', async () => {
                if (!confirm('Remover esta checklist?')) return;
                await post(cfg.delChecklistUrl, { id: cl.id });
                refreshChecklists();
            });

            div.querySelector(`.cl-item-add[data-cl-id="${cl.id}"]`)?.addEventListener('click', async () => {
                const inp = div.querySelector(`.cl-item-input[data-cl-id="${cl.id}"]`);
                const text = inp.value.trim();
                if (!text) return;
                await post(cfg.saveItemUrl, { checklist_id: cl.id, text });
                inp.value = '';
                refreshChecklists();
            });

            div.querySelector(`.cl-item-input`)?.addEventListener('keydown', async e => {
                if (e.key !== 'Enter') return;
                e.target.closest('div').querySelector('.cl-item-add')?.click();
            });

            wrap.appendChild(div);
        });

        if (!checklists.length) {
            wrap.innerHTML = '<p class="text-xs text-slate-400">Nenhuma checklist. Crie abaixo.</p>';
        }
    }

    // --- CAMPOS CUSTOMIZADOS ---
    function fillCampos(card) {
        const wrap = id('mdCfWrap');
        if (!wrap) return;
        const fields = card.custom_fields || [];
        fields.forEach(f => {
            const row = document.createElement('div');
            row.className = 'gda-cf-row';
            let input = '';
            if (f.field_type === 'checkbox') {
                input = `<input type="checkbox" id="cf_${f.id}" class="gda-input w-auto" ${f.value == '1' ? 'checked' : ''} data-cf="${f.id}">`;
            } else if (f.field_type === 'select') {
                const opts = (f.options_json ? JSON.parse(f.options_json) : []);
                input = `<select id="cf_${f.id}" class="gda-input gda-select text-sm" data-cf="${f.id}">
                    <option value="">—</option>
                    ${opts.map(o => `<option value="${esc(o)}" ${f.value === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}
                </select>`;
            } else {
                input = `<input type="${f.field_type === 'date' ? 'date' : f.field_type === 'number' ? 'number' : 'text'}"
                    id="cf_${f.id}" class="gda-input text-sm" value="${esc(f.value || '')}" data-cf="${f.id}">`;
            }
            row.innerHTML = `<span class="gda-cf-label">${esc(f.name)}</span><div class="gda-cf-value">${input}</div>`;
            wrap.appendChild(row);
        });

        if (!fields.length) wrap.innerHTML = '<p class="text-xs text-slate-400">Nenhum campo customizado. Crie em Configurações.</p>';

        id('mdCfSave')?.addEventListener('click', async () => {
            for (const f of fields) {
                const el = id('cf_' + f.id);
                if (!el) continue;
                const value = f.field_type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
                await post(cfg.saveCfValueUrl, { student_id: currentStudentId, field_id: f.id, value });
            }
            toast('Campos salvos.', 'success');
        });
    }

    // --- TEMPLATES ---
    function fillTemplates(card) {
        const sel = id('mdTplSelect');
        (card.all_templates || []).forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id; opt.textContent = t.name;
            sel?.appendChild(opt);
        });

        id('mdTplApply')?.addEventListener('click', async () => {
            const tid = parseInt(val('mdTplSelect'));
            if (!tid) return;
            if (!confirm('Aplicar template? Isso adicionará checklists e meta ao card.')) return;
            const r = await post(cfg.applyTemplateUrl, { student_id: currentStudentId, template_id: tid });
            if (r.ok) {
                toast('Template aplicado.', 'success');
                openModal(currentStudentId);
            } else toast(r.message || 'Erro.', 'error');
        });
    }

    // --------------------------------------------------------
    // HELPERS
    // --------------------------------------------------------
    function id(n)        { return modalBody.querySelector('#' + n) || document.getElementById(n); }
    function set(n, v)    { const el = id(n); if (el) el.textContent = v || ''; }
    function val(n, v)    {
        const el = id(n);
        if (!el) return '';
        if (v !== undefined) { el.value = v; return v; }
        return el.value;
    }
    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // --------------------------------------------------------
    // INIT
    // --------------------------------------------------------
    initDragDrop();
    initQuickAdd();

})();
