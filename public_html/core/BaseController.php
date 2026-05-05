<?php

abstract class BaseController
{
    protected function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        if ($layout === 'layouts/app' && current_user()) {
            try {
                $tickets = new SupportTicketModel();
                $data['mobileNegotiationAlerts'] = $tickets->latestMobileNegotiationAlerts(5);
                $data['mobileNegotiationAlertCount'] = $tickets->countOpenMobileNegotiations();
            } catch (Throwable $e) {
                $data['mobileNegotiationAlerts'] = [];
                $data['mobileNegotiationAlertCount'] = 0;
            }
        }

        if ($layout === 'layouts/student' && current_student()) {
            try {
                $tickets = new SupportTicketModel();
                $portal = new StudentPortalModel();
                $student = current_student();
                $studentId = (int) ($student['id'] ?? 0);
                $companyId = (int) ($student['company_id'] ?? 0);
                $studentEmail = trim((string) ($student['email'] ?? ''));

                if ($tickets->featureAvailable() && $studentId > 0 && $companyId > 0) {
                    $rows = $tickets->listStudentTickets(
                        $companyId,
                        $studentId,
                        $studentEmail,
                        ['status' => 'pending'],
                        5,
                        1
                    );
                    $stats = $tickets->studentStats($companyId, $studentId, $studentEmail);
                    $data['studentTicketAlerts'] = is_array($rows['rows'] ?? null) ? $rows['rows'] : [];
                    $data['studentTicketAlertCount'] = (int) ($stats['open'] ?? 0) + (int) ($stats['in_progress'] ?? 0);
                } else {
                    $data['studentTicketAlerts'] = [];
                    $data['studentTicketAlertCount'] = 0;
                }

                if ($studentId > 0) {
                    $liveAlerts = $portal->upcomingLiveClasses($studentId);
                    $data['studentLiveAlerts'] = array_slice(is_array($liveAlerts) ? $liveAlerts : [], 0, 5);
                    $data['studentLiveAlertCount'] = count($data['studentLiveAlerts']);
                } else {
                    $data['studentLiveAlerts'] = [];
                    $data['studentLiveAlertCount'] = 0;
                }

                if ($studentId > 0 && $portal->studentPortalNotificationsFeatureAvailable()) {
                    $data['studentPortalAlerts'] = $portal->listRecentPortalNotifications($studentId, 5);
                    $data['studentPortalAlertCount'] = $portal->countUnreadPortalNotifications($studentId);
                } else {
                    $data['studentPortalAlerts'] = [];
                    $data['studentPortalAlertCount'] = 0;
                }

                $data['studentAlertCount'] =
                    (int) ($data['studentTicketAlertCount'] ?? 0)
                    + (int) ($data['studentLiveAlertCount'] ?? 0)
                    + (int) ($data['studentPortalAlertCount'] ?? 0);
            } catch (Throwable $e) {
                $data['studentTicketAlerts'] = [];
                $data['studentTicketAlertCount'] = 0;
                $data['studentLiveAlerts'] = [];
                $data['studentLiveAlertCount'] = 0;
                $data['studentPortalAlerts'] = [];
                $data['studentPortalAlertCount'] = 0;
                $data['studentAlertCount'] = 0;
            }
        }

        view($view, $data, $layout);
    }

    protected function redirect(string $route): void
    {
        redirect($route);
    }

    protected function success(string $message): void
    {
        flash('success', $message);
    }

    protected function error(string $message): void
    {
        flash('error', $message);
    }

    protected function json(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
