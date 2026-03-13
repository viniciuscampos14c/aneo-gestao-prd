<?php

class ChatwootFlowModel extends BaseModel
{
    private ?bool $sessionsTableExists = null;
    private ?bool $companyColumnExists = null;

    public function featureAvailable(): bool
    {
        return $this->hasSessionsTable();
    }

    public function findSession(int $conversationId, ?int $companyId = null): ?array
    {
        if (!$this->hasSessionsTable() || $conversationId <= 0) {
            return null;
        }

        $companyId = (int) ($companyId ?? $this->companyId());
        if ($this->hasCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('SELECT * FROM chatwoot_flow_sessions
                WHERE conversation_id = :conversation_id
                  AND company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':conversation_id' => $conversationId,
                ':company_id' => $companyId,
            ]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM chatwoot_flow_sessions WHERE conversation_id = :conversation_id LIMIT 1');
            $stmt->execute([':conversation_id' => $conversationId]);
        }
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function upsertSession(int $conversationId, array $data, ?int $companyId = null): void
    {
        if (!$this->hasSessionsTable() || $conversationId <= 0) {
            return;
        }

        $now = now();
        $companyId = (int) ($companyId ?? $this->companyId());

        if ($this->hasCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('INSERT INTO chatwoot_flow_sessions (
                company_id, conversation_id, contact_id, contact_name, phone, current_step, menu_choice,
                city, last_user_message, handoff_team_id, handoff_sent_at, created_at, updated_at
            ) VALUES (
                :company_id, :conversation_id, :contact_id, :contact_name, :phone, :current_step, :menu_choice,
                :city, :last_user_message, :handoff_team_id, :handoff_sent_at, :created_at, :updated_at
            ) ON DUPLICATE KEY UPDATE
                contact_id = VALUES(contact_id),
                contact_name = VALUES(contact_name),
                phone = VALUES(phone),
                current_step = VALUES(current_step),
                menu_choice = VALUES(menu_choice),
                city = VALUES(city),
                last_user_message = VALUES(last_user_message),
                handoff_team_id = VALUES(handoff_team_id),
                handoff_sent_at = VALUES(handoff_sent_at),
                updated_at = VALUES(updated_at)');

            $stmt->execute([
                ':company_id' => $companyId,
                ':conversation_id' => $conversationId,
                ':contact_id' => ($data['contact_id'] ?? null) ?: null,
                ':contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
                ':phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                ':current_step' => trim((string) ($data['current_step'] ?? 'menu_choice')) ?: 'menu_choice',
                ':menu_choice' => trim((string) ($data['menu_choice'] ?? '')) ?: null,
                ':city' => trim((string) ($data['city'] ?? '')) ?: null,
                ':last_user_message' => trim((string) ($data['last_user_message'] ?? '')) ?: null,
                ':handoff_team_id' => ($data['handoff_team_id'] ?? null) ?: null,
                ':handoff_sent_at' => $data['handoff_sent_at'] ?? null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO chatwoot_flow_sessions (
            conversation_id, contact_id, contact_name, phone, current_step, menu_choice,
            city, last_user_message, handoff_team_id, handoff_sent_at, created_at, updated_at
        ) VALUES (
            :conversation_id, :contact_id, :contact_name, :phone, :current_step, :menu_choice,
            :city, :last_user_message, :handoff_team_id, :handoff_sent_at, :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE
            contact_id = VALUES(contact_id),
            contact_name = VALUES(contact_name),
            phone = VALUES(phone),
            current_step = VALUES(current_step),
            menu_choice = VALUES(menu_choice),
            city = VALUES(city),
            last_user_message = VALUES(last_user_message),
            handoff_team_id = VALUES(handoff_team_id),
            handoff_sent_at = VALUES(handoff_sent_at),
            updated_at = VALUES(updated_at)');

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':contact_id' => ($data['contact_id'] ?? null) ?: null,
            ':contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
            ':phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            ':current_step' => trim((string) ($data['current_step'] ?? 'menu_choice')) ?: 'menu_choice',
            ':menu_choice' => trim((string) ($data['menu_choice'] ?? '')) ?: null,
            ':city' => trim((string) ($data['city'] ?? '')) ?: null,
            ':last_user_message' => trim((string) ($data['last_user_message'] ?? '')) ?: null,
            ':handoff_team_id' => ($data['handoff_team_id'] ?? null) ?: null,
            ':handoff_sent_at' => $data['handoff_sent_at'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    private function hasSessionsTable(): bool
    {
        if ($this->sessionsTableExists !== null) {
            return $this->sessionsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'chatwoot_flow_sessions'");
        $stmt->execute();
        $this->sessionsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->sessionsTableExists;
    }

    private function hasCompanyColumn(): bool
    {
        if ($this->companyColumnExists !== null) {
            return $this->companyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'chatwoot_flow_sessions'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->companyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->companyColumnExists;
    }
}
