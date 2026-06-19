<?php

class FinanceAutomationController extends BaseController
{
    private FinanceNotificationModel $notifications;

    public function __construct()
    {
        $this->notifications = new FinanceNotificationModel();
    }

    public function billingNotifications(): void
    {
        if (!(bool) config('automation.enabled', true)) {
            $this->json([
                'ok' => false,
                'message' => 'Automacoes desativadas no config.php.',
            ], 503);
        }

        $payload = $this->readPayload();
        $providedToken = $this->providedToken($payload);
        $configuredToken = trim((string) config('automation.finance_webhook_token', ''));
        if ($configuredToken === '') {
            $configuredToken = trim((string) config('automation.enrollment_webhook_token', ''));
        }

        if ($configuredToken !== '' && !hash_equals($configuredToken, $providedToken)) {
            $this->json([
                'ok' => false,
                'message' => 'Token inválido para automacao financeira.',
            ], 401);
        }

        $companyId = isset($payload['company_id']) ? (int) $payload['company_id'] : 0;
        $referenceDate = trim((string) ($payload['reference_date'] ?? request('reference_date', date('Y-m-d'))));
        $dispatch = $this->notifications->dispatchDueNotifications($companyId > 0 ? $companyId : null, $referenceDate);

        $this->json([
            'ok' => true,
            'message' => 'Automacao financeira executada.',
            'data' => $dispatch,
        ]);
    }

    private function readPayload(): array
    {
        $raw = file_get_contents('php://input');
        $payload = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($payload) || $payload === []) {
            $payload = $_POST ?: [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function providedToken(array $payload): string
    {
        $token = trim((string) request('token', ''));
        if ($token !== '') {
            return $token;
        }

        if (isset($_SERVER['HTTP_X_ANEO_TOKEN'])) {
            $token = trim((string) $_SERVER['HTTP_X_ANEO_TOKEN']);
            if ($token !== '') {
                return $token;
            }
        }

        if (isset($payload['token'])) {
            $token = trim((string) $payload['token']);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }
}
