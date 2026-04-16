<?php

class ApiTokenModel extends BaseModel
{
    // Recursos disponíveis e suas capabilities
    public const RESOURCES = [
        'students' => ['get', 'search', 'create', 'update', 'delete'],
        'leads'    => ['get', 'search', 'create', 'update', 'delete'],
        'invoices' => ['get', 'search', 'create', 'delete'],
        'courses'  => ['get', 'search'],
        'users'    => ['get', 'search'],
        'tickets'  => ['get', 'search', 'create'],
    ];

    public const RESOURCE_LABELS = [
        'students' => 'Alunos',
        'leads'    => 'Leads',
        'invoices' => 'Faturas',
        'courses'  => 'Cursos',
        'users'    => 'Usuários',
        'tickets'  => 'Chamados',
    ];

    public const CAP_LABELS = [
        'get'    => 'Get',
        'search' => 'Search',
        'create' => 'Criar',
        'update' => 'Update',
        'delete' => 'Deletar',
    ];

    public function list(int $companyId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.name AS user_name
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.company_id = :company_id
             ORDER BY t.id DESC'
        );
        $stmt->execute([':company_id' => $companyId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $companyId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.name AS user_name
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.id = :id AND t.company_id = :company_id'
        );
        $stmt->execute([':id' => $id, ':company_id' => $companyId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['permissions'] = $this->decodePermissions($row['permissions']);
        return $row;
    }

    public function findByToken(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $stmt = $this->db->prepare(
            'SELECT * FROM api_tokens WHERE token_hash = :hash'
        );
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        // Verificar expiração
        if ($row['expires_at'] !== null && $row['expires_at'] < date('Y-m-d')) {
            return null;
        }

        $row['permissions'] = $this->decodePermissions($row['permissions']);
        return $row;
    }

    public function create(array $data): array
    {
        $raw = $this->generateRaw();
        $hash = hash('sha256', $raw);
        $permissions = $this->encodePermissions($data['permissions'] ?? []);

        $stmt = $this->db->prepare(
            'INSERT INTO api_tokens (company_id, user_id, name, token_hash, permissions, expires_at)
             VALUES (:company_id, :user_id, :name, :token_hash, :permissions, :expires_at)'
        );
        $stmt->execute([
            ':company_id' => (int) $data['company_id'],
            ':user_id'    => (int) $data['user_id'],
            ':name'       => trim((string) $data['name']),
            ':token_hash' => $hash,
            ':permissions'=> $permissions,
            ':expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
        ]);

        return [
            'id'        => (int) $this->db->lastInsertId(),
            'raw_token' => $raw,
        ];
    }

    public function update(int $id, array $data, int $companyId): void
    {
        $permissions = $this->encodePermissions($data['permissions'] ?? []);

        $stmt = $this->db->prepare(
            'UPDATE api_tokens
             SET name = :name, user_id = :user_id, permissions = :permissions, expires_at = :expires_at
             WHERE id = :id AND company_id = :company_id'
        );
        $stmt->execute([
            ':name'       => trim((string) $data['name']),
            ':user_id'    => (int) $data['user_id'],
            ':permissions'=> $permissions,
            ':expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
            ':id'         => $id,
            ':company_id' => $companyId,
        ]);
    }

    public function delete(int $id, int $companyId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM api_tokens WHERE id = :id AND company_id = :company_id'
        );
        $stmt->execute([':id' => $id, ':company_id' => $companyId]);
    }

    public function touchLastUsed(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function generateRaw(): string
    {
        return bin2hex(random_bytes(32));
    }

    // ---------------------------------------------------------------------------

    private function decodePermissions(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encodePermissions(array $permissions): string
    {
        // Garante que só salva recursos e capabilities válidos
        $clean = [];
        foreach (self::RESOURCES as $resource => $validCaps) {
            $caps = array_values(array_intersect(
                (array) ($permissions[$resource] ?? []),
                $validCaps
            ));
            if ($caps !== []) {
                $clean[$resource] = $caps;
            }
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }
}
