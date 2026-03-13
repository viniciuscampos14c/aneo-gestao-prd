<?php

class ChatwootWebhookController extends BaseController
{
    private ChatwootService $chatwoot;
    private ChatwootFlowModel $flow;
    private CompanyIntegrationModel $integrations;

    public function __construct()
    {
        $this->chatwoot = new ChatwootService();
        $this->flow = new ChatwootFlowModel();
        $this->integrations = new CompanyIntegrationModel();
    }

    public function receive(): void
    {
        $providedToken = trim((string) request('token', ''));
        $this->chatwoot = $this->resolveServiceByWebhookToken($providedToken);
        $companyId = $this->chatwoot->companyId();
        $configuredToken = $this->chatwoot->webhookToken();

        if ($configuredToken !== '' && !hash_equals($configuredToken, $providedToken)) {
            $this->json([
                'ok' => false,
                'message' => 'Webhook token invalido.',
            ], 401);
        }

        if (!$this->chatwoot->botEnabled()) {
            $this->json(['ok' => true, 'ignored' => 'bot_disabled']);
        }

        if (!$this->chatwoot->isConfigured()) {
            $this->json(['ok' => false, 'message' => 'Integracao Chatwoot nao configurada.'], 503);
        }

        if (!$this->flow->featureAvailable()) {
            $this->json(['ok' => false, 'message' => 'Tabela chatwoot_flow_sessions indisponivel. Rode a migracao SQL.'], 503);
        }

        $payload = $this->readJsonPayload();
        if (!$payload) {
            $this->json(['ok' => true, 'ignored' => 'empty_payload']);
        }

        $event = strtolower(trim((string) ($payload['event'] ?? '')));
        if ($event !== 'message_created') {
            $this->json(['ok' => true, 'ignored' => 'event_' . ($event ?: 'unknown')]);
        }

        $message = is_array($payload['message'] ?? null) ? $payload['message'] : $payload;
        if (!$this->isIncomingMessage($message)) {
            $this->json(['ok' => true, 'ignored' => 'not_incoming']);
        }

        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            $this->json(['ok' => true, 'ignored' => 'empty_message']);
        }

        $conversationId = $this->extractConversationId($payload, $message);
        if ($conversationId <= 0) {
            $this->json(['ok' => true, 'ignored' => 'conversation_not_found']);
        }

        $contactName = trim((string) ($payload['contact']['name'] ?? ($message['sender']['name'] ?? '')));
        $contactPhone = trim((string) ($payload['contact']['phone_number'] ?? ($message['sender']['phone_number'] ?? '')));
        $contactId = (int) ($payload['contact']['id'] ?? ($message['sender']['id'] ?? 0));

        $normalized = $this->normalize($content);
        $session = $this->flow->findSession($conversationId, $companyId);

        if ($session === null || $this->isStartCommand($normalized)) {
            $this->sendBotMessage($conversationId, $this->chatwoot->botMessageMenu());

            $this->flow->upsertSession($conversationId, [
                'contact_id' => $contactId,
                'contact_name' => $contactName,
                'phone' => $contactPhone,
                'current_step' => 'menu_choice',
                'menu_choice' => null,
                'city' => null,
                'last_user_message' => $content,
                'handoff_team_id' => null,
                'handoff_sent_at' => null,
            ], $companyId);

            $this->json(['ok' => true, 'step' => 'menu_choice']);
        }

        $currentStep = (string) ($session['current_step'] ?? 'menu_choice');

        if ($currentStep === 'menu_choice') {
            $choice = $this->detectMenuChoice($normalized);
            if ($choice === null) {
                $this->sendBotMessage($conversationId, $this->chatwoot->botMessageInvalidOption());

                $this->flow->upsertSession($conversationId, [
                    'contact_id' => $contactId ?: ($session['contact_id'] ?? null),
                    'contact_name' => $contactName ?: ($session['contact_name'] ?? null),
                    'phone' => $contactPhone ?: ($session['phone'] ?? null),
                    'current_step' => 'menu_choice',
                    'menu_choice' => null,
                    'city' => $session['city'] ?? null,
                    'last_user_message' => $content,
                    'handoff_team_id' => $session['handoff_team_id'] ?? null,
                    'handoff_sent_at' => $session['handoff_sent_at'] ?? null,
                ], $companyId);

                $this->json(['ok' => true, 'step' => 'menu_choice', 'status' => 'invalid_option']);
            }

            $this->sendBotMessage($conversationId, $this->chatwoot->botMessageNameCity());

            $this->flow->upsertSession($conversationId, [
                'contact_id' => $contactId ?: ($session['contact_id'] ?? null),
                'contact_name' => $contactName ?: ($session['contact_name'] ?? null),
                'phone' => $contactPhone ?: ($session['phone'] ?? null),
                'current_step' => 'collect_name_city',
                'menu_choice' => $choice,
                'city' => $session['city'] ?? null,
                'last_user_message' => $content,
                'handoff_team_id' => $session['handoff_team_id'] ?? null,
                'handoff_sent_at' => $session['handoff_sent_at'] ?? null,
            ], $companyId);

            $this->json(['ok' => true, 'step' => 'collect_name_city', 'menu_choice' => $choice]);
        }

