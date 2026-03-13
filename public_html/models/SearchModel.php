<?php

class SearchModel extends BaseModel
{
    private ?bool $courseCompanyColumnExists = null;

    public function run(string $query): array
    {
        $like = '%' . $query . '%';
        $companyId = $this->companyId();

        $students = $this->db->prepare('SELECT id, full_name AS title, email_primary AS subtitle
            FROM students
            WHERE company_id = :company_id
              AND (full_name LIKE :q OR email_primary LIKE :q OR phone LIKE :q)
            ORDER BY id DESC
            LIMIT 10');
        $students->execute([
            ':company_id' => $companyId,
            ':q' => $like,
        ]);

        $leads = $this->db->prepare('SELECT id, full_name AS title, source AS subtitle
            FROM leads
            WHERE company_id = :company_id
              AND (full_name LIKE :q OR email LIKE :q OR phone LIKE :q)
            ORDER BY id DESC
            LIMIT 10');
        $leads->execute([
            ':company_id' => $companyId,
            ':q' => $like,
        ]);

        $invoices = $this->db->prepare('SELECT id, invoice_number AS title, status AS subtitle
            FROM invoices
            WHERE company_id = :company_id
              AND (invoice_number LIKE :q OR tags LIKE :q)
            ORDER BY id DESC
            LIMIT 10');
        $invoices->execute([
            ':company_id' => $companyId,
            ':q' => $like,
        ]);

        if ($this->hasCourseCompanyColumn()) {
            $courses = $this->db->prepare('SELECT id, name AS title, status AS subtitle
                FROM courses
                WHERE company_id = :company_id
                  AND (name LIKE :q OR description LIKE :q)
                ORDER BY id DESC
                LIMIT 10');
            $courses->execute([
                ':company_id' => $companyId,
                ':q' => $like,
            ]);
        } else {
            $courses = $this->db->prepare('SELECT id, name AS title, status AS subtitle
                FROM courses
                WHERE name LIKE :q OR description LIKE :q
                ORDER BY id DESC
                LIMIT 10');
            $courses->execute([':q' => $like]);
        }

        return [
            'students' => $students->fetchAll(),
            'leads' => $leads->fetchAll(),
            'invoices' => $invoices->fetchAll(),
            'courses' => $courses->fetchAll(),
        ];
    }

    private function hasCourseCompanyColumn(): bool
    {
        if ($this->courseCompanyColumnExists !== null) {
            return $this->courseCompanyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'courses'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->courseCompanyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->courseCompanyColumnExists;
    }
}
