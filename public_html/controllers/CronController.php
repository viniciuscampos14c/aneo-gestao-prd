<?php

class CronController extends BaseController
{
    private CronRunner $runner;

    public function __construct()
    {
        $this->runner = new CronRunner();
    }

    /** Painel principal — lista todos os jobs com status. */
    public function index(): void
    {
        require_auth();
        require_permission('dashboard');   // apenas usuários logados; admin vê pelo menu

        $jobs = $this->runner->listJobs();
        $token = trim((string) config('cron.secret_token', ''));
        $baseUrl = rtrim((string) config('app.base_url', ''), '/');

        $this->render('cron/index', [
            'title'   => 'Cron Jobs',
            'jobs'    => $jobs,
            'token'   => $token,
            'baseUrl' => $baseUrl,
        ]);
    }

    /** Executa um job via AJAX — retorna JSON. */
    public function runJob(): void
    {
        require_auth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Metodo inválido.'], 405);
        }

        $jobKey = trim((string) ($_POST['job'] ?? ''));
        if ($jobKey === '') {
            $this->json(['ok' => false, 'message' => 'job_key não informado.'], 400);
        }

        $result = $this->runner->run($jobKey);
        $this->json($result);
    }

    /** Lista logs de um job específico — retorna JSON. */
    public function logs(): void
    {
        require_auth();

        $jobKey = trim((string) ($_GET['job'] ?? ''));
        $limit  = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        if ($jobKey === '') {
            $this->json(['ok' => false, 'message' => 'job_key não informado.'], 400);
        }

        $logs = $this->runner->logs($jobKey, $limit, $offset);
        $this->json(['ok' => true, 'data' => $logs]);
    }

    /** Ativa ou desativa um job. */
    public function toggle(): void
    {
        require_auth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Metodo inválido.'], 405);
        }

        $jobKey  = trim((string) ($_POST['job'] ?? ''));
        $enabled = (bool) (int) ($_POST['enabled'] ?? 1);

        if ($jobKey === '') {
            $this->json(['ok' => false, 'message' => 'job_key não informado.'], 400);
        }

        $this->runner->setEnabled($jobKey, $enabled);
        $this->json(['ok' => true, 'message' => 'Status atualizado.']);
    }
}
