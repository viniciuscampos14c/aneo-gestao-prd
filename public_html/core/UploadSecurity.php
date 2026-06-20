<?php

class UploadSecurity
{
    private const EICAR_MARKER = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!';

    private const MIME_BY_EXTENSION = [
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'gif' => ['image/gif'],
        'jpeg' => ['image/jpeg'],
        'jpg' => ['image/jpeg'],
        'mp3' => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
        'mp4' => ['video/mp4', 'application/octet-stream'],
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'rar' => ['application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
        'txt' => ['text/plain', 'application/octet-stream'],
        'webp' => ['image/webp'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];

    public static function validate(array $file, array $allowedExtensions, int $maxBytes): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return self::failure('Falha no envio do arquivo.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath) || !is_file($tmpPath)) {
            return self::failure('Arquivo de upload inválido.');
        }

        $size = (int) ($file['size'] ?? filesize($tmpPath) ?: 0);
        if ($size <= 0) {
            return self::failure('O arquivo está vazio.');
        }
        if ($size > $maxBytes) {
            return self::failure('O arquivo ultrapassa o limite permitido.');
        }

        $originalName = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = array_values(array_unique(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $allowedExtensions
        )));
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            return self::failure('Extensão de arquivo não permitida.');
        }

        $mime = self::detectMime($tmpPath);
        $allowedMimes = self::MIME_BY_EXTENSION[$extension] ?? [];
        if ($mime === '' || ($allowedMimes !== [] && !in_array($mime, $allowedMimes, true))) {
            return self::failure('O conteúdo do arquivo não corresponde ao formato informado.');
        }

        $sample = file_get_contents($tmpPath, false, null, 0, min($size, 131072));
        $sample = is_string($sample) ? $sample : '';
        if (str_contains($sample, self::EICAR_MARKER)) {
            return self::failure('O arquivo foi bloqueado pela verificação de segurança.');
        }

        if (
            preg_match('/<\?(?:php|=)/i', $sample) === 1
            || preg_match('/<script[^>]+language\s*=\s*["\']?php/i', $sample) === 1
        ) {
            return self::failure('O arquivo contém conteúdo executável não permitido.');
        }

        return [
            'ok' => true,
            'message' => '',
            'extension' => $extension,
            'mime' => $mime,
            'size' => $size,
            'original_name' => $originalName,
            'tmp_path' => $tmpPath,
        ];
    }

    private static function detectMime(string $path): string
    {
        if (!function_exists('finfo_open')) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        try {
            $mime = finfo_file($finfo, $path);
            return is_string($mime) ? strtolower(trim($mime)) : '';
        } finally {
            finfo_close($finfo);
        }
    }

    private static function failure(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
        ];
    }
}
