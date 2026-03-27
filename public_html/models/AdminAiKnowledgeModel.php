<?php

class AdminAiKnowledgeModel extends BaseModel
{
    private array $tableExistsCache = [];

    public function buildContext(string $question): array
    {
        $question = trim($question);
        $searchTerms = $this->searchTerms($question);
        $intent = $this->detectIntent($question);
        $company = current_company() ?? [];
        $companyName = trim((string) ($company['trade_name'] ?? '')) !== ''
            ? (string) $company['trade_name']
            : (string) ($company['legal_name'] ?? ('Empresa #' . $this->companyId()));

        $summary = $this->summary();

        $matches = [
            'students' => $this->searchStudents($searchTerms, 6),
            'leads' => $this->searchLeads($searchTerms, 6),
            'invoices' => $this->searchInvoices($searchTerms, 6),
            'courses' => $this->searchCourses($searchTerms, 6),
            'course_enrollments' => $this->searchCourseEnrollments($searchTerms, 20),
            'payments' => $this->searchPayments($searchTerms, 6),
        ];

        // Buscas especializadas por intenção detectada na pergunta
        if ($intent['inadimplentes']) {
            $matches['alunos_inadimplentes'] = $this->searchDefaulters(10);
        }
        if ($intent['alunos_ativos']) {
            $matches['lista_alunos_ativos'] = $this->searchStudentsByStatus(true, 15);
        }
        if ($intent['alunos_inativos']) {
            $matches['lista_alunos_inativos'] = $this->searchStudentsByStatus(false, 15);
        }
        if ($intent['faturas_vencidas']) {
            $matches['faturas_vencidas_detalhe'] = $this->searchOverdueInvoicesDetailed(10);
        }
        if ($intent['recebimentos_mes']) {
            $matches['recebimentos_mes_atual'] = $this->searchRecentPayments(10);
        }
        if ($intent['leads_sem_contato']) {
            $matches['leads_sem_contato_recente'] = $this->searchLeadsWithoutRecentContact(10);
        }

        if ($this->hasTable('support_tickets')) {
            $matches['support_tickets'] = $this->searchSupportTickets($searchTerms, 6);
        } else {
            $matches['support_tickets'] = [];
        }

        // Remove seções com zero resultados — evita poluir o contexto com arrays vazios
        $matchesClean = array_filter($matches, fn ($rows) => is_array($rows) && count($rows) > 0);

        // Intenções ativas — ajudam o modelo a entender o foco da pergunta
        $intencoesAtivas = array_keys(array_filter($intent));

        // lead_status e invoice_status só entram no JSON quando são relevantes para a intenção detectada.
        // Em perguntas sem intenção específica ($intencoesAtivas vazio), ambos sempre entram.
        $incluirLeadStatus = $intencoesAtivas === []
            || in_array('leads_sem_contato', $intencoesAtivas, true)
            || in_array('inadimplentes', $intencoesAtivas, true);

        $incluirInvoiceStatus = $intencoesAtivas === []
            || in_array('inadimplentes', $intencoesAtivas, true)
            || in_array('faturas_vencidas', $intencoesAtivas, true)
            || in_array('recebimentos_mes', $intencoesAtivas, true);

        $payload = [
            'empresa' => [
                'id' => (int) ($company['id'] ?? $this->companyId()),
                'nome' => $companyName,
                'cnpj' => (string) ($company['cnpj'] ?? ''),
            ],
            'gerado_em' => now(),
            'resumo' => $summary,
            'intencoes_detectadas' => $intencoesAtivas,
            'lead_status' => $incluirLeadStatus ? $this->leadStatusBreakdown() : [],
            'invoice_status' => $incluirInvoiceStatus ? $this->invoiceStatusBreakdown() : [],
            'matches' => $matchesClean,
            'search_terms' => $searchTerms,
            'pergunta_usuario' => $question,
        ];

        $contextJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($contextJson === false) {
            $contextJson = '{}';
        }

        return [
            'summary' => $summary,
            'matches' => $matches,          // completo para uso interno do controller
            'sources' => $this->sourceLabels($matchesClean),
            'payload' => $payload,
            'context_json' => $contextJson,
        ];
    }

