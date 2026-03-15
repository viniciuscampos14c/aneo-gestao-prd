<?php

class SystemLogController extends BaseController
{
    private SystemAuditLogModel $logs;
    private CompanyModel $companies;

    public function __construct()
    {
        $this->logs = new SystemAuditLogModel();
        $this->companies = new CompanyModel();
    }

    public function index(): void
    {
        require_admin();

        $filters = [
            'q' => trim((string) request('q', '')),
            'module' => trim((string) request('module', '')),
            'user_role' => trim((string) request('user_role', '')),
            'company_id' => (int) request('company_id', 0),
            'start_date' => trim((string) request('start_date', '')),
            'end_date' => trim((string) request('end_date', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->logs->list($filters, $perPage, $page);

        $this->render('system/logs', [
            'title' => 'Logs de Sistema',
            'filters' => $filters,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'modules' => $this->logs->modules(),
            'roles' => [
                'admin' => 'Administrador',
                'professor' => 'Professor',
                'suporte' => 'Suporte',
            ],
            'companyOptions' => $this->companies->activeCompanies(),
            'logsAvailable' => $this->logs->tableExists(),
        ]);
    }
}
