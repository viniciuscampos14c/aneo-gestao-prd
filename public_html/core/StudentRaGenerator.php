<?php

class StudentRaGenerator
{
    private const PREFIX_BY_KEYWORD = [
        'brasilia' => '10',
        'palmas' => '20',
        'bahia' => '30',
        'rio de janeiro' => '40',
        'rj' => '40',
        'goiania' => '50',
        'sao paulo' => '60',
    ];

    public static function prefixForCompany(PDO $db, int $companyId): ?string
    {
        if ($companyId <= 0) {
            return null;
        }

        $stmt = $db->prepare('SELECT legal_name, trade_name FROM companies WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            return null;
        }

        return self::prefixForCompanyName(
            (string) ($company['legal_name'] ?? ''),
            (string) ($company['trade_name'] ?? '')
        );
    }

    public static function prefixForCompanyName(string $legalName, string $tradeName = ''): ?string
    {
        $name = self::normalize($legalName . ' ' . $tradeName);
        foreach (self::PREFIX_BY_KEYWORD as $keyword => $prefix) {
            if (str_contains($name, $keyword)) {
                return $prefix;
            }
        }

        return null;
    }

    public static function nextForCompany(PDO $db, int $companyId): ?string
    {
        $prefix = self::prefixForCompany($db, $companyId);
        if ($prefix === null) {
            return null;
        }

        $stmt = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(ra, 3) AS UNSIGNED)), 0)
            FROM students
            WHERE company_id = :company_id
              AND ra REGEXP :pattern");
        $stmt->execute([
            ':company_id' => $companyId,
            ':pattern' => '^' . $prefix . '[0-9]{4}$',
        ]);

        $next = ((int) $stmt->fetchColumn()) + 1;
        return self::format($prefix, $next);
    }

    public static function format(string $prefix, int $sequence): string
    {
        return $prefix . str_pad((string) max(1, $sequence), 4, '0', STR_PAD_LEFT);
    }

    private static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
