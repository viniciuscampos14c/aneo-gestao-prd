<?php

class DashboardModel extends BaseModel
{
    public function metrics(): array
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return [
                'students_total' => 0,
                'students_active' => 0,
                'students_inactive' => 0,
                'leads_total' => 0,
                'invoices_open' => 0,
                'invoices_paid' => 0,
                'receivable' => 0.0,
                'lead_pipeline' => [],
                'kanban' => [],
                'bi' => $this->emptyBi(),
            ];
        }

        $metrics = [];
        $metrics['students_total'] = (int) $this->scalar('SELECT COUNT(*) FROM students WHERE company_id = :company_id', [':company_id' => $companyId]);
        $metrics['students_active'] = (int) $this->scalar('SELECT COUNT(*) FROM students WHERE company_id = :company_id AND is_active = 1', [':company_id' => $companyId]);
        $metrics['students_inactive'] = (int) $this->scalar('SELECT COUNT(*) FROM students WHERE company_id = :company_id AND is_active = 0', [':company_id' => $companyId]);

        $metrics['leads_total'] = (int) $this->scalar('SELECT COUNT(*) FROM leads WHERE company_id = :company_id', [':company_id' => $companyId]);
        $metrics['invoices_open'] = (int) $this->scalar("SELECT COUNT(*) FROM invoices WHERE company_id = :company_id AND status IN ('open','partial','overdue')", [':company_id' => $companyId]);
        $metrics['invoices_paid'] = (int) $this->scalar("SELECT COUNT(*) FROM invoices WHERE company_id = :company_id AND status = 'paid'", [':company_id' => $companyId]);

        $metrics['receivable'] = (float) $this->scalar(
            "SELECT COALESCE(SUM(amount - paid_amount), 0)
            FROM invoices
            WHERE company_id = :company_id
              AND status IN ('open','partial','overdue')",
            [':company_id' => $companyId]
        );

        $pipelineStmt = $this->db->prepare('SELECT
                s.name,
                s.color,
                COUNT(l.id) AS qty
            FROM lead_status s
            LEFT JOIN leads l ON l.lead_status_id = s.id AND l.company_id = :company_id
            GROUP BY s.id
            ORDER BY s.display_order ASC');
        $pipelineStmt->execute([':company_id' => $companyId]);
        $metrics['lead_pipeline'] = $pipelineStmt->fetchAll();

        $kanbanStmt = $this->db->prepare('SELECT
                s.name,
                s.color,
                COUNT(st.id) AS qty
            FROM kanban_status s
            LEFT JOIN students st ON st.kanban_status_id = s.id AND st.company_id = :company_id
            GROUP BY s.id
            ORDER BY s.display_order ASC');
        $kanbanStmt->execute([':company_id' => $companyId]);
        $metrics['kanban'] = $kanbanStmt->fetchAll();

        $metrics['bi'] = $this->managerialBi($companyId);

        return $metrics;
    }

    private function managerialBi(int $companyId): array
    {
        $overview = [
            'leads_conversion_rate' => 0.0,
            'leads_converted' => 0,
            'leads_total' => 0,
            'revenue_received_30d' => 0.0,
            'revenue_forecast_30d' => 0.0,
            'overdue_amount' => 0.0,
            'open_amount' => 0.0,
            'delinquency_rate' => 0.0,
            'enrollments_avg_progress' => 0.0,
            'exam_approval_rate' => 0.0,
            'exam_results_total' => 0,
        ];

        $leadsStmt = $this->db->prepare("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN converted_student_id IS NOT NULL THEN 1 ELSE 0 END) AS converted
            FROM leads
            WHERE company_id = :company_id");
        $leadsStmt->execute([':company_id' => $companyId]);
        $leadsRow = $leadsStmt->fetch() ?: [];
        $overview['leads_total'] = (int) ($leadsRow['total'] ?? 0);
        $overview['leads_converted'] = (int) ($leadsRow['converted'] ?? 0);
        if ($overview['leads_total'] > 0) {
            $overview['leads_conversion_rate'] = round(($overview['leads_converted'] / $overview['leads_total']) * 100, 2);
        }

        $receivedStmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE company_id = :company_id
              AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $receivedStmt->execute([':company_id' => $companyId]);
        $overview['revenue_received_30d'] = (float) $receivedStmt->fetchColumn();

        $forecastStmt = $this->db->prepare("SELECT COALESCE(SUM(GREATEST(amount - paid_amount, 0)), 0)
            FROM invoices
            WHERE company_id = :company_id
              AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND status IN ('open', 'partial', 'overdue')");
        $forecastStmt->execute([':company_id' => $companyId]);
        $overview['revenue_forecast_30d'] = (float) $forecastStmt->fetchColumn();

        $overdueStmt = $this->db->prepare("SELECT COALESCE(SUM(GREATEST(amount - paid_amount, 0)), 0)
            FROM invoices
            WHERE company_id = :company_id
              AND status = 'overdue'");
        $overdueStmt->execute([':company_id' => $companyId]);
        $overview['overdue_amount'] = (float) $overdueStmt->fetchColumn();

        $openStmt = $this->db->prepare("SELECT COALESCE(SUM(GREATEST(amount - paid_amount, 0)), 0)
            FROM invoices
            WHERE company_id = :company_id
              AND status IN ('open', 'partial', 'overdue')");
        $openStmt->execute([':company_id' => $companyId]);
        $overview['open_amount'] = (float) $openStmt->fetchColumn();
        if ($overview['open_amount'] > 0) {
            $overview['delinquency_rate'] = round(($overview['overdue_amount'] / $overview['open_amount']) * 100, 2);
        }

        $progressStmt = $this->db->prepare("SELECT COALESCE(AVG(e.progress_percent), 0)
            FROM enrollments e
            INNER JOIN students s ON s.id = e.student_id
            WHERE s.company_id = :company_id");
        $progressStmt->execute([':company_id' => $companyId]);
        $overview['enrollments_avg_progress'] = (float) $progressStmt->fetchColumn();

        $examStmt = $this->db->prepare("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) AS approved
            FROM exam_results r
            INNER JOIN students s ON s.id = r.student_id
            WHERE s.company_id = :company_id");
        $examStmt->execute([':company_id' => $companyId]);
        $examRow = $examStmt->fetch() ?: [];
        $overview['exam_results_total'] = (int) ($examRow['total'] ?? 0);
        $approved = (int) ($examRow['approved'] ?? 0);
        if ($overview['exam_results_total'] > 0) {
            $overview['exam_approval_rate'] = round(($approved / $overview['exam_results_total']) * 100, 2);
        }

        $monthlyStmt = $this->db->prepare("SELECT
                month_ref,
                SUM(invoiced_total) AS invoiced_total,
                SUM(received_total) AS received_total
            FROM (
                SELECT DATE_FORMAT(i.due_date, '%Y-%m') AS month_ref, SUM(i.amount) AS invoiced_total, 0 AS received_total
                FROM invoices i
                WHERE i.company_id = :company_id_invoices
                  AND i.due_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
                GROUP BY DATE_FORMAT(i.due_date, '%Y-%m')

                UNION ALL

                SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS month_ref, 0 AS invoiced_total, SUM(p.amount) AS received_total
                FROM payments p
                WHERE p.company_id = :company_id_payments
                  AND p.paid_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
                GROUP BY DATE_FORMAT(p.paid_at, '%Y-%m')
            ) x
            GROUP BY month_ref
            ORDER BY month_ref ASC");
        $monthlyStmt->execute([
            ':company_id_invoices' => $companyId,
            ':company_id_payments' => $companyId,
        ]);
        $monthly = $monthlyStmt->fetchAll();

        $series = [];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime('-' . $i . ' month'));
            $months[$key] = [
                'month' => $key,
                'label' => date('m/Y', strtotime($key . '-01')),
                'invoiced' => 0.0,
                'received' => 0.0,
            ];
        }

        foreach ($monthly as $row) {
            $key = (string) ($row['month_ref'] ?? '');
            if ($key !== '' && isset($months[$key])) {
                $months[$key]['invoiced'] = (float) ($row['invoiced_total'] ?? 0);
                $months[$key]['received'] = (float) ($row['received_total'] ?? 0);
            }
        }

        foreach ($months as $row) {
            $series[] = $row;
        }

        $coursesStmt = $this->db->prepare("SELECT
                c.id,
                c.name,
                COALESCE(en.enrollments_total, 0) AS enrollments_total,
                COALESCE(en.avg_progress, 0) AS avg_progress,
                COALESCE(exr.exam_results_total, 0) AS exam_results_total,
                COALESCE(exr.exam_approved_total, 0) AS exam_approved_total
            FROM courses c
            LEFT JOIN (
                SELECT
                    e.course_id,
                    COUNT(*) AS enrollments_total,
                    AVG(e.progress_percent) AS avg_progress
                FROM enrollments e
                INNER JOIN students st ON st.id = e.student_id
                WHERE st.company_id = :company_id_enrollments
                GROUP BY e.course_id
            ) en ON en.course_id = c.id
            LEFT JOIN (
                SELECT
                    ex.course_id,
                    COUNT(r.id) AS exam_results_total,
                    SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) AS exam_approved_total
                FROM exams ex
                LEFT JOIN exam_results r ON r.exam_id = ex.id
                LEFT JOIN students st ON st.id = r.student_id
                WHERE st.company_id = :company_id_exams
                GROUP BY ex.course_id
            ) exr ON exr.course_id = c.id
            WHERE COALESCE(en.enrollments_total, 0) > 0
            ORDER BY enrollments_total DESC, c.name ASC
            LIMIT 8");
        $coursesStmt->execute([
            ':company_id_enrollments' => $companyId,
            ':company_id_exams' => $companyId,
        ]);
        $courses = $coursesStmt->fetchAll();

        $coursesPerformance = [];
        foreach ($courses as $row) {
            $resultsTotal = (int) ($row['exam_results_total'] ?? 0);
            $approvedTotal = (int) ($row['exam_approved_total'] ?? 0);
            $approvalRate = $resultsTotal > 0 ? round(($approvedTotal / $resultsTotal) * 100, 2) : 0.0;

            $coursesPerformance[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'enrollments_total' => (int) ($row['enrollments_total'] ?? 0),
                'avg_progress' => (float) ($row['avg_progress'] ?? 0),
                'exam_results_total' => $resultsTotal,
                'exam_approval_rate' => $approvalRate,
            ];
        }

        return [
            'overview' => $overview,
            'monthly_series' => $series,
            'courses_performance' => $coursesPerformance,
        ];
    }

    private function emptyBi(): array
    {
        return [
            'overview' => [
                'leads_conversion_rate' => 0.0,
                'leads_converted' => 0,
                'leads_total' => 0,
                'revenue_received_30d' => 0.0,
                'revenue_forecast_30d' => 0.0,
                'overdue_amount' => 0.0,
                'open_amount' => 0.0,
                'delinquency_rate' => 0.0,
                'enrollments_avg_progress' => 0.0,
                'exam_approval_rate' => 0.0,
                'exam_results_total' => 0,
            ],
            'monthly_series' => [],
            'courses_performance' => [],
        ];
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
