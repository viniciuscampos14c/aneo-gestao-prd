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
