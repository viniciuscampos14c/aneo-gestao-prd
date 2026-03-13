<?php

class DashboardController extends BaseController
{
    private DashboardModel $model;
    private AcademicCalendarModel $calendar;

    public function __construct()
    {
        $this->model = new DashboardModel();
        $this->calendar = new AcademicCalendarModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('dashboard');

        $this->calendar->processAutomaticReminders();
        $metrics = $this->model->metrics();

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'metrics' => $metrics,
        ]);
    }
}
