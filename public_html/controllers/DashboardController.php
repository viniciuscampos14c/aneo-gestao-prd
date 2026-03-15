<?php

class DashboardController extends BaseController
{
    private DashboardModel $model;
    private AcademicCalendarModel $calendar;
    private FinanceNotificationModel $financeNotifications;

    public function __construct()
    {
        $this->model = new DashboardModel();
        $this->calendar = new AcademicCalendarModel();
        $this->financeNotifications = new FinanceNotificationModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('dashboard');

        $this->calendar->processAutomaticReminders();
        $this->financeNotifications->dispatchDueNotifications((int) (current_company_id() ?? 0), date('Y-m-d'));
        $metrics = $this->model->metrics();

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'metrics' => $metrics,
        ]);
    }
}
