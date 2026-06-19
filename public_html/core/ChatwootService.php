<?php

class ChatwootService
{
    private int $companyId;
    private array $settings;

    public function __construct(?int $companyId = null)
    {
        $this->companyId = (int) ($companyId ?? current_company_id() ?? 0);
        $this->settings = (new CompanyIntegrationModel())->mergeWithGlobalConfig('chatwoot', $this->companyId);
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    public function baseUrl(): string
    {
        $base = trim((string) $this->setting('base_url', ''));
        return rtrim($base, '/');
    }

    public function accountId(): int
    {
        return (int) $this->setting('account_id', 0);
    }

    public function inboxId(): int
    {
        return (int) $this->setting('inbox_id', 0);
    }

    public function apiAccessToken(): string
    {
        return trim((string) $this->setting('api_access_token', ''));
    }

    public function webhookToken(): string
    {
        return trim((string) $this->setting('webhook_token', ''));
    }

    public function botEnabled(): bool
    {
        return (bool) $this->setting('bot_enabled', false);
    }

    public function botStartKeywords(): array
    {
        $value = $this->setting('bot_start_keywords', []);
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array_map('trim', explode(',', $value));
            }
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $value), fn ($item) => trim($item) !== ''));
    }

    public function botMessageMenu(): string
    {
        return (string) $this->setting('bot_message_menu', 'Ola! Em que posso ajudar?');
    }

    public function botMessageNameCity(): string
    {
        return (string) $this->setting('bot_message_name_city', 'Qual e o seu nome e cidade de interesse?');
    }

    public function botMessageInvalidOption(): string
    {
        return (string) $this->setting('bot_message_invalid_option', 'Responda com 1 ou 2 para continuar.');
    }

    public function botMessageCityRetry(): string
    {
        return (string) $this->setting('bot_message_city_retry', 'Me informe a cidade para eu direcionar ao time correto.');
    }

    public function botMessageHandoff(): string
    {
        return (string) $this->setting('bot_message_handoff', 'Vou encaminhar para a unidade {{cidade}}.');
    }

    public function botCityTeamMap(): array
    {
        $value = $this->setting('bot_city_team_map', []);
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }

        return is_array($value) ? $value : [];
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && $this->baseUrl() !== ''
            && $this->accountId() > 0
            && $this->inboxId() > 0
            && $this->apiAccessToken() !== '';
    }

    public function dashboardConversationsUrl(?int $conversationId = null): string
    {
        if ($this->baseUrl() === '' || $this->accountId() <= 0) {
            return '#';
        }

        $url = $this->baseUrl() . '/app/accounts/' . $this->accountId() . '/conversations';

        if ($conversationId !== null && $conversationId > 0) {
            $url .= '/' . $conversationId;
        }

        return $url;
    }

    public function ensureConversation(array $person, ?array $existingLink = null): array
    {
        if (!$this->isEnabled()) {
            return [
                'ok' => false,
                'message' => 'Integração Chatwoot desativada em config.php.',
                'conversation_url' => null,
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Chatwoot habilitado, mas faltam credenciais (base_url, account_id, inbox_id, api_access_token).',
                'conversation_url' => $this->dashboardConversationsUrl(),
            ];
        }

        $entityType = trim((string) ($person['entity_type'] ?? 'other'));
        $entityId = (int) ($person['entity_id'] ?? 0);
        $name = trim((string) ($person['name'] ?? ''));
        $email = trim((string) ($person['email'] ?? ''));
        $phoneE164 = $this->normalizePhoneE164($person['phone'] ?? '');

        if ($name === '') {
            $name = ucfirst($entityType) . ' #' . max(0, $entityId);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $contact = null;
        $contactId = (int) ($existingLink['contact_id'] ?? 0);

        if ($contactId > 0) {
            $contact = ['id' => $contactId];
        } else {
            $searchTerms = [];
            if ($phoneE164) {
                $searchTerms[] = $phoneE164;
                $searchTerms[] = ltrim($phoneE164, '+');
            }
            if ($email !== '') {
                $searchTerms[] = $email;
            }
            if ($name !== '') {
                $searchTerms[] = $name;
            }

            foreach (array_values(array_unique($searchTerms)) as $term) {
                $candidates = $this->searchContacts($term);
                if ($candidates !== []) {
                    $contact = $this->pickBestContact($candidates, $phoneE164, $email);
                    if ($contact) {
                        break;
                    }
                }
            }
        }

        if (!$contact) {
            $createContact = $this->createContact([
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone_number' => $phoneE164,
                'identifier' => strtolower($entityType) . '-' . max(0, $entityId),
            ]);

            if (!$createContact['ok']) {
                return [
                    'ok' => false,
                    'message' => $createContact['message'] ?: 'Não foi possível criar contato no Chatwoot.',
                    'conversation_url' => $this->dashboardConversationsUrl(),
                ];
            }

            $contact = $createContact['contact'] ?? null;
        }

        if (!$contact || empty($contact['id'])) {
            return [
                'ok' => false,
                'message' => 'Não foi possível identificar contato no Chatwoot.',
                'conversation_url' => $this->dashboardConversationsUrl(),
            ];
        }

        $contactId = (int) $contact['id'];
        $conversationId = (int) ($existingLink['conversation_id'] ?? 0);
        $sourceId = trim((string) ($existingLink['contact_source_id'] ?? ''));

        if ($conversationId <= 0) {
            $contactConversations = $this->contactConversations($contactId);
            if ($contactConversations !== []) {
                $conversationId = (int) ($contactConversations[0]['id'] ?? 0);
                $sourceId = trim((string) ($contactConversations[0]['source_id'] ?? $sourceId));
            }
        }

        if ($conversationId <= 0) {
            if ($sourceId === '') {
                $sourceId = $this->buildSourceId($entityType, $entityId, $phoneE164, $email);
            }

            $createdConversation = $this->createConversation($contactId, $sourceId);
            if (!$createdConversation['ok']) {
                return [
                    'ok' => false,
                    'message' => $createdConversation['message'] ?: 'Não foi possível criar conversa no Chatwoot.',
                    'conversation_url' => $this->dashboardConversationsUrl(),
                    'contact_id' => $contactId,
                    'contact_source_id' => $sourceId,
                ];
            }

            $conversation = $createdConversation['conversation'] ?? [];
            $conversationId = (int) ($conversation['id'] ?? 0);
            $sourceId = trim((string) ($conversation['source_id'] ?? $sourceId));
        }

        return [
            'ok' => true,
            'message' => 'Conversa pronta no Chatwoot.',
            'contact_id' => $contactId,
            'contact_source_id' => $sourceId !== '' ? $sourceId : null,
            'conversation_id' => $conversationId > 0 ? $conversationId : null,
            'conversation_url' => $this->dashboardConversationsUrl($conversationId > 0 ? $conversationId : null),
            'contact_name' => $name,
            'contact_phone' => $phoneE164,
            'contact_email' => $email !== '' ? $email : null,
        ];
    }

    public function searchContacts(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $result = $this->request('GET', '/api/v1/accounts/' . $this->accountId() . '/contacts/search', null, ['q' => $query]);
        if (!$result['ok']) {
            return [];
        }

        $payload = $result['data']['payload'] ?? [];
        if (!is_array($payload)) {
            return [];
        }

        $rows = [];
        foreach ($payload as $row) {
            if (is_array($row) && isset($row['id'])) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function contactConversations(int $contactId): array
    {
        if ($contactId <= 0) {
            return [];
        }

        $result = $this->request('GET', '/api/v1/accounts/' . $this->accountId() . '/contacts/' . $contactId . '/conversations');
        if (!$result['ok']) {
            return [];
        }

        $payload = $result['data']['payload'] ?? [];
        if (!is_array($payload)) {
            return [];
        }

        $rows = [];
        foreach ($payload as $row) {
            if (is_array($row) && isset($row['id'])) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function createContact(array $payload): array
    {
        $data = [
            'name' => trim((string) ($payload['name'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone_number' => trim((string) ($payload['phone_number'] ?? '')),
            'identifier' => trim((string) ($payload['identifier'] ?? '')),
        ];

        if ($data['name'] === '') {
            return ['ok' => false, 'message' => 'Nome do contato não informado.'];
        }

        if ($data['email'] === '') {
            unset($data['email']);
        }

        if ($data['phone_number'] === '') {
            unset($data['phone_number']);
        }

        if ($data['identifier'] === '') {
            unset($data['identifier']);
        }

        $result = $this->request('POST', '/api/v1/accounts/' . $this->accountId() . '/contacts', $data);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => $result['message'] ?: 'Falha ao criar contato no Chatwoot.'];
        }

        $contact = $this->extractEntity($result['data']);
        return [
            'ok' => $contact !== null,
            'message' => $contact ? 'Contato criado.' : 'Contato criado sem retorno de ID.',
            'contact' => $contact,
        ];
    }

    public function createConversation(int $contactId, string $sourceId): array
    {
        if ($contactId <= 0) {
            return ['ok' => false, 'message' => 'Contato inválido para criar conversa.'];
        }

        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            return ['ok' => false, 'message' => 'source_id da conversa não informado.'];
        }

        $payload = [
            'source_id' => $sourceId,
            'inbox_id' => $this->inboxId(),
            'contact_id' => $contactId,
            'status' => 'open',
        ];

        $result = $this->request('POST', '/api/v1/accounts/' . $this->accountId() . '/conversations', $payload);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => $result['message'] ?: 'Falha ao criar conversa no Chatwoot.'];
        }

        $conversation = $this->extractEntity($result['data']);
        return [
            'ok' => $conversation !== null,
            'message' => $conversation ? 'Conversa criada.' : 'Conversa criada sem retorno de ID.',
            'conversation' => $conversation,
        ];
    }

    public function sendConversationMessage(int $conversationId, string $content, bool $private = false): array
    {
        if ($conversationId <= 0) {
            return ['ok' => false, 'message' => 'Conversa inválida para envio de mensagem.'];
        }

        $content = trim($content);
        if ($content === '') {
            return ['ok' => false, 'message' => 'Conteudo da mensagem vazio.'];
        }

        $payload = [
            'content' => $content,
            'message_type' => 'outgoing',
            'private' => $private ? true : false,
        ];

        $result = $this->request(
            'POST',
            '/api/v1/accounts/' . $this->accountId() . '/conversations/' . $conversationId . '/messages',
            $payload
        );

        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => $result['message'] ?: 'Falha ao enviar mensagem da automacao para o Chatwoot.',
                'response' => $result['data'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Mensagem enviada na conversa.',
            'response' => $result['data'] ?? null,
        ];
    }

    private function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'message' => 'Extensão cURL não disponível no servidor.', 'data' => []];
        }

        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'POST'], true)) {
            return ['ok' => false, 'status' => 0, 'message' => 'Metodo HTTP não suportado para Chatwoot.', 'data' => []];
        }

        $url = $this->baseUrl() . $path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'api_access_token: ' . $this->apiAccessToken(),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($method === 'POST') {
            $json = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json !== false ? $json : '{}');
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => $httpCode,
                'message' => 'Falha de conexao com Chatwoot: ' . ($curlError ?: 'erro desconhecido'),
                'data' => [],
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $raw];
        }

        $ok = $httpCode >= 200 && $httpCode < 300;
        $message = '';

        if (!$ok) {
            if (isset($decoded['message']) && is_string($decoded['message'])) {
                $message = $decoded['message'];
            } elseif (isset($decoded['error']) && is_string($decoded['error'])) {
                $message = $decoded['error'];
            } elseif (isset($decoded['errors']) && is_array($decoded['errors'])) {
                $message = implode('; ', array_map('strval', $decoded['errors']));
            } else {
                $message = 'Erro HTTP ' . $httpCode . ' ao acessar Chatwoot.';
            }
        }

        return [
            'ok' => $ok,
            'status' => $httpCode,
            'message' => $message,
            'data' => $decoded,
        ];
    }

    private function extractEntity(array $body): ?array
    {
        if (isset($body['id']) && is_numeric($body['id'])) {
            return $body;
        }

        $payload = $body['payload'] ?? null;
        if (is_array($payload)) {
            if (isset($payload['id']) && is_numeric($payload['id'])) {
                return $payload;
            }

            if (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['id'])) {
                return $payload[0];
            }

            if (isset($payload['contact']) && is_array($payload['contact']) && isset($payload['contact']['id'])) {
                return $payload['contact'];
            }
        }

        return null;
    }

    private function pickBestContact(array $contacts, ?string $phoneE164, ?string $email): ?array
    {
        if ($contacts === []) {
            return null;
        }

        foreach ($contacts as $contact) {
            $contactPhone = $this->normalizePhoneE164((string) ($contact['phone_number'] ?? ''));
            if ($phoneE164 && $contactPhone && $contactPhone === $phoneE164) {
                return $contact;
            }
        }

        foreach ($contacts as $contact) {
            $contactEmail = strtolower(trim((string) ($contact['email'] ?? '')));
            if ($email && $contactEmail !== '' && strtolower($email) === $contactEmail) {
                return $contact;
            }
        }

        return $contacts[0] ?? null;
    }

    private function normalizePhoneE164(?string $phone): ?string
    {
        $digits = whatsapp_number($phone);
        if (!$digits) {
            return null;
        }

        return '+' . $digits;
    }

    private function buildSourceId(string $entityType, int $entityId, ?string $phoneE164, ?string $email): string
    {
        $fingerprint = implode('|', [
            strtolower(trim($entityType)),
            max(0, $entityId),
            trim((string) $phoneE164),
            strtolower(trim((string) $email)),
        ]);

        return substr('aneo-' . sha1($fingerprint), 0, 60);
    }

    private function setting(string $key, $default = null)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return $default;
    }
}
