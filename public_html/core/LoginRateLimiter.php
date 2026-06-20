<?php

class LoginRateLimiter
{
    private const IDENTIFIER_LIMIT = 5;
    private const IDENTIFIER_WINDOW_SECONDS = 900;
    private const IDENTIFIER_BLOCK_SECONDS = 900;
    private const IP_LIMIT = 300;
    private const IP_WINDOW_SECONDS = 300;
    private const IP_BLOCK_SECONDS = 300;

    public static function check(string $scope, string $identifier): array
    {
        $now = time();
        $identifierState = self::readState(self::key($scope, 'identifier', self::normalizeIdentifier($identifier)));
        $ipState = self::readState(self::key($scope, 'ip', self::clientIp()));

        $identifierRetry = self::retryAfter($identifierState, $now);
        $ipRetry = self::retryAfter($ipState, $now);
        $retryAfter = max($identifierRetry, $ipRetry);

        return [
            'allowed' => $retryAfter <= 0,
            'retry_after' => $retryAfter,
        ];
    }

    public static function recordFailure(string $scope, string $identifier): void
    {
        self::increment(
            self::key($scope, 'identifier', self::normalizeIdentifier($identifier)),
            self::IDENTIFIER_LIMIT,
            self::IDENTIFIER_WINDOW_SECONDS,
            self::IDENTIFIER_BLOCK_SECONDS
        );
        self::increment(
            self::key($scope, 'ip', self::clientIp()),
            self::IP_LIMIT,
            self::IP_WINDOW_SECONDS,
            self::IP_BLOCK_SECONDS
        );
    }

    public static function clear(string $scope, string $identifier): void
    {
        self::deleteState(self::key($scope, 'identifier', self::normalizeIdentifier($identifier)));
    }

    private static function increment(string $key, int $limit, int $windowSeconds, int $blockSeconds): void
    {
        $now = time();
        self::withLockedState($key, static function (array $state) use ($now, $limit, $windowSeconds, $blockSeconds): array {
            $windowStartedAt = (int) ($state['window_started_at'] ?? 0);
            if ($windowStartedAt <= 0 || ($now - $windowStartedAt) >= $windowSeconds) {
                $state = [
                    'attempts' => 0,
                    'window_started_at' => $now,
                    'blocked_until' => 0,
                ];
            }

            if ((int) ($state['blocked_until'] ?? 0) > $now) {
                return $state;
            }

            $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
            if ($state['attempts'] >= $limit) {
                $state['blocked_until'] = $now + $blockSeconds;
            }

            return $state;
        });
    }

    private static function retryAfter(array $state, int $now): int
    {
        return max(0, (int) ($state['blocked_until'] ?? 0) - $now);
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim(mb_strtolower($identifier, 'UTF-8'));
        return $identifier !== '' ? $identifier : '[empty]';
    }

    private static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }

    private static function key(string $scope, string $type, string $value): string
    {
        return hash('sha256', trim($scope) . '|' . trim($type) . '|' . $value);
    }

    private static function storageDir(): string
    {
        $configured = trim((string) config('security.rate_limit_dir', ''));
        $directory = $configured !== ''
            ? $configured
            : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'aneo-login-rate-limit';

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o controle de tentativas de login.');
        }

        return $directory;
    }

    private static function filePath(string $key): string
    {
        return self::storageDir() . DIRECTORY_SEPARATOR . $key . '.json';
    }

    private static function readState(string $key): array
    {
        $path = self::filePath($key);
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function deleteState(string $key): void
    {
        $path = self::filePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function withLockedState(string $key, callable $callback): void
    {
        $path = self::filePath($key);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível registrar a tentativa de login.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Não foi possível bloquear o registro de tentativas.');
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $decoded = json_decode((string) $raw, true);
            $state = is_array($decoded) ? $decoded : [];
            $updated = $callback($state);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($updated, JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
