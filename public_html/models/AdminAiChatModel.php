<?php

class AdminAiChatModel extends BaseModel
{
    private ?bool $sessionsTableExists = null;
    private ?bool $messagesTableExists = null;

    public function featureAvailable(): bool
    {
        return $this->hasSessionsTable() && $this->hasMessagesTable();
    }

    public function listSessions(int $userId, int $limit = 20): array
    {
        if (!$this->featureAvailable() || $this->companyId() <= 0 || $userId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        $stmt = $this->db->prepare('SELECT
                s.id,
                s.title,
                s.created_at,
                s.updated_at,
                (
                    SELECT content
                    FROM admin_ai_messages m
                    WHERE m.session_id = s.id
                    ORDER BY m.id DESC
                    LIMIT 1
                ) AS last_message
            FROM admin_ai_sessions s
            WHERE s.company_id = :company_id
              AND s.user_id = :user_id
            ORDER BY s.updated_at DESC, s.id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function findSession(int $sessionId, int $userId): ?array
    {
        if (!$this->featureAvailable() || $this->companyId() <= 0 || $sessionId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM admin_ai_sessions
            WHERE id = :id
              AND company_id = :company_id
              AND user_id = :user_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $sessionId,
            ':company_id' => $this->companyId(),
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createSession(int $userId, string $title): int
    {
        if (!$this->featureAvailable() || $this->companyId() <= 0 || $userId <= 0) {
            return 0;
        }

        $title = trim($title);
        if ($title === '') {
            $title = 'Novo chat';
        }

        $stmt = $this->db->prepare('INSERT INTO admin_ai_sessions (
                company_id, user_id, title, created_at, updated_at
            ) VALUES (
                :company_id, :user_id, :title, :created_at, :updated_at
            )');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':user_id' => $userId,
            ':title' => $this->truncate($title, 120),
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function renameSession(int $sessionId, int $userId, string $title): void
    {
        if (!$this->featureAvailable() || $this->companyId() <= 0 || $sessionId <= 0 || $userId <= 0) {
            return;
        }

        $title = $this->truncate(trim($title), 120);
        if ($title === '') {
            return;
        }

        $stmt = $this->db->prepare('UPDATE admin_ai_sessions
            SET title = :title,
                updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id
              AND user_id = :user_id');
        $stmt->execute([
            ':title' => $title,
            ':updated_at' => now(),
            ':id' => $sessionId,
            ':company_id' => $this->companyId(),
            ':user_id' => $userId,
        ]);
    }

    public function listMessages(int $sessionId, int $userId, int $limit = 50): array
    {
        if (!$this->featureAvailable() || $this->companyId() <= 0 || $sessionId <= 0 || $userId <= 0) {
            return [];
        }

        if (!$this->findSession($sessionId, $userId)) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        $stmt = $this->db->prepare('SELECT role, content, metadata_json, created_at
            FROM admin_ai_messages
            WHERE session_id = :session_id
            ORDER BY id DESC
            LIMIT :limit');
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        $rows = array_reverse($rows);

        foreach ($rows as &$row) {
            $metadata = [];
            $rawMetadata = (string) ($row['metadata_json'] ?? '');
            if ($rawMetadata !== '') {
                $decoded = json_decode($rawMetadata, true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }
            $row['metadata'] = $metadata;
            unset($row['metadata_json']);
        }

        return $rows;
    }

    public function appendMessage(int $sessionId, int $userId, string $role, string $content, array $metadata = []): void
    {
        if (!$this->featureAvailable() || $this->companyId() <= 0 || $sessionId <= 0 || $userId <= 0) {
            return;
        }

        if (!$this->findSession($sessionId, $userId)) {
            return;
        }

        $role = strtolower(trim($role));
        if (!in_array($role, ['system', 'user', 'assistant'], true)) {
            $role = 'assistant';
        }

        $content = trim($content);
        if ($content === '') {
            return;
        }

        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }

        $stmt = $this->db->prepare('INSERT INTO admin_ai_messages (
                session_id, role, content, metadata_json, created_at
            ) VALUES (
                :session_id, :role, :content, :metadata_json, :created_at
            )');
        $stmt->execute([
            ':session_id' => $sessionId,
            ':role' => $role,
            ':content' => $content,
            ':metadata_json' => $json,
            ':created_at' => now(),
        ]);

        $touch = $this->db->prepare('UPDATE admin_ai_sessions
            SET updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id
              AND user_id = :user_id');
        $touch->execute([
            ':updated_at' => now(),
            ':id' => $sessionId,
            ':company_id' => $this->companyId(),
            ':user_id' => $userId,
        ]);
    }

    private function hasSessionsTable(): bool
    {
        if ($this->sessionsTableExists !== null) {
            return $this->sessionsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'admin_ai_sessions'");
        $stmt->execute();
        $this->sessionsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->sessionsTableExists;
    }

    private function hasMessagesTable(): bool
    {
        if ($this->messagesTableExists !== null) {
            return $this->messagesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'admin_ai_messages'");
        $stmt->execute();
        $this->messagesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->messagesTableExists;
    }

    private function truncate(string $value, int $maxChars): string
    {
        if ($maxChars <= 0 || $value === '') {
            return '';
        }

        if (strlen($value) <= $maxChars) {
            return $value;
        }

        return substr($value, 0, $maxChars);
    }
}
