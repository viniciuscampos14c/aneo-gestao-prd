<div class="grid w-full gap-10 lg:grid-cols-2">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center gap-4">
            <img src="assets/brand/aneo-wordmark-transparente-branco.svg?v=20260512-brand-kit-v1" alt="Logo ANEO" class="h-12 w-auto">
            <p class="inline-flex rounded-full border border-cyan-500/40 bg-cyan-500/10 px-3 py-1 text-xs uppercase tracking-[0.2em] text-cyan-300">Plataforma Integrada</p>
        </div>
        <h1 class="text-4xl font-semibold leading-tight">Gestao Educacional + CRM + Financeiro em um unico painel.</h1>
        <p class="text-slate-300">Acesse para administrar alunos, funil comercial, faturas, cursos e operacoes da ANEO.</p>
        <div class="grid gap-3 text-sm text-slate-300 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">Kanban financeiro com arrastar e soltar</div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">Pipeline de leads customizavel</div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">Faturas, pagamentos e recorrencia</div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">Cursos EAD e matrículas</div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-cyan-900/20">
        <img src="assets/brand/aneo-wordmark-transparente-branco.svg?v=20260512-brand-kit-v1" alt="Logo ANEO" class="mb-6 h-8 w-auto">
        <h2 class="mb-6 text-2xl font-semibold">Entrar no sistema</h2>

        <?php if ($msg = flash('error')): ?>
            <div class="mb-4 rounded-lg border border-rose-400/40 bg-rose-400/10 px-4 py-3 text-sm text-rose-200"><?= e($msg); ?></div>
        <?php endif; ?>

        <?php if ($msg = flash('success')): ?>
            <div class="mb-4 rounded-lg border border-emerald-400/40 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200"><?= e($msg); ?></div>
        <?php endif; ?>

        <form method="post" action="<?= route('login'); ?>" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <label class="block">
                <span class="mb-1 block text-sm text-slate-300">Usuário ou Email</span>
                <input type="text" name="login" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-cyan-400" placeholder="admin@aneo.local">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm text-slate-300">Senha</span>
                <input type="password" name="password" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-cyan-400" placeholder="********">
            </label>

            <button type="submit" class="w-full rounded-lg bg-cyan-600 px-4 py-2 font-medium text-white hover:bg-cyan-500">Entrar</button>
        </form>

        <p class="mt-4 text-xs text-slate-400">Seed padrao: usuário <code>admin</code> / senha <code>admin123</code>.</p>
        <a href="<?= route('student/login'); ?>" class="mt-3 inline-flex text-xs text-cyan-300 hover:text-cyan-200">Entrar no portal do aluno</a>
    </section>
</div>