    private function summary(): array
    {
        $params = [':company_id' => $this->companyId()];

        $totalStudents = (int) $this->scalar('SELECT COUNT(*)
            FROM students
            WHERE company_id = :company_id', $params);

        $activeStudents = (int) $this->scalar('SELECT COUNT(*)
            FROM students
            WHERE company_id = :company_id
              AND is_active = 1', $params);

        $inactiveStudents = (int) $this->scalar('SELECT COUNT(*)
            FROM students
            WHERE company_id = :company_id
              AND is_active = 0', $params);

        $newStudents30d = (int) $this->scalar('SELECT COUNT(*)
            FROM students
            WHERE company_id = :company_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', $params);

        $totalLeads = (int) $this->scalar('SELECT COUNT(*)
            FROM leads
            WHERE company_id = :company_id', $params);

        $newLeads30d = (int) $this->scalar('SELECT COUNT(*)
            FROM leads
            WHERE company_id = :company_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', $params);

        $convertedLeads = (int) $this->scalar('SELECT COUNT(*)
            FROM leads
            WHERE company_id = :company_id
              AND converted_student_id IS NOT NULL', $params);

        $totalCourses = (int) $this->scalar('SELECT COUNT(*)
            FROM courses
            WHERE company_id = :company_id', $params);

        $publishedCourses = (int) $this->scalar("SELECT COUNT(*)
            FROM courses
            WHERE company_id = :company_id
              AND status = 'published'", $params);

        $activeEnrollments = (int) $this->scalar("SELECT COUNT(*)
            FROM enrollments e
            INNER JOIN students s ON s.id = e.student_id
            WHERE s.company_id = :company_id
              AND e.status = 'active'", $params);

        $invoicesOpenCount = (int) $this->scalar("SELECT COUNT(*)
            FROM invoices
            WHERE company_id = :company_id
              AND status IN ('open', 'partial', 'overdue')", $params);

        $invoicesOverdueCount = (int) $this->scalar("SELECT COUNT(*)
            FROM invoices
            WHERE company_id = :company_id
              AND status = 'overdue'", $params);

        $openBalance = $this->decimal($this->scalar("SELECT COALESCE(SUM(
                CASE WHEN amount > paid_amount THEN amount - paid_amount ELSE 0 END
            ), 0)
            FROM invoices
            WHERE company_id = :company_id
              AND status IN ('open', 'partial', 'overdue')", $params));

        $receivedThisMonth = $this->decimal($this->scalar("SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE company_id = :company_id
              AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", $params));

        $summary = [
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'inactive_students' => $inactiveStudents,
            'new_students_30d' => $newStudents30d,
            'total_leads' => $totalLeads,
            'new_leads_30d' => $newLeads30d,
            'converted_leads' => $convertedLeads,
            'total_courses' => $totalCourses,
            'published_courses' => $publishedCourses,
            'active_enrollments' => $activeEnrollments,
            'open_invoices' => $invoicesOpenCount,
            'overdue_invoices' => $invoicesOverdueCount,
            'open_balance' => $openBalance,
            'received_this_month' => $receivedThisMonth,
        ];

        if ($this->hasTable('support_tickets')) {
            $supportOpen = (int) $this->scalar("SELECT COUNT(*)
                FROM support_tickets
                WHERE company_id = :company_id
                  AND status = 'open'", $params);
            $supportInProgress = (int) $this->scalar("SELECT COUNT(*)
                FROM support_tickets
                WHERE company_id = :company_id
                  AND status = 'in_progress'", $params);
            $supportResolved = (int) $this->scalar("SELECT COUNT(*)
                FROM support_tickets
                WHERE company_id = :company_id
                  AND status = 'resolved'", $params);
            $supportClosed = (int) $this->scalar("SELECT COUNT(*)
                FROM support_tickets
                WHERE company_id = :company_id
                  AND status = 'closed'", $params);

            $summary['open_support_tickets'] = $supportOpen + $supportInProgress;
            $summary['support_open_tickets'] = $supportOpen;
            $summary['support_in_progress_tickets'] = $supportInProgress;
            $summary['support_resolved_tickets'] = $supportResolved;
            $summary['support_closed_tickets'] = $supportClosed;
        } else {
            $summary['open_support_tickets'] = 0;
            $summary['support_open_tickets'] = 0;
            $summary['support_in_progress_tickets'] = 0;
            $summary['support_resolved_tickets'] = 0;
            $summary['support_closed_tickets'] = 0;
        }

        return $summary;
    }

    private function searchTerms(string $question): array
    {
        $question = trim($question);
        if ($question === '') {
            return [];
        }

        $normalized = $this->lower($question);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;
        $chunks = preg_split('/\s+/', $normalized) ?: [];

        $stopwords = [
            'a', 'o', 'e', 'de', 'da', 'do', 'das', 'dos', 'na', 'no', 'nas', 'nos',
            'para', 'por', 'com', 'sem', 'que', 'quais', 'qual', 'quanto', 'quantos',
            'quantas', 'tem', 'temos', 'tenho', 'existe', 'existem', 'mostrar', 'mostre',
            'me', 'em', 'um', 'uma', 'as', 'os', 'ao', 'aos', 'sao', 'Ã©', 'eh', 'base',
            'banco', 'hoje', 'mes', 'mÃªs', 'sobre', 'algum', 'alguns', 'alguma', 'algumas',
            'dele', 'deles', 'dela', 'delas', 'isso', 'isto', 'esse', 'essa', 'esses', 'essas',
            'esta', 'estao', 'esta', 'estao', 'estar', 'seria', 'neles', 'nelas',
        ];

        $terms = [];
        foreach ($chunks as $token) {
            $token = trim($token);
            if ($token === '' || $this->length($token) < 3) {
                continue;
            }
            if (in_array($token, $stopwords, true)) {
                continue;
            }
            $terms[] = $token;

            if ($this->length($token) > 4 && str_ends_with($token, 's')) {
                $singular = substr($token, 0, -1);
                if ($singular !== '' && $this->length($singular) >= 3 && !in_array($singular, $stopwords, true)) {
                    $terms[] = $singular;
                }
            }
        }

        if ($terms === []) {
            return [];
        }

        $terms = array_values(array_unique($terms));
        usort($terms, fn ($a, $b) => $this->length($b) <=> $this->length($a));

        return array_slice($terms, 0, 5);
    }

    private function leadStatusBreakdown(): array
    {
        $stmt = $this->db->prepare("SELECT
                COALESCE(ls.name, 'Sem status') AS status_name,
                COUNT(*) AS total
            FROM leads l
            LEFT JOIN lead_status ls ON ls.id = l.lead_status_id
            WHERE l.company_id = :company_id
            GROUP BY COALESCE(ls.name, 'Sem status')
            ORDER BY total DESC, status_name ASC
            LIMIT 10");
        $stmt->execute([':company_id' => $this->companyId()]);

        return $stmt->fetchAll() ?: [];
    }

    private function invoiceStatusBreakdown(): array
    {
        $stmt = $this->db->prepare("SELECT
                status,
                COUNT(*) AS total,
                COALESCE(SUM(
                    CASE WHEN amount > paid_amount THEN amount - paid_amount ELSE 0 END
                ), 0) AS balance
            FROM invoices
            WHERE company_id = :company_id
            GROUP BY status
            ORDER BY total DESC");
        $stmt->execute([':company_id' => $this->companyId()]);

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['balance'] = $this->decimal($row['balance'] ?? 0);
        }

        return $rows;
    }

    private function searchStudents(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }

        [$termSql, $params] = $this->buildLikeWhere(
            ['full_name', 'email_primary', 'phone', 'ra', 'admin_info'],
            $terms,
            'st'
        );

        $stmt = $this->db->prepare('SELECT
                id,
                full_name,
                email_primary,
                phone,
                ra,
                monthly_fee,
                is_active,
                updated_at
            FROM students
            WHERE company_id = :company_id
              AND (' . $termSql . ')
            ORDER BY updated_at DESC, id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function searchLeads(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }

        [$termSql, $params] = $this->buildLikeWhere(
            ['l.full_name', 'l.email', 'l.phone', 'l.source', 'l.unit_name', 'l.tags', 'ls.name'],
            $terms,
            'ld'
        );

        $stmt = $this->db->prepare('SELECT
                l.id,
                l.full_name,
                l.email,
                l.phone,
                l.source,
                l.unit_name,
                l.lead_value,
                l.last_contact_at,
                ls.name AS status_name,
                l.updated_at
            FROM leads l
            LEFT JOIN lead_status ls ON ls.id = l.lead_status_id
            WHERE l.company_id = :company_id
              AND (' . $termSql . ')
            ORDER BY l.updated_at DESC, l.id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function searchInvoices(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }

        [$termSql, $params] = $this->buildLikeWhere(
            ['i.invoice_number', 'i.tags', 'i.project_name', 's.full_name'],
            $terms,
            'iv'
        );

        $stmt = $this->db->prepare('SELECT
                i.id,
                i.invoice_number,
                i.status,
                i.due_date,
                i.amount,
                i.paid_amount,
                i.project_name,
                i.tags,
                s.full_name AS student_name,
                i.updated_at
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            WHERE i.company_id = :company_id
              AND (' . $termSql . ')
            ORDER BY i.updated_at DESC, i.id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function searchCourses(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }

        [$termSql, $params] = $this->buildLikeWhere(
            ['c.name', 'c.description', 'cc.name'],
            $terms,
            'cr'
        );

        $stmt = $this->db->prepare('SELECT
                c.id,
                c.name,
                c.status,
                c.workload_hours,
                c.live_datetime,
                cc.name AS category_name,
                c.updated_at
            FROM courses c
            LEFT JOIN course_categories cc ON cc.id = c.category_id
            WHERE c.company_id = :company_id
              AND (' . $termSql . ')
            ORDER BY c.updated_at DESC, c.id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function searchCourseEnrollments(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }

        [$termSql, $params] = $this->buildLikeWhere(
            ['c.name', 'c.description', 'cc.name'],
            $terms,
            'ce'
        );

        $stmt = $this->db->prepare('SELECT
                e.id AS enrollment_id,
                e.status AS enrollment_status,
                e.updated_at,
                c.id AS course_id,
                c.name AS course_name,
                s.id AS student_id,
                s.full_name AS student_name
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            LEFT JOIN course_categories cc ON cc.id = c.category_id
            INNER JOIN students s ON s.id = e.student_id
            WHERE c.company_id = :company_id_course
              AND s.company_id = :company_id_student
              AND e.status = :enrollment_status
              AND (' . $termSql . ')
            ORDER BY c.name ASC, s.full_name ASC
            LIMIT :limit');
        $stmt->bindValue(':company_id_course', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':company_id_student', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':enrollment_status', 'active', PDO::PARAM_STR);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function searchPayments(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }

        [$termSql, $params] = $this->buildLikeWhere(
            ['payment_ref', 'method', 'notes'],
            $terms,
            'py'
        );

        $stmt = $this->db->prepare('SELECT
                id,
                payment_ref,
                method,
                amount,
                paid_at,
                notes,
                updated_at
            FROM payments
            WHERE company_id = :company_id
              AND (' . $termSql . ')
            ORDER BY updated_at DESC, id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['amount'] = $this->decimal($row['amount'] ?? 0);
        }

        return $rows;
    }

    private function searchSupportTickets(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }

        [$termSql, $params] = $this->buildLikeWhere(
            ['ticket_code', 'subject', 'description', 'requester_name', 'requester_email'],
            $terms,
            'tk'
        );

        $stmt = $this->db->prepare('SELECT
                id,
                ticket_code,
                subject,
                status,
                priority,
                requester_name,
                requester_email,
                updated_at
            FROM support_tickets
            WHERE company_id = :company_id
              AND (' . $termSql . ')
            ORDER BY updated_at DESC, id DESC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Detecção de intenção e buscas especializadas
    // -------------------------------------------------------------------------

    /**
     * Detecta intenções semânticas na pergunta do usuário para acionar
     * queries especializadas além da busca LIKE por termos.
     */
    private function detectIntent(string $question): array
    {
        $n = $this->normalizeText($question);

        $ehAluno = str_contains($n, 'alun');
        $ehFatura = str_contains($n, 'fatura') || str_contains($n, 'conta') || str_contains($n, 'boleto') || str_contains($n, 'titulo');
        $ehPagamento = str_contains($n, 'pagamento') || str_contains($n, 'recebimento') || str_contains($n, 'recebid') || str_contains($n, 'entrada');
        $ehAtraso = str_contains($n, 'atraso') || str_contains($n, 'atrasad') || str_contains($n, 'vencid') || str_contains($n, 'overdue');
        $ehMes = str_contains($n, 'mes') || str_contains($n, 'hoje') || str_contains($n, 'semana') || str_contains($n, 'period') || str_contains($n, 'atual');
        $ehLead = str_contains($n, 'lead');
        $ehContato = str_contains($n, 'contat') || str_contains($n, 'aguardando') || str_contains($n, 'sem retorno');

        return [
            // Ex: "quais alunos estão inadimplentes?", "alunos devendo", "em atraso nos pagamentos"
            'inadimplentes' => str_contains($n, 'inadimplent')
                || str_contains($n, 'devend')
                || ($ehAluno && $ehAtraso)
                || ($ehFatura && $ehAtraso && $ehAluno),

            // Ex: "listar alunos ativos", "quantos alunos ativos temos"
            'alunos_ativos' => $ehAluno
                && str_contains($n, 'ativ')
                && !str_contains($n, 'inativ'),

            // Ex: "quais alunos estão inativos", "lista de alunos inativos"
            'alunos_inativos' => $ehAluno && str_contains($n, 'inativ'),

            // Ex: "faturas vencidas", "contas em atraso", "boletos vencidos"
            'faturas_vencidas' => $ehFatura && $ehAtraso && !$ehAluno,

            // Ex: "recebimentos deste mês", "pagamentos de hoje", "entradas no mês"
            'recebimentos_mes' => ($ehPagamento || str_contains($n, 'receb')) && $ehMes,

            // Ex: "leads sem contato", "leads aguardando retorno", "quem ainda não contactamos"
            'leads_sem_contato' => $ehLead && ($ehContato || str_contains($n, 'sem retorno') || str_contains($n, 'nao contact') || str_contains($n, 'precis')),
        ];
    }

    /**
     * Alunos ativos com faturas vencidas (inadimplentes), ordenados pelo maior saldo devedor.
     */
    private function searchDefaulters(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT
                s.id,
                s.full_name,
                s.email_primary,
                s.phone,
                COUNT(i.id)                                                    AS faturas_vencidas,
                COALESCE(SUM(i.amount - COALESCE(i.paid_amount, 0)), 0)        AS saldo_devedor,
                MIN(i.due_date)                                                AS vencimento_mais_antigo
            FROM students s
            INNER JOIN invoices i ON i.student_id = s.id AND i.company_id = :company_id2
            WHERE s.company_id = :company_id
              AND i.status = 'overdue'
              AND s.is_active = 1
            GROUP BY s.id, s.full_name, s.email_primary, s.phone
            ORDER BY saldo_devedor DESC, s.full_name ASC
            LIMIT :limit");
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':company_id2', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['saldo_devedor'] = $this->decimal($row['saldo_devedor'] ?? 0);
        }

        return $rows;
    }

    /**
     * Lista de alunos filtrados por status ativo/inativo.
     */
    private function searchStudentsByStatus(bool $active, int $limit): array
    {
        $stmt = $this->db->prepare('SELECT
                id,
                full_name,
                email_primary,
                phone,
                ra,
                monthly_fee,
                is_active,
                updated_at
            FROM students
            WHERE company_id = :company_id
              AND is_active = :is_active
            ORDER BY full_name ASC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':is_active', $active ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Faturas vencidas detalhadas com nome e contato do aluno, ordenadas da mais antiga.
     */
    private function searchOverdueInvoicesDetailed(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT
                i.id,
                i.invoice_number,
                i.due_date,
                i.amount,
                COALESCE(i.paid_amount, 0)                                    AS paid_amount,
                (i.amount - COALESCE(i.paid_amount, 0))                       AS saldo_devedor,
                i.status,
                s.full_name   AS student_name,
                s.phone       AS student_phone,
                s.email_primary AS student_email
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            WHERE i.company_id = :company_id
              AND i.status = 'overdue'
            ORDER BY i.due_date ASC
            LIMIT :limit");
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['amount']       = $this->decimal($row['amount'] ?? 0);
            $row['paid_amount']  = $this->decimal($row['paid_amount'] ?? 0);
            $row['saldo_devedor'] = $this->decimal($row['saldo_devedor'] ?? 0);
        }

        return $rows;
    }

    /**
     * Pagamentos recebidos no mês atual.
     */
    private function searchRecentPayments(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT
                id,
                payment_ref,
                method,
                amount,
                paid_at,
                notes,
                updated_at
            FROM payments
            WHERE company_id = :company_id
              AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY paid_at DESC
            LIMIT :limit");
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['amount'] = $this->decimal($row['amount'] ?? 0);
        }

        return $rows;
    }

    /**
     * Leads não convertidos sem contato há mais de 7 dias, ordenados pelo mais antigo.
     */
    private function searchLeadsWithoutRecentContact(int $limit): array
    {
        $stmt = $this->db->prepare('SELECT
                l.id,
                l.full_name,
                l.email,
                l.phone,
                l.source,
                l.unit_name,
                l.last_contact_at,
                ls.name AS status_name
            FROM leads l
            LEFT JOIN lead_status ls ON ls.id = l.lead_status_id
            WHERE l.company_id = :company_id
              AND l.converted_student_id IS NULL
              AND (l.last_contact_at IS NULL
                   OR l.last_contact_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
            ORDER BY l.last_contact_at ASC, l.created_at ASC
            LIMIT :limit');
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Normaliza texto para comparação de intenções: minúsculas + remove acentos e pontuação.
     */
    private function normalizeText(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && trim($converted) !== '') {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;

        return trim((string) (preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function buildLikeWhere(array $columns, array $terms, string $prefix): array
    {
        $columns = array_values(array_filter(array_map('trim', $columns), fn ($c) => $c !== ''));
        $terms = array_values(array_unique(array_filter(array_map('trim', $terms), fn ($t) => $t !== '')));

        if ($columns === [] || $terms === []) {
            return ['1=0', []];
        }

        $parts = [];
        $params = [];

        foreach ($terms as $i => $term) {
            $termParam = ':' . $prefix . '_t' . $i;
            $orCols = [];
            foreach ($columns as $column) {
                $orCols[] = $column . ' LIKE ' . $termParam;
            }
            $parts[] = '(' . implode(' OR ', $orCols) . ')';
            $params[$termParam] = '%' . $term . '%';
        }

        return [implode(' OR ', $parts), $params];
    }

    private function sourceLabels(array $matches): array
    {
        $labels = [];
        foreach ($matches as $key => $rows) {
            $count = is_array($rows) ? count($rows) : 0;
            if ($count <= 0) {
                continue;
            }
            $labels[] = $key . ': ' . $count;
        }
        return $labels;
    }

    private function hasTable(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name");
        $stmt->execute([':table_name' => $table]);
        $exists = ((int) $stmt->fetchColumn()) > 0;
        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function decimal($value): float
    {
        return round((float) $value, 2);
    }

    private function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    private function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return (int) strlen($value);
    }
}


