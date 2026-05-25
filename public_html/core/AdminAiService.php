<?php

class AdminAiService
{
    private int $companyId;
    private array $settings;

    public function __construct(?int $companyId = null)
    {
        $this->companyId = (int) ($companyId ?? current_company_id() ?? 0);
        $fallback = config('admin_ai', []);
        if (!is_array($fallback)) {
            $fallback = [];
        }

        if (class_exists('CompanyIntegrationModel')) {
            try {
                $this->settings = (new CompanyIntegrationModel())->mergeWithGlobalConfig('admin_ai', $this->companyId);
                return;
            } catch (Throwable $e) {
                // Continua com config global quando o model/tabela nao estiver disponivel.
            }
        }

        $this->settings = $fallback;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && $this->baseUrl() !== ''
            && $this->apiKey() !== ''
            && $this->model() !== '';
    }

    public function provider(): string
    {
        return trim((string) $this->setting('provider', 'openai_compatible'));
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string) $this->setting('base_url', '')), '/');
    }

    public function apiKey(): string
    {
        return trim((string) $this->setting('api_key', ''));
    }

    public function model(): string
    {
        return trim((string) $this->setting('model', ''));
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function ask(string $question, string $contextJson, array $history = []): array
    {
        $question = trim($question);
        $contextJson = trim($contextJson);

        if ($question === '') {
            return ['ok' => false, 'message' => 'Pergunta vazia para o assistente.'];
        }

        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'Assistente IA desativado para esta empresa.'];
        }

        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Assistente IA ativo, mas faltam credenciais (base_url, api_key ou model).'];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
        ];

        foreach ($this->normalizeHistory($history) as $entry) {
            $messages[] = $entry;
        }

        $messages[] = [
            'role' => 'user',
            'content' => "PERGUNTA DO USUARIO:\n" . $question . "\n\nCONTEXTO_INTERNO_JSON:\n" . ($contextJson !== '' ? $contextJson : '{}'),
        ];

        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'temperature' => $this->temperature(),
            'max_tokens' => $this->maxTokens(),
        ];

        $result = $this->request('POST', '/chat/completions', $payload);
        if (!($result['ok'] ?? false) && $this->isTimeoutError((string) ($result['message'] ?? ''))) {
            $retryPayload = [
                'model' => $this->model(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => "PERGUNTA DO USUARIO:\n" . $question . "\n\nCONTEXTO_INTERNO_JSON:\n" . ($contextJson !== '' ? $contextJson : '{}'),
                    ],
                ],
                'temperature' => min(0.2, $this->temperature()),
                'max_tokens' => max(200, min(600, (int) floor($this->maxTokens() * 0.6))),
            ];

            $retry = $this->request('POST', '/chat/completions', $retryPayload);
            if ($retry['ok'] ?? false) {
                $result = $retry;
            } else {
                $retryMessage = trim((string) ($retry['message'] ?? ''));
                if ($retryMessage !== '') {
                    $result['message'] = $retryMessage;
                }
                $result['status'] = $retry['status'] ?? ($result['status'] ?? 0);
            }
        }

        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => $result['message'] ?: 'Falha na chamada da API de IA.',
                'status' => $result['status'] ?? 0,
            ];
        }

        $answer = $this->extractAnswer($result['data'] ?? []);
        if ($answer === '') {
            return [
                'ok' => false,
                'message' => 'API respondeu sem conteudo de resposta.',
                'status' => $result['status'] ?? 0,
            ];
        }

        return [
            'ok' => true,
            'answer' => $answer,
            'provider' => $this->provider(),
            'model' => $this->model(),
            'usage' => $result['data']['usage'] ?? null,
        ];
    }

    private function systemPrompt(): string
    {
        $default = <<<'PROMPT'
Você é a Jully, assistente administrativa inteligente da ANEO Gestão Integrada.
Seu papel é ajudar a equipe administrativa da escola com informações precisas sobre alunos, leads, financeiro, cursos e chamados.

REGRAS OBRIGATÓRIAS:
1. Responda SEMPRE em português brasileiro, de forma clara e objetiva.
2. Use APENAS as informações presentes no CONTEXTO_INTERNO_JSON fornecido na mensagem do usuário.
3. NUNCA invente valores, nomes, datas, turmas, contratos ou status que não estejam no contexto.
4. Se os dados não estiverem no contexto, diga explicitamente: "Não encontrei essa informação no banco de dados interno." e sugira o que o usuário pode verificar diretamente no sistema.
5. Quando houver dados no contexto, seja específica: cite nomes, valores, datas e status exatos.

FORMATAÇÃO DAS RESPOSTAS:
- Para listas de alunos, leads ou itens: use marcadores (•) com nome e informação principal.
- Para valores financeiros: sempre use o formato R$ 0.000,00.
- Para datas: use o formato dia/mês/ano (ex: 27/03/2026).
- Para resumos com múltiplos dados: separe por seções com título em negrito.
- Respostas curtas e diretas para perguntas simples. Respostas detalhadas apenas quando necessário.
- Não repita a pergunta do usuário na resposta.

TOM E PERSONA:
- Profissional, prestativa e direta.
- Use "você" e trate o usuário com respeito.
- Quando não houver dados suficientes, seja honesta e proativa em sugerir onde buscar a informação.
PROMPT;

        $default = <<<'PROMPT_V2'
Voce e a Jully, assistente operacional da ANEO Gestao Integrada.
Seu foco principal e apoiar a equipe em decisoes praticas de financeiro, renegociacao, comercial e operacao.
Voce nao e um chat generico: fale como quem conhece a rotina administrativa da ANEO.

REGRAS OBRIGATORIAS:
1. Responda sempre em portugues do Brasil.
2. Use apenas as informacoes presentes no CONTEXTO_INTERNO_JSON.
3. Nunca invente nomes, valores, datas, contratos, parcelas, status ou conclusoes fora do contexto.
4. Se a informacao nao estiver no contexto, diga exatamente: "Nao encontrei essa informacao no banco interno." e sugira a tela mais adequada para conferencia.
5. Quando houver dados, seja especifica e operacional: cite nomes, valores, datas, status e prioridades.

COMO RESPONDER:
- Priorize objetividade operacional. Primeiro diga o que importa, depois os detalhes.
- Se a pergunta for de financeiro, destaque risco, saldo, vencimento e quem precisa de acao.
- Se a pergunta for de renegociacao, diferencie negociacao, aditivo, tickets pendentes e impacto no contas a receber.
- Se a pergunta for comercial, destaque leads sem contato, valor potencial, status e proximo passo.
- Se houver alertas operacionais no contexto, use isso para orientar prioridade.
- Nao repita a pergunta do usuario.
- Evite texto floreado ou resposta de assistente generico.

FORMATO:
- Para listas, use marcadores com uma linha por item.
- Para valores, use R$ 0.000,00.
- Para datas, use dia/mes/ano.
- Para respostas simples, seja curta.
- Para respostas analiticas, organize em blocos curtos com titulo em negrito.

POSTURA:
- Clara, firme, prestativa e confiavel.
- Fale como assistente de gestao, nao como suporte tecnico.
- Quando fizer sentido, termine com uma recomendacao objetiva de proximo passo.
PROMPT_V2;

        $custom = trim((string) $this->setting('system_prompt', ''));
        return $custom !== '' ? $custom : $default;
    }

    private function temperature(): float
    {
        $temperature = (float) $this->setting('temperature', 0.2);
        if ($temperature < 0) {
            return 0.0;
        }
        if ($temperature > 1.5) {
            return 1.5;
        }
        return $temperature;
    }

    private function maxTokens(): int
    {
        $maxTokens = (int) $this->setting('max_tokens', 450);
        return max(100, min(3000, $maxTokens));
    }

    private function timeoutSeconds(): int
    {
        $seconds = (int) $this->setting('timeout_seconds', 60);
        return max(10, min(180, $seconds));
    }

    private function normalizeHistory(array $history): array
    {
        $messages = [];
        $maxHistory = max(0, min(20, (int) $this->setting('history_messages', 8)));

        $tail = array_slice($history, -$maxHistory);
        foreach ($tail as $row) {
            if (!is_array($row)) {
                continue;
            }

            $role = strtolower(trim((string) ($row['role'] ?? '')));
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) ($row['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
            $metaProvider = strtolower(trim((string) ($metadata['provider'] ?? '')));
            $metaModel = strtolower(trim((string) ($metadata['model'] ?? '')));

            if ($role === 'assistant') {
                if ($metaProvider === 'internal_context' || $metaModel === 'deterministic') {
                    continue;
                }

                $lower = strtolower($content);
                if (str_starts_with($lower, 'resposta baseada no banco interno da escola')) {
                    continue;
                }
            }

            $content = $this->shrinkHistoryContent($content, $role === 'assistant' ? 900 : 700);
            if ($content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $messages;
    }

    private function isTimeoutError(string $message): bool
    {
        $message = strtolower(trim($message));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'operation timed out');
    }

    private function shrinkHistoryContent(string $content, int $maxChars): string
    {
        $content = trim($content);
        if ($content === '' || $maxChars <= 0) {
            return '';
        }

        $content = preg_replace('/\s+/u', ' ', $content) ?? $content;

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($content, 'UTF-8') <= $maxChars) {
                return $content;
            }

            return rtrim((string) mb_substr($content, 0, max(1, $maxChars - 3), 'UTF-8')) . '...';
        }

        if (strlen($content) <= $maxChars) {
            return $content;
        }

        return rtrim(substr($content, 0, max(1, $maxChars - 3))) . '...';
    }

    private function request(string $method, string $path, array $payload): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'message' => 'Extensao cURL nao disponivel no servidor.', 'data' => []];
        }

        $url = $this->baseUrl() . $path;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey(),
        ];

        $httpReferer = trim((string) $this->setting('http_referer', ''));
        if ($httpReferer !== '') {
            $headers[] = 'HTTP-Referer: ' . $httpReferer;
        }

        $appTitle = trim((string) $this->setting('app_title', config('app.name', 'ANEO Gestao')));
        if ($appTitle !== '') {
            $headers[] = 'X-Title: ' . $appTitle;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper(trim($method)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => $httpCode,
                'message' => 'Falha de conexao com API de IA: ' . ($error !== '' ? $error : 'erro desconhecido'),
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
            if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            } elseif (isset($decoded['message']) && is_string($decoded['message'])) {
                $message = $decoded['message'];
            } else {
                $message = 'Erro HTTP ' . $httpCode . ' na API de IA.';
            }
        }

        return [
            'ok' => $ok,
            'status' => $httpCode,
            'message' => $message,
            'data' => $decoded,
        ];
    }

    private function extractAnswer(array $response): string
    {
        $choices = $response['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            return '';
        }

        $first = $choices[0];
        $message = $first['message'] ?? null;
        if (is_array($message)) {
            $content = $message['content'] ?? '';
            if (is_string($content)) {
                return trim($content);
            }

            if (is_array($content)) {
                $chunks = [];
                foreach ($content as $piece) {
                    if (is_string($piece)) {
                        $chunks[] = $piece;
                        continue;
                    }

                    if (is_array($piece)) {
                        $text = trim((string) ($piece['text'] ?? ''));
                        if ($text !== '') {
                            $chunks[] = $text;
                        }
                    }
                }

                return trim(implode("\n", $chunks));
            }
        }

        $text = trim((string) ($first['text'] ?? ''));
        return $text;
    }

    private function setting(string $key, $default = null)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return $default;
    }
}
