<?php

abstract class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    protected function paginate(string $countSql, string $dataSql, array $params, int $perPage, int $page): array
    {
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $meta = pagination_meta($total, $perPage, $page);

        $offset = ($meta['page'] - 1) * $meta['per_page'];
        $dataStmt = $this->db->prepare($dataSql . ' LIMIT :limit OFFSET :offset');

        foreach ($params as $key => $value) {
            $dataStmt->bindValue(is_string($key) ? $key : $key + 1, $value);
        }
        $dataStmt->bindValue(':limit', $meta['per_page'], PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'rows' => $dataStmt->fetchAll(),
            'meta' => $meta,
        ];
    }

    protected function companyId(): int
    {
        return (int) (current_company_id() ?? 0);
    }

    protected function companyColumn(string $alias = ''): string
    {
        $alias = trim($alias);
        return $alias !== '' ? ($alias . '.company_id') : 'company_id';
    }
}
