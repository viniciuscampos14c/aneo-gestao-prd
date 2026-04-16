<div class="mb-6 flex items-center gap-3">
    <a href="<?= route('api-management'); ?>" class="text-slate-400 hover:text-slate-600">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
    </a>
    <h2 class="text-xl font-bold text-slate-800">Manual da API</h2>
</div>

<div class="max-w-4xl space-y-8">

    <!-- Visão geral -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-slate-800">Visão Geral</h3>
        <p class="text-sm text-slate-600">A API REST do ANEO Gestão permite integrar sistemas externos como n8n, CRMs e outras ferramentas. Todas as requisições exigem um <strong>Bearer Token</strong> no header <code class="rounded bg-slate-100 px-1 font-mono text-xs">Authorization</code>.</p>

        <div class="mt-4 rounded-lg bg-slate-900 p-4">
            <p class="mb-1 text-xs font-semibold uppercase text-slate-400">Base URL</p>
            <code class="font-mono text-sm text-cyan-300">https://erp-hml.aneobrasil.com.br/api.php</code>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                <p class="text-xs font-semibold text-slate-500">Autenticação</p>
                <p class="mt-1 font-mono text-xs text-slate-700">Authorization: Bearer &lt;token&gt;</p>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                <p class="text-xs font-semibold text-slate-500">Content-Type (POST/PUT)</p>
                <p class="mt-1 font-mono text-xs text-slate-700">application/json</p>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                <p class="text-xs font-semibold text-slate-500">Resposta</p>
                <p class="mt-1 font-mono text-xs text-slate-700">application/json; charset=utf-8</p>
            </div>
        </div>
    </div>

    <!-- Formato de resposta -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-slate-800">Formato de Resposta</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="mb-2 text-xs font-semibold text-slate-500">Sucesso (lista)</p>
                <pre class="rounded-lg bg-slate-900 p-4 text-xs text-emerald-300"><code>{
  "ok": true,
  "data": [ {...}, {...} ],
  "meta": {
    "total": 150,
    "page": 1,
    "per_page": 50,
    "pages": 3
  }
}</code></pre>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold text-slate-500">Erro</p>
                <pre class="rounded-lg bg-slate-900 p-4 text-xs text-rose-300"><code>{
  "ok": false,
  "message": "Token invalido ou expirado.",
  "code": 401
}</code></pre>
            </div>
        </div>

        <div class="mt-4">
            <p class="mb-2 text-xs font-semibold text-slate-500">Códigos de status HTTP</p>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">200 OK</span>
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">201 Criado</span>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">401 Não autenticado</span>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">403 Sem permissão</span>
                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-rose-700">404 Não encontrado</span>
                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-rose-700">422 Dados inválidos</span>
                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-rose-700">500 Erro interno</span>
            </div>
        </div>
    </div>

    <!-- Parâmetros comuns -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-slate-800">Parâmetros de Listagem</h3>
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="pb-2 pr-4">Parâmetro</th>
                    <th class="pb-2 pr-4">Tipo</th>
                    <th class="pb-2">Descrição</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-600">
                <tr><td class="py-2 pr-4 font-mono text-xs">q</td><td class="py-2 pr-4">string</td><td class="py-2">Busca por nome, email, telefone</td></tr>
                <tr><td class="py-2 pr-4 font-mono text-xs">page</td><td class="py-2 pr-4">int</td><td class="py-2">Página (padrão: 1)</td></tr>
                <tr><td class="py-2 pr-4 font-mono text-xs">per_page</td><td class="py-2 pr-4">int</td><td class="py-2">Itens por página (max: 200, padrão: 50)</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Recursos -->
    <?php
    $endpointDefs = [
        'students' => [
            'label' => 'Alunos',
            'endpoints' => [
                ['method' => 'GET',    'path' => 'api.php?r=students',       'cap' => 'search', 'desc' => 'Listar alunos (paginado). Filtros: q, is_active, kanban_status_id'],
                ['method' => 'GET',    'path' => 'api.php?r=students&id=1',  'cap' => 'get',    'desc' => 'Buscar aluno por ID'],
                ['method' => 'POST',   'path' => 'api.php?r=students',       'cap' => 'create', 'desc' => 'Criar aluno. Campo obrigatório: full_name'],
                ['method' => 'PUT',    'path' => 'api.php?r=students&id=1',  'cap' => 'update', 'desc' => 'Atualizar aluno por ID'],
                ['method' => 'DELETE', 'path' => 'api.php?r=students&id=1',  'cap' => 'delete', 'desc' => 'Remover aluno por ID'],
            ],
        ],
        'leads' => [
            'label' => 'Leads',
            'endpoints' => [
                ['method' => 'GET',    'path' => 'api.php?r=leads',          'cap' => 'search', 'desc' => 'Listar leads. Filtros: q, status_id'],
                ['method' => 'GET',    'path' => 'api.php?r=leads&id=1',     'cap' => 'get',    'desc' => 'Buscar lead por ID'],
                ['method' => 'POST',   'path' => 'api.php?r=leads',          'cap' => 'create', 'desc' => 'Criar lead. Campo obrigatório: full_name'],
                ['method' => 'PUT',    'path' => 'api.php?r=leads&id=1',     'cap' => 'update', 'desc' => 'Atualizar lead por ID'],
                ['method' => 'DELETE', 'path' => 'api.php?r=leads&id=1',     'cap' => 'delete', 'desc' => 'Remover lead por ID'],
            ],
        ],
        'invoices' => [
            'label' => 'Faturas',
            'endpoints' => [
                ['method' => 'GET',    'path' => 'api.php?r=invoices',       'cap' => 'search', 'desc' => 'Listar faturas. Filtros: q, status, student_id'],
                ['method' => 'GET',    'path' => 'api.php?r=invoices&id=1',  'cap' => 'get',    'desc' => 'Buscar fatura por ID'],
                ['method' => 'POST',   'path' => 'api.php?r=invoices',       'cap' => 'create', 'desc' => 'Criar fatura. Campos obrigatórios: student_id, due_date, amount'],
                ['method' => 'DELETE', 'path' => 'api.php?r=invoices&id=1',  'cap' => 'delete', 'desc' => 'Remover fatura por ID'],
            ],
        ],
        'courses' => [
            'label' => 'Cursos',
            'endpoints' => [
                ['method' => 'GET', 'path' => 'api.php?r=courses',       'cap' => 'search', 'desc' => 'Listar cursos. Filtros: q, category_id, is_active'],
                ['method' => 'GET', 'path' => 'api.php?r=courses&id=1',  'cap' => 'get',    'desc' => 'Buscar curso por ID'],
            ],
        ],
        'users' => [
            'label' => 'Usuários',
            'endpoints' => [
                ['method' => 'GET', 'path' => 'api.php?r=users',       'cap' => 'search', 'desc' => 'Listar usuários do sistema. Filtros: q, role, is_active'],
                ['method' => 'GET', 'path' => 'api.php?r=users&id=1',  'cap' => 'get',    'desc' => 'Buscar usuário por ID'],
            ],
        ],
        'tickets' => [
            'label' => 'Chamados',
            'endpoints' => [
                ['method' => 'GET',  'path' => 'api.php?r=tickets',       'cap' => 'search', 'desc' => 'Listar chamados. Filtros: q, status, priority'],
                ['method' => 'GET',  'path' => 'api.php?r=tickets&id=1',  'cap' => 'get',    'desc' => 'Buscar chamado por ID'],
                ['method' => 'POST', 'path' => 'api.php?r=tickets',       'cap' => 'create', 'desc' => 'Criar chamado. Campos obrigatórios: subject, description, requester_name'],
            ],
        ],
    ];

    $methodColors = [
        'GET'    => 'bg-emerald-100 text-emerald-700',
        'POST'   => 'bg-blue-100 text-blue-700',
        'PUT'    => 'bg-amber-100 text-amber-700',
        'DELETE' => 'bg-rose-100 text-rose-700',
    ];
    ?>

    <?php foreach ($endpointDefs as $resource => $def): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-slate-800"><?= e($def['label']); ?></h3>
            <div class="space-y-3">
                <?php foreach ($def['endpoints'] as $ep): ?>
                    <div class="flex flex-wrap items-start gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <span class="shrink-0 rounded px-2 py-0.5 text-xs font-bold font-mono <?= $methodColors[$ep['method']]; ?>">
                            <?= $ep['method']; ?>
                        </span>
                        <code class="shrink-0 text-xs text-slate-700"><?= e($ep['path']); ?></code>
                        <span class="text-xs text-slate-500"><?= e($ep['desc']); ?></span>
                        <span class="ml-auto shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] text-slate-500">perm: <?= e($resource . '.' . $ep['cap']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Exemplo curl completo -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-3 text-base font-semibold text-slate-800">Exemplos</h3>
        <div class="space-y-4">
            <div>
                <p class="mb-1 text-xs font-semibold text-slate-500">Listar alunos (página 2, 10 por página)</p>
                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-cyan-300"><code>curl -H "Authorization: Bearer SEU_TOKEN" \
     "https://erp-hml.aneobrasil.com.br/api.php?r=students&page=2&per_page=10&q=maria"</code></pre>
            </div>
            <div>
                <p class="mb-1 text-xs font-semibold text-slate-500">Criar lead via JSON</p>
                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-cyan-300"><code>curl -X POST \
     -H "Authorization: Bearer SEU_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"full_name":"João Silva","email":"joao@email.com","phone":"11999999999","source":"site"}' \
     "https://erp-hml.aneobrasil.com.br/api.php?r=leads"</code></pre>
            </div>
            <div>
                <p class="mb-1 text-xs font-semibold text-slate-500">Criar fatura</p>
                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-cyan-300"><code>curl -X POST \
     -H "Authorization: Bearer SEU_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"student_id":42,"due_date":"2026-05-10","amount":350.00,"project_name":"Mensalidade Maio"}' \
     "https://erp-hml.aneobrasil.com.br/api.php?r=invoices"</code></pre>
            </div>
        </div>
    </div>

</div>
