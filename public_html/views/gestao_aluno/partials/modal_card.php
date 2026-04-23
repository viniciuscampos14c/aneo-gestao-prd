<div class="gda-modal-backdrop" id="gdaModalBackdrop">
    <div class="gda-modal" id="gdaModal">

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

        <!-- Tabs -->
        <div class="gda-modal-tabs">
            <button class="gda-modal-tab active" data-tab="info">Informações</button>
            <button class="gda-modal-tab" data-tab="meta">Meta</button>
            <button class="gda-modal-tab" data-tab="descricao">Descrição</button>
            <button class="gda-modal-tab" data-tab="notas">Notas</button>
            <button class="gda-modal-tab" data-tab="anexos">Anexos</button>
            <button class="gda-modal-tab" data-tab="historico">Histórico</button>
            <button class="gda-modal-tab" data-tab="membros">Membros</button>
            <button class="gda-modal-tab" data-tab="etiquetas">Etiquetas</button>
            <button class="gda-modal-tab" data-tab="checklists">Checklists</button>
            <button class="gda-modal-tab" data-tab="campos">Campos</button>
            <button class="gda-modal-tab" data-tab="templates">Templates</button>
        </div>

        <!-- Body -->
        <div class="gda-modal-body" id="gdaModalBody">
            <div class="flex items-center justify-center py-12">
                <span class="gda-spinner"></span>
            </div>
        </div>

    </div>
</div>

<!-- Templates dos painéis (clonados via JS) -->
<template id="gdaTplModalContent">
<div>

    <!-- TAB: Informações -->
    <div class="gda-tab-panel active" data-panel="info">
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-slate-400 text-xs">Nome</span><br><strong id="mdInfoNome"></strong></div>
            <div><span class="text-slate-400 text-xs">CPF</span><br><span id="mdInfoCpf" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">E-mail</span><br><span id="mdInfoEmail" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">Telefone</span><br><span id="mdInfoPhone" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">Cidade / UF</span><br><span id="mdInfoCity" class="text-slate-700"></span></div>
            <div><span class="text-slate-400 text-xs">Coluna atual</span><br><span id="mdInfoCol" class="text-slate-700"></span></div>
        </div>
    </div>

    <!-- TAB: Meta -->
    <div class="gda-tab-panel" data-panel="meta">
        <div class="flex flex-col gap-4">
            <div>
                <label class="text-xs text-slate-500 font-semibold block mb-1">Prioridade</label>
                <select id="mdMetaPriority" class="gda-input gda-select text-sm">
                    <option value="none">Nenhuma</option>
                    <option value="low">Baixa</option>
                    <option value="medium">Média</option>
                    <option value="high">Alta</option>
                    <option value="critical">Crítica</option>
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
                <label class="text-xs text-slate-500 font-semibold block mb-1">Responsável</label>
                <select id="mdMetaAssigned" class="gda-input gda-select text-sm">
                    <option value="">— Nenhum —</option>
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

    <!-- TAB: Descrição -->
    <div class="gda-tab-panel" data-panel="descricao">
        <textarea id="mdDesc" rows="8" class="gda-input text-sm w-full" placeholder="Escreva uma descrição para o aluno..."></textarea>
        <div class="mt-2">
            <button id="mdDescSave" class="gda-btn gda-btn-primary text-sm">Salvar Descrição</button>
        </div>
    </div>

    <!-- TAB: Notas -->
    <div class="gda-tab-panel" data-panel="notas">
        <div id="mdNotesList" class="mb-4 flex flex-col gap-2"></div>
        <div class="flex gap-2">
            <input type="text" id="mdNoteInput" class="gda-input text-sm flex-1" placeholder="Adicionar nota...">
            <button id="mdNoteSave" class="gda-btn gda-btn-primary text-sm">Adicionar</button>
        </div>
    </div>

    <!-- TAB: Anexos -->
    <div class="gda-tab-panel" data-panel="anexos">
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

    <!-- TAB: Histórico -->
    <div class="gda-tab-panel" data-panel="historico">
        <div id="mdHistoryList" class="flex flex-col"></div>
    </div>

    <!-- TAB: Membros -->
    <div class="gda-tab-panel" data-panel="membros">
        <p class="text-xs text-slate-400 mb-3">Usuários vinculados a este card:</p>
        <div id="mdMembersList" class="flex flex-col gap-2 mb-4"></div>
        <div>
            <label class="text-xs text-slate-500 font-semibold block mb-1">Adicionar membro</label>
            <div class="flex gap-2">
                <select id="mdMemberSelect" class="gda-input gda-select text-sm flex-1">
                    <option value="">Selecionar usuário...</option>
                </select>
                <button id="mdMemberAdd" class="gda-btn gda-btn-primary text-sm">Adicionar</button>
            </div>
        </div>
    </div>

    <!-- TAB: Etiquetas -->
    <div class="gda-tab-panel" data-panel="etiquetas">
        <div id="mdLabelsList" class="flex flex-wrap gap-2 mb-4"></div>
        <p class="text-xs text-slate-400">Clique em uma etiqueta para adicionar ou remover do card.</p>
    </div>

    <!-- TAB: Checklists -->
    <div class="gda-tab-panel" data-panel="checklists">
        <div id="mdChecklistsWrap" class="flex flex-col gap-4 mb-4"></div>
        <div class="flex gap-2 mt-2">
            <input type="text" id="mdNewChecklist" class="gda-input text-sm flex-1" placeholder="Nome da checklist...">
            <button id="mdChecklistAdd" class="gda-btn gda-btn-primary text-sm">Criar</button>
        </div>
    </div>

    <!-- TAB: Campos Customizados -->
    <div class="gda-tab-panel" data-panel="campos">
        <div id="mdCfWrap" class="flex flex-col gap-3"></div>
        <div class="mt-2">
            <button id="mdCfSave" class="gda-btn gda-btn-primary text-sm">Salvar Campos</button>
        </div>
    </div>

    <!-- TAB: Templates -->
    <div class="gda-tab-panel" data-panel="templates">
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
