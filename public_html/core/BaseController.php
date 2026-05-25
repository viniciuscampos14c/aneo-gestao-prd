<?php

abstract class BaseController
{
    protected function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        if ($layout === 'layouts/app' && current_user()) {
            try {
                if (!is_professor()) {
                    $tickets = new SupportTicketModel();
                    $data['mobileNegotiationAlerts'] = $tickets->latestMobileNegotiationAlerts(5);
                    $data['mobileNegotiationAlertCount'] = $tickets->countOpenMobileNegotiations();
                    if (has_permission('finance') && class_exists('PayableModel')) {
                        $payables = new PayableModel();
                        $data['payableDueAlerts'] = $payables->dueAlerts(7, 8);
                        $data['payableDueAlertCount'] = $payables->dueAlertCount(7);
                    } else {
                        $data['payableDueAlerts'] = [];
                        $data['payableDueAlertCount'] = 0;
                    }
                } else {
                    $data['mobileNegotiationAlerts'] = [];
                    $data['mobileNegotiationAlertCount'] = 0;
                    $data['payableDueAlerts'] = [];
                    $data['payableDueAlertCount'] = 0;
                }
                if (has_permission('students')) {
                    $exchange = new StudentExchangeModel();
                    $companyId = (int) (current_company_id() ?? 0);
                    $data['exchangeAlerts'] = $exchange->latestPendingAlerts($companyId, 5);
                    $data['exchangeAlertCount'] = $exchange->countPendingAlerts($companyId);
                } else {
                    $data['exchangeAlerts'] = [];
                    $data['exchangeAlertCount'] = 0;
                }
            } catch (Throwable $e) {
                $data['mobileNegotiationAlerts'] = [];
                $data['mobileNegotiationAlertCount'] = 0;
                $data['payableDueAlerts'] = [];
                $data['payableDueAlertCount'] = 0;
                $data['exchangeAlerts'] = [];
                $data['exchangeAlertCount'] = 0;
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
                $portalNotificationsAvailable = $studentId > 0 && $portal->studentPortalNotificationsFeatureAvailable();

                if ($tickets->featureAvailable() && $studentId > 0 && $companyId > 0) {
                    $rows = $tickets->listStudentTickets(
                        $companyId,
                        $studentId,
                        $studentEmail,
                        ['status' => 'pending'],
                        5,
                        1
                    );
                    $ticketRows = is_array($rows['rows'] ?? null) ? $rows['rows'] : [];
                    if ($portalNotificationsAvailable) {
                        foreach ($ticketRows as $alert) {
                            $ticketId = (int) ($alert['id'] ?? 0);
                            $ticketCode = trim((string) ($alert['ticket_code'] ?? ''));
                            if ($ticketCode === '' && $ticketId > 0) {
                                $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
                            }

                            $status = trim((string) ($alert['status'] ?? 'open'));
                            $statusLabel = match ($status) {
                                'in_progress' => 'Em andamento',
                                'resolved' => 'Resolvido',
                                'closed' => 'Fechado',
                                default => 'Aberto',
                            };

                            $portal->createPortalNotification([
                                'company_id' => $companyId,
                                'student_id' => $studentId,
                                'notification_type' => 'support_ticket_pending',
                                'title' => 'Chamado em andamento: ' . ($ticketCode !== '' ? $ticketCode : ('#' . $ticketId)),
                                'message' => 'Seu chamado "' . trim((string) ($alert['subject'] ?? 'Chamado')) . '" esta com status ' . $statusLabel . '.',
                                'link_url' => route('student/requests'),
                                'meta' => [
                                    'ticket_id' => $ticketId,
                                    'ticket_code' => $ticketCode,
                                    'status' => $status,
                                ],
                            ]);
                        }
                    }
                    $data['studentTicketAlerts'] = $portalNotificationsAvailable ? [] : $ticketRows;
                    $data['studentTicketAlertCount'] = $portalNotificationsAvailable ? 0 : count($ticketRows);
                } else {
                    $data['studentTicketAlerts'] = [];
                    $data['studentTicketAlertCount'] = 0;
                }

                if ($studentId > 0) {
                    $liveAlerts = $portal->upcomingLiveClasses($studentId);
                    $liveRows = array_slice(is_array($liveAlerts) ? $liveAlerts : [], 0, 5);
                    if ($portalNotificationsAvailable) {
                        foreach ($liveRows as $alert) {
                            $liveTitle = trim((string) ($alert['name'] ?? 'Aula ao vivo'));
                            $liveCourse = trim((string) ($alert['course_name'] ?? ''));
                            $liveDatetime = trim((string) ($alert['live_datetime'] ?? ''));
                            $when = $liveDatetime !== '' ? date('d/m/Y H:i', strtotime($liveDatetime)) : 'em breve';
                            $portal->createPortalNotification([
                                'company_id' => $companyId,
                                'student_id' => $studentId,
                                'notification_type' => 'live_class',
                                'title' => 'Aula ao vivo: ' . $liveTitle,
                                'message' => ($liveCourse !== '' ? 'Curso ' . $liveCourse . ' | ' : '') . 'Encontro agendado para ' . $when . '.',
                                'link_url' => route('student/live'),
                                'meta' => [
                                    'session_name' => $liveTitle,
                                    'course_name' => $liveCourse,
                                    'live_datetime' => $liveDatetime,
                                ],
                            ]);
                        }
                    }
                    $data['studentLiveAlerts'] = $portalNotificationsAvailable ? [] : $liveRows;
                    $data['studentLiveAlertCount'] = $portalNotificationsAvailable ? 0 : count($liveRows);
                } else {
                    $data['studentLiveAlerts'] = [];
                    $data['studentLiveAlertCount'] = 0;
                }

                if ($portalNotificationsAvailable) {
                    $data['studentPortalAlerts'] = $portal->listRecentPortalNotifications($studentId, 20);
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
