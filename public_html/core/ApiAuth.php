<?php

/**
 * Autenticação e autorização para endpoints da API REST.
 * Não usa sessão PHP — opera exclusivamente via Bearer Token.
 */
class ApiAuth
{
    /**
     * Resolve o Bearer Token do header Authorization.
     * Em caso de falha responde 401 e encerra a execução.
     *
     * @return array Token row com 'permissions' já decodificado e 'company_id' disponível.
     */
    public static function resolve(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            self::abort(401, 'Token de autenticacao ausente. Informe o header: Authorization: Bearer <token>');
        }

        $rawToken = substr($header, 7);

        if ($rawToken === '') {
            self::abort(401, 'Token de autenticacao vazio.');
        }

        $model = new ApiTokenModel();
        $token = $model->findByToken($rawToken);

        if ($token === null) {
            self::abort(401, 'Token invalido ou expirado.');
        }

        // Atualiza last_used_at de forma assíncrona (best-effort)
        try {
            $model->touchLastUsed((int) $token['id']);
        } catch (Throwable) {
            // Não bloquear a requisição por falha em log
        }

        return $token;
    }

    /**
     * Verifica se o token tem permissão para o recurso + capability.
     */
    public static function hasPermission(array $token, string $resource, string $cap): bool
    {
        $permissions = $token['permissions'] ?? [];
        if (!is_array($permissions)) {
            return false;
        }

        $caps = $permissions[$resource] ?? [];
        return in_array($cap, (array) $caps, true);
    }

    /**
     * Exige a permissão; aborta com 403 se não tiver.
     */
    public static function requirePermission(array $token, string $resource, string $cap): void
    {
        if (!self::hasPermission($token, $resource, $cap)) {
            self::abort(403, "Permissao insuficiente: {$resource}.{$cap}");
        }
    }

    /**
     * Emite resposta JSON de erro e encerra.
     */
    public static function abort(int $status, string $message): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['ok' => false, 'message' => $message, 'code' => $status],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}