        if ($currentStep === 'collect_name_city') {
            [$city, $teamId] = $this->detectCity($normalized);
            $resolvedName = $this->resolveName($content, (string) ($session['contact_name'] ?? $contactName));

            if ($city === null) {
                $this->sendBotMessage($conversationId, $this->chatwoot->botMessageCityRetry());

                $this->flow->upsertSession($conversationId, [
                    'contact_id' => $contactId ?: ($session['contact_id'] ?? null),
                    'contact_name' => $resolvedName ?: ($session['contact_name'] ?? null),
                    'phone' => $contactPhone ?: ($session['phone'] ?? null),
                    'current_step' => 'collect_name_city',
                    'menu_choice' => $session['menu_choice'] ?? null,
                    'city' => null,
                    'last_user_message' => $content,
                    'handoff_team_id' => null,
                    'handoff_sent_at' => null,
                ], $companyId);

                $this->json(['ok' => true, 'step' => 'collect_name_city', 'status' => 'city_missing']);
            }

            $template = $this->chatwoot->botMessageHandoff();
            $handoffText = str_replace('{{cidade}}', $city, $template);
            $this->sendBotMessage($conversationId, $handoffText);

            $this->flow->upsertSession($conversationId, [
                'contact_id' => $contactId ?: ($session['contact_id'] ?? null),
                'contact_name' => $resolvedName ?: ($session['contact_name'] ?? null),
                'phone' => $contactPhone ?: ($session['phone'] ?? null),
                'current_step' => 'handoff_sent',
                'menu_choice' => $session['menu_choice'] ?? null,
                'city' => $city,
                'last_user_message' => $content,
                'handoff_team_id' => $teamId,
                'handoff_sent_at' => now(),
            ], $companyId);

            $this->json(['ok' => true, 'step' => 'handoff_sent', 'city' => $city]);
        }

        $this->flow->upsertSession($conversationId, [
            'contact_id' => $contactId ?: ($session['contact_id'] ?? null),
            'contact_name' => $contactName ?: ($session['contact_name'] ?? null),
            'phone' => $contactPhone ?: ($session['phone'] ?? null),
            'current_step' => $currentStep,
            'menu_choice' => $session['menu_choice'] ?? null,
            'city' => $session['city'] ?? null,
            'last_user_message' => $content,
            'handoff_team_id' => $session['handoff_team_id'] ?? null,
            'handoff_sent_at' => $session['handoff_sent_at'] ?? null,
        ], $companyId);

        $this->json(['ok' => true, 'step' => $currentStep, 'status' => 'stored']);
    }

    private function readJsonPayload(): ?array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function isIncomingMessage(array $message): bool
    {
        if ((bool) ($message['private'] ?? false)) {
            return false;
        }

        $senderType = strtolower(trim((string) ($message['sender_type'] ?? '')));
        if (in_array($senderType, ['agent', 'bot'], true)) {
            return false;
        }

        $messageType = $message['message_type'] ?? null;
        if (is_string($messageType)) {
            $type = strtolower(trim($messageType));
            return in_array($type, ['incoming', 'inbound', 'received'], true);
        }

        if (is_numeric($messageType)) {
            return (int) $messageType === 0;
        }

        return false;
    }

    private function extractConversationId(array $payload, array $message): int
    {
        $id = (int) ($message['conversation_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        $id = (int) ($payload['conversation']['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) ($payload['id'] ?? 0);
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function isStartCommand(string $normalizedMessage): bool
    {
        $keywords = $this->chatwoot->botStartKeywords();
        if ($keywords === []) {
            return false;
        }

        foreach ($keywords as $keyword) {
            $candidate = $this->normalize((string) $keyword);
            if ($candidate !== '' && str_contains($normalizedMessage, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function detectMenuChoice(string $normalizedMessage): ?string
    {
        if (preg_match('/(^|\\b)(1|sim|sou aluno)(\\b|$)/', $normalizedMessage)) {
            return '1';
        }

        if (preg_match('/(^|\\b)(2|nao|nao tenho interesse|tenho interesse)(\\b|$)/', $normalizedMessage)) {
            return '2';
        }

        return null;
    }

    private function detectCity(string $normalizedMessage): array
    {
        $map = $this->chatwoot->botCityTeamMap();
        if ($map === []) {
            return [null, null];
        }

        foreach ($map as $cityLabel => $teamId) {
            $normalizedCity = $this->normalize((string) $cityLabel);
            if ($normalizedCity !== '' && str_contains($normalizedMessage, $normalizedCity)) {
                $displayName = ucwords((string) $cityLabel);
                return [$displayName, $teamId !== null ? (int) $teamId : null];
            }
        }

        return [null, null];
    }

    private function resolveName(string $originalMessage, string $fallback): string
    {
        $originalMessage = trim($originalMessage);
        $fallback = trim($fallback);

        $normalized = $this->normalize($originalMessage);

        if (preg_match('/me chamo ([a-z ]+)/', $normalized, $m)) {
            return ucwords(trim($m[1]));
        }

        if (preg_match('/meu nome e ([a-z ]+)/', $normalized, $m)) {
            return ucwords(trim($m[1]));
        }

        if (str_contains($originalMessage, ',')) {
            $first = trim(explode(',', $originalMessage, 2)[0]);
            if ($first !== '' && strlen($first) <= 60) {
                return ucwords($first);
            }
        }

        return $fallback;
    }

    private function sendBotMessage(int $conversationId, string $content): void
    {
        $this->chatwoot->sendConversationMessage($conversationId, $content, false);
    }

    private function resolveServiceByWebhookToken(string $providedToken): ChatwootService
    {
        if ($providedToken !== '') {
            $companyId = $this->integrations->findCompanyIdByToken('chatwoot', 'webhook_token', $providedToken);
            if ($companyId !== null && $companyId > 0) {
                return new ChatwootService($companyId);
            }
        }

        return new ChatwootService();
    }
}
