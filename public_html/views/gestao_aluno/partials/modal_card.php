<div class="gda-modal-backdrop" id="gdaModalBackdrop">
    <div class="gda-modal gda-modal-single" id="gdaModal">

        <!-- Header -->
        <div class="gda-modal-header">
            <div class="flex-1 min-w-0">
                <div id="gdaModalTitle" class="gda-modal-title">Carregando...</div>
                <div class="flex items-center gap-2 mt-1">
                    <span id="gdaModalColBadge" class="gda-modal-col-badge text-xs" style="display:none"></span>
                    <a id="gdaModalStudentLink" href="#" target="_blank" class="text-xs text-blue-600 hover:underline" style="display:none">Ver ficha do aluno</a>
                </div>
            </div>
            <button class="gda-modal-close" id="gdaModalClose" title="Fechar">&times;</button>
        </div>

        <!-- Body -->
        <div class="gda-modal-body" id="gdaModalBody">
            <div class="flex items-center justify-center py-12">
                <span class="gda-spinner"></span>
            </div>
        </div>

    </div>
</div>

<!-- Templates dos paineis (clonados via JS) -->
<template id="gdaTplModalContent">
<div>

    <!-- Painel: Informacoes -->
    <div class="gda-tab-panel active" data-panel="info">
        <h4 class="gda-section-title">Informacoes</h4>
        <div class="grid gap-3 text-sm md:grid-cols-2">
            <div><span class="text-slate-400 text-xs">Nome</span><br><strong id="mdInfoNome"></strong></div>
            <div><span class="text-slate-400 text-xs">CPF</span><br><span id="mdInfoCpf" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">E-mail</span><br><span id="mdInfoEmail" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">Telefone</span><br><span id="mdInfoPhone" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">Cidade / UF</span><br><span id="mdInfoCity" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">Coluna atual</span><br><span id="mdInfoCol" class="text-slate-700"></span></div>
        </div>
    </div>

    <!-- Painel: Financeiro -->
    <div class="gda-tab-panel gda-panel-featured" data-panel="financeiro">
        <div class="gda-panel-head">
            <div>
                <h4 class="gda-section-title">Financeiro</h4>
                <p class="gda-panel-subtitle">Ultimas 3 parcelas com vencimento ate hoje.</p>
            </div>
            <span id="mdFinanceStatus" class="gda-finance-status">Sem faturas</span>
        </div>
        <div id="mdFinanceSummary" class="gda-finance-summary"></div>
        <div id="mdFinanceList" class="gda-finance-list"></div>
    </div>

    <!-- Painel: Meta -->
    <div class="gda-tab-panel" data-panel="meta">
        <h4 class="gda-section-title">Meta do card</h4>
        <div class="flex flex-col gap-4">
            <div>
                <label class="text-xs text-slate-500 font-semibold block mb-1">Prioridade</label>
                <select id="mdMetaPriority" class="gda-input gda-select text-sm">
                    <option value="none">Nenhuma</option>
                    <option value="low">Baixa</option>
                    <option value="medium">Media</option>
                    <option value="high">Alta</option>
                    <option value="critical">Critica</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500 font-semibold block mb-1">Prazo</label>
                <input type="date" id="mdMetaDue" class="gda-input text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500 font-semibold block mb-1">Cor de capa</label>
                <input type="color" id="mdMetaCover" class="h-9 w-20 rounded border border-slate-200 cursor-pointer">
                <button id="mdMetaCoverClear" class="gda-btn gda-btn-default gda-btn-sm ml-2">Remover</button>
            </div>
            <div>
                <label class="text-xs text-slate-500 font-semibold block mb-1">Responsavel</label>
                <select id="mdMetaAssigned" class="gda-input gda-select text-sm">
                    <option value="">- Nenhum -</option>
                </select>
            </div>
            <div>
                <button id="mdMetaSave" class="gda-btn gda-btn-primary text-sm">Salvar Meta</button>
            </div>
            <hr class="border-slate-200">
            <div>
                <button id="mdArchive" class="gda-btn gda-btn-danger text-sm">Arquivar card</button>
            </div>
        </div>
    </div>

    <!-- Painel: Descricao -->
    <div class="gda-tab-panel" data-panel="descricao">
        <h4 class="gda-section-title">Descricao</h4>
        <textarea id="mdDesc" rows="8" class="gda-input text-sm w-full" placeholder="Escreva uma descricao para o aluno..."></textarea>
        <div class="mt-2">
            <button id="mdDescSave" class="gda-btn gda-btn-primary text-sm">Salvar Descricao</button>
        </div>
    </div>

    <!-- Painel: Notas -->
    <div class="gda-tab-panel gda-panel-followup" data-panel="notas">
        <div class="gda-panel-head">
            <div>
                <h4 class="gda-section-title">Follow-up</h4>
                <p class="gda-panel-subtitle">Registre os ultimos contatos e combinados com o aluno.</p>
            </div>
        </div>
        <div id="mdNotesList" class="mb-4 flex flex-col gap-2"></div>
        <div class="flex gap-2">
            <input type="text" id="mdNoteInput" class="gda-input text-sm flex-1" placeholder="Registrar novo follow-up...">
            <button id="mdNoteSave" class="gda-btn gda-btn-primary text-sm">Registrar</button>
        </div>
    </div>

    <!-- Painel: Anexos -->
    <div class="gda-tab-panel" data-panel="anexos">
        <h4 class="gda-section-title">Anexos</h4>
        <div id="mdAttList" class="mb-4 flex flex-col gap-2"></div>
        <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center">
            <input type="file" id="mdAttFile" class="hidden" multiple>
            <button id="mdAttTrigger" class="gda-btn gda-btn-default text-sm mx-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
                Selecionar arquivos
            </button>
            <p class="text-xs text-slate-400 mt-2">Ou arraste arquivos aqui</p>
            <div id="mdAttProgress" class="hidden mt-2 text-xs text-slate-500"></div>
        </div>
    </div>

    <!-- Painel: Historico -->
    <div class="gda-tab-panel" data-panel="historico">
        <h4 class="gda-section-title">Historico</h4>
        <div id="mdHistoryList" class="flex flex-col"></div>
    </div>

    <!-- Painel: Membros -->
    <div class="gda-tab-panel" data-panel="membros">
        <h4 class="gda-section-title">Membros</h4>
        <p class="text-xs text-slate-400 mb-3">Usuarios vinculados a este card:</p>
        <div id="mdMembersList" class="flex flex-col gap-2 mb-4"></div>
        <div>
            <label class="text-xs text-slate-500 font-semibold block mb-1">Adicionar membro</label>
            <div class="flex gap-2">
                <select id="mdMemberSelect" class="gda-input gda-select text-sm flex-1">
                    <option value="">Selecionar usuario...</option>
                </select>
                <button id="mdMemberAdd" class="gda-btn gda-btn-primary text-sm">Adicionar</button>
            </div>
        </div>
    </div>

    <!-- Painel: Etiquetas -->
    <div class="gda-tab-panel" data-panel="etiquetas">
        <h4 class="gda-section-title">Etiquetas</h4>
        <div id="mdLabelsList" class="flex flex-wrap gap-2 mb-4"></div>
        <p class="text-xs text-slate-400">Clique em uma etiqueta para adicionar ou remover do card.</p>
    </div>

    <!-- Painel: Checklists -->
    <div class="gda-tab-panel" data-panel="checklists">
        <h4 class="gda-section-title">Checklists</h4>
        <div id="mdChecklistsWrap" class="flex flex-col gap-4 mb-4"></div>
        <div class="flex gap-2 mt-2">
            <input type="text" id="mdNewChecklist" class="gda-input text-sm flex-1" placeholder="Nome da checklist...">
            <button id="mdChecklistAdd" class="gda-btn gda-btn-primary text-sm">Criar</button>
        </div>
    </div>

    <!-- Painel: Campos customizados -->
    <div class="gda-tab-panel" data-panel="campos">
        <h4 class="gda-section-title">Campos customizados</h4>
        <div id="mdCfWrap" class="flex flex-col gap-3"></div>
        <div class="mt-2">
            <button id="mdCfSave" class="gda-btn gda-btn-primary text-sm">Salvar Campos</button>
        </div>
    </div>

    <!-- Painel: Templates -->
    <div class="gda-tab-panel" data-panel="templates">
        <h4 class="gda-section-title">Templates</h4>
        <p class="text-xs text-slate-400 mb-3">Aplicar um template popula checklists e prioridade no card.</p>
        <div class="flex gap-2">
            <select id="mdTplSelect" class="gda-input gda-select text-sm flex-1">
                <option value="">Selecionar template...</option>
            </select>
            <button id="mdTplApply" class="gda-btn gda-btn-primary text-sm">Aplicar</button>
        </div>
    </div>

</div>
</template>
