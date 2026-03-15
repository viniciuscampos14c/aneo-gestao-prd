<?php

class AuditLogService
{
    private SystemAuditLogModel $logs;

    public function __construct()
    {
        $this->logs = new SystemAuditLogModel();
    }

    public function available(): bool
    {
        return $this->logs->tableExists();
    }

    public function log(array $payload): void
    {
        try {
            if (!$this->logs->tableExists()) {
                return;
            }

            $actor = current_user();
            $userRole = strtolower(trim((string) ($payload['user_role'] ?? ($actor['role'] ?? ''))));
            if ($userRole !== '' && !in_array($userRole, ['admin', 'suporte', 'professor'], true)) {
                return;
            }

            $before = $this->sanitize($payload['before'] ?? null);
            $after = $this->sanitize($payload['after'] ?? null);
            $metadata = $this->sanitize($payload['metadata'] ?? []);
            $changes = $this->buildChanges(
                is_array($before) ? $before : [],
                is_array($after) ? $after : []
            );

            $companyId = (int) ($payload['company_id'] ?? current_company_id() ?? 0);
            $userId = (int) ($payload['user_id'] ?? ($actor['id'] ?? 0));

            $this->logs->insert([
                'company_id' => $companyId > 0 ? $companyId : null,
                'user_id' => $userId > 0 ? $userId : null,
                'user_name' => trim((string) ($payload['user_name'] ?? ($actor['name'] ?? ''))),
                'user_email' => trim((string) ($payload['user_email'] ?? ($actor['email'] ?? ''))),
                'user_role' => $userRole,
                'module' => trim((string) ($payload['module'] ?? 'sistema')),
                'action' => trim((string) ($payload['action'] ?? 'update')),
                'entity_type' => trim((string) ($payload['entity_type'] ?? 'registro')),
                'entity_id' => isset($payload['entity_id']) ? (int) $payload['entity_id'] : null,
                'entity_label' => $this->trimText((string) ($payload['entity_label'] ?? ''), 255),
                'description' => $this->trimText((string) ($payload['description'] ?? ''), 255),
                'changes_json' => $this->encodeJson($changes),
                'metadata_json' => $this->encodeJson(is_array($metadata) ? $metadata : []),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Auditoria nao pode interromper o fluxo principal.
        }
    }

    private function buildChanges(array $before, array $after): array
    {
        if ($before === [] && $after === []) {
            return [];
        }

        $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        $changes = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;
            if ($this->normalizeComparable($beforeValue) === $this->normalizeComparable($afterValue)) {
                continue;
            }

            $changes[$key] = [
                'before' => $beforeValue,
                'after' => $afterValue,
            ];
        }

        return $changes;
    }

    private function normalizeComparable($value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded !== false ? $encoded : (string) $value;
    }

    private function sanitize($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $normalizedKey = strtolower((string) $key);
                if ($this->isSensitiveKey($normalizedKey)) {
                    $clean[$key] = '[REDACTED]';
                    continue;
                }

                $clean[$key] = $this->sanitize($item);
            }

            return $clean;
        }

        if (is_object($value)) {
            return $this->sanitize((array) $value);
        }

        if (is_string($value)) {
            return $this->trimText($value, 2000);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/password|secret|token|api_key|access_token|crypt_key|hash|smtp_password/i', $key) === 1;
    }

    private function trimText(string $text, int $max): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $max) {
                return $text;
            }

            return mb_substr($text, 0, $max - 3, 'UTF-8') . '...';
        }

        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 3) . '...';
    }

    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{}';
        }

        return $json;
    }
}
