<?php

class DashboardController extends BaseController
{
    private DashboardModel $model;
    private StudentModel $students;
    private AcademicCalendarModel $calendar;
    private FinanceNotificationModel $financeNotifications;

    public function __construct()
    {
        $this->model = new DashboardModel();
        $this->students = new StudentModel();
        $this->calendar = new AcademicCalendarModel();
        $this->financeNotifications = new FinanceNotificationModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('dashboard');

        if (is_professor()) {
            $this->render('dashboard/professor', [
                'title' => 'Inicio do Professor',
                'studentStats' => $this->students->stats(),
            ]);
            return;
        }

        $this->calendar->processAutomaticReminders();
        $this->financeNotifications->dispatchDueNotifications((int) (current_company_id() ?? 0), date('Y-m-d'));
        $metrics = $this->model->metrics();

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'metrics' => $metrics,
        ]);
    }
}
