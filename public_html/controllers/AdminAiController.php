<?php

class AdminAiController extends BaseController
{
    private AdminAiChatModel $chat;
    private AdminAiKnowledgeModel $knowledge;
    private AdminAiService $assistant;

    public function __construct()
    {
        $this->chat = new AdminAiChatModel();
        $this->knowledge = new AdminAiKnowledgeModel();
        $this->assistant = new AdminAiService();
    }

    public function index(): void
    {
        require_auth();
        require_permission('help');

        $userId = (int) (current_user()['id'] ?? 0);
        $sessionId = max(0, (int) request('session_id', 0));
        $historyAvailable = $this->chat->featureAvailable();

        $sessions = [];
        $messages = [];

        if ($historyAvailable) {
            $sessions = $this->chat->listSessions($userId, 30);

            if ($sessionId > 0 && !$this->chat->findSession($sessionId, $userId)) {
                $sessionId = 0;
            }

            if ($sessionId <= 0 && $sessions !== []) {
                $sessionId = (int) ($sessions[0]['id'] ?? 0);
            }

            if ($sessionId > 0) {
                $messages = $this->chat->listMessages($sessionId, $userId, 80);
            }
        } else {
            $messages = $this->memoryMessages(80);
            $sessionId = 0;
        }

        $this->render('admin_ai/index', [
            'title' => 'Assistente IA',
            'sessions' => $sessions,
            'messages' => $messages,
            'sessionId' => $sessionId,
            'historyAvailable' => $historyAvailable,
            'aiEnabled' => $this->assistant->isEnabled(),
            'aiConfigured' => $this->assistant->isConfigured(),
            'aiProvider' => $this->assistant->provider(),
            'aiModel' => $this->assistant->model(),
        ]);
    }

    public function createSession(): void
    {
        require_auth();
        require_permission('help');
        csrf_validate();

        $requestedReturn = trim((string) post('return_route', 'ai-chat'));
        $returnRoute = in_array($requestedReturn, ['help', 'ai-chat'], true) ? $requestedReturn : 'ai-chat';
        $sessionParam = $returnRoute === 'help' ? 'chat_session_id' : 'session_id';

        if (!$this->chat->featureAvailable()) {
            $this->error('Histórico de chat indisponivel. Execute a migração 20260310_admin_ai_chat.sql.');
            $this->redirect($returnRoute);
        }

        $userId = (int) (current_user()['id'] ?? 0);
        $title = trim((string) post('title', 'Novo chat'));
        if ($title === '') {
            $title = 'Novo chat';
        }

        $sessionId = $this->chat->createSession($userId, $title);
        if ($sessionId <= 0) {
            $this->error('Não foi possível criar o chat no momento.');
            $this->redirect($returnRoute);
        }

        $this->redirect($returnRoute . '&' . $sessionParam . '=' . $sessionId);
    }

    public function ask(): void
    {
        require_auth();
        require_permission('help');
        csrf_validate();

        $userId = (int) (current_user()['id'] ?? 0);
        $question = trim((string) post('message'));
        if ($question === '') {
            $this->json([
                'ok' => false,
                'message' => 'Digite uma pergunta antes de enviar.',
            ], 422);
        }

        if (strlen($question) > 2000) {
            $question = substr($question, 0, 2000);
        }

        $historyAvailable = $this->chat->featureAvailable();
        $sessionId = max(0, (int) post('session_id', 0));

        if ($historyAvailable) {
            if ($sessionId <= 0 || !$this->chat->findSession($sessionId, $userId)) {
                $sessionId = $this->chat->createSession($userId, 'Novo chat');
            }

            if ($sessionId > 0) {
                $this->chat->appendMessage($sessionId, $userId, 'user', $question, [
                    'from' => 'admin_ui',
                ]);
            }
        } else {
            $this->appendMemoryMessage('user', $question, ['from' => 'admin_ui']);
        }

        $history = $historyAvailable
            ? $this->chat->listMessages($sessionId, $userId, 12)
            : $this->memoryMessages(12);

        if ($history !== []) {
            $lastIndex = count($history) - 1;
            $lastRole = strtolower(trim((string) ($history[$lastIndex]['role'] ?? '')));
            $lastContent = trim((string) ($history[$lastIndex]['content'] ?? ''));
            if ($lastRole === 'user' && $lastContent === $question) {
                array_pop($history);
            }
        }

        $questionForContext = $this->resolveQuestionForContext($question, $history);
        $context = $this->knowledge->buildContext($questionForContext);
        $fallback = false;
        $warning = null;
        $ai = [
            'provider' => $this->assistant->provider(),
            'model' => $this->assistant->model(),
        ];
        $answer = '';

        $directAnswer = $this->directAnswerFromContext($questionForContext, $context);
        if ($directAnswer !== null) {
            $answer = $directAnswer;
            $ai['provider'] = 'internal_context';
            $ai['model'] = 'deterministic';
        } else {
            $ai = $this->assistant->ask($questionForContext, (string) ($context['context_json'] ?? '{}'), $history);

            if (!($ai['ok'] ?? false)) {
                $fallback = true;
                $rawReason = (string) ($ai['message'] ?? 'Não foi possível consultar o provedor de IA.');
                $warning = $this->shouldExposeWarning($rawReason) ? $rawReason : null;
                $answer = $this->buildFallbackAnswer($question, $context, $rawReason);
            } else {
                $answer = trim((string) ($ai['answer'] ?? ''));
                if ($answer === '') {
                    $fallback = true;
                    $rawReason = 'IA retornou resposta vazia. Exibindo resumo do banco interno.';
                    $warning = $this->shouldExposeWarning($rawReason) ? $rawReason : null;
                    $answer = $this->buildFallbackAnswer($question, $context, $rawReason);
                }
            }
        }

        $metadata = [
            'sources' => $context['sources'] ?? [],
            'fallback' => $fallback ? 1 : 0,
            'warning' => $warning,
            'provider' => $ai['provider'] ?? $this->assistant->provider(),
            'model' => $ai['model'] ?? $this->assistant->model(),
        ];

        if ($historyAvailable && $sessionId > 0) {
            $this->chat->appendMessage($sessionId, $userId, 'assistant', $answer, $metadata);

            $session = $this->chat->findSession($sessionId, $userId);
            $sessionTitle = trim((string) ($session['title'] ?? ''));
            if ($sessionTitle === '' || str_starts_with(strtolower($sessionTitle), 'novo chat')) {
                $this->chat->renameSession($sessionId, $userId, $this->titleFromQuestion($question));
            }
        } else {
            $this->appendMemoryMessage('assistant', $answer, $metadata);
        }

        $this->json([
            'ok' => true,
            'session_id' => $sessionId,
            'answer' => $answer,
            'fallback' => $fallback,
            'warning' => $warning,
            'sources' => $context['sources'] ?? [],
        ]);
    }

    private function buildFallbackAnswer(string $question, array $context, string $reason): string
    {
        $summary = (array) ($context['summary'] ?? []);
        $matches = (array) ($context['matches'] ?? []);

        // Verifica se há registros específicos encontrados
        $hasMatches = false;
        foreach ($matches as $rows) {
            if (is_array($rows) && count($rows) > 0) {
                $hasMatches = true;
                break;
            }
        }

        $lines = [];

        if ($hasMatches) {
            // Resposta focada nos dados encontrados
            $lines[] = 'Aqui está o que encontrei no banco de dados:';
            $lines[] = '';

            foreach ($matches as $section => $rows) {
                if (!is_array($rows) || $rows === []) {
                    continue;
                }

                $label = match ((string) $section) {
                    'students'                  => 'Alunos',
                    'lista_alunos_ativos'        => 'Alunos ativos',
                    'lista_alunos_inativos'      => 'Alunos inativos',
                    'alunos_inadimplentes'       => 'Alunos inadimplentes',
                    'leads'                      => 'Leads',
                    'leads_sem_contato_recente'  => 'Leads sem contato recente',
                    'leads_comerciais_prioritarios' => 'Leads prioritarios do comercial',
                    'invoices'                   => 'Faturas',
                    'faturas_vencidas_detalhe'   => 'Faturas vencidas',
                    'maiores_faturas_em_aberto'  => 'Maiores faturas em aberto',
                    'courses'                    => 'Cursos',
                    'course_enrollments'         => 'Matrículas',
                    'payments'                   => 'Pagamentos',
                    'recebimentos_mes_atual'     => 'Recebimentos do mês',
                    'support_tickets'            => 'Chamados',
                    'renegociacoes_mobile_pendentes' => 'Renegociacoes mobile pendentes',
                    default                      => ucfirst((string) $section),
                };

                $total = count($rows);
                $lines[] = $label . ' (' . $total . ' encontrado(s)):';
                foreach (array_slice($rows, 0, 5) as $row) {
                    $lines[] = '• ' . $this->formatMatchLine((string) $section, (array) $row);
                }
                if ($total > 5) {
                    $lines[] = '  ... e mais ' . ($total - 5) . ' registro(s).';
                }
                $lines[] = '';
            }

            // Rodapé com resumo compacto
            $lines[] = 'Resumo geral: '
                . (int) ($summary['active_students'] ?? 0) . ' aluno(s) ativo(s) | '
                . (int) ($summary['total_leads'] ?? 0) . ' lead(s) | '
                . (int) ($summary['open_invoices'] ?? 0) . ' fatura(s) em aberto | '
                . 'Saldo: R$ ' . number_format((float) ($summary['open_balance'] ?? 0), 2, ',', '.');
        } else {
            // Sem dados específicos — panorama geral limpo
            $lines[] = 'Não encontrei registros específicos para essa pergunta.';
            $lines[] = '';
            $lines[] = 'Panorama atual da escola:';
            $lines[] = '• Alunos ativos: ' . (int) ($summary['active_students'] ?? 0);
            $lines[] = '• Alunos inativos: ' . (int) ($summary['inactive_students'] ?? 0);
            $lines[] = '• Leads cadastrados: ' . (int) ($summary['total_leads'] ?? 0);
            $lines[] = '• Cursos publicados: ' . (int) ($summary['published_courses'] ?? 0);
            $lines[] = '• Matrículas ativas: ' . (int) ($summary['active_enrollments'] ?? 0);
            $lines[] = '• Faturas em aberto: ' . (int) ($summary['open_invoices'] ?? 0);
            $lines[] = '• Faturas vencidas: ' . (int) ($summary['overdue_invoices'] ?? 0);
            $lines[] = '• Saldo a receber: R$ ' . number_format((float) ($summary['open_balance'] ?? 0), 2, ',', '.');
            $lines[] = '• Recebido no mês: R$ ' . number_format((float) ($summary['received_this_month'] ?? 0), 2, ',', '.');

            if (isset($summary['support_open_tickets'])) {
                $lines[] = '• Chamados abertos: ' . (int) ($summary['support_open_tickets'] ?? 0);
                $lines[] = '• Chamados em andamento: ' . (int) ($summary['support_in_progress_tickets'] ?? 0);
                $lines[] = '• Chamados resolvidos: ' . (int) ($summary['support_resolved_tickets'] ?? 0);
            }
        }

        return implode("\n", $lines);
    }

    private function titleFromQuestion(string $question): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $question) ?? '');
        if ($title === '') {
            return 'Novo chat';
        }
        if (strlen($title) > 80) {
            $title = substr($title, 0, 77) . '...';
        }
        return $title;
    }

    private function memoryStoreKey(): string
    {
        return '_admin_ai_memory_' . (int) (current_company_id() ?? 0) . '_' . (int) (current_user()['id'] ?? 0);
    }

    private function memoryMessages(int $limit = 80): array
    {
        $key = $this->memoryStoreKey();
        $rows = $_SESSION[$key] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $rows = array_values(array_filter($rows, fn ($row) => is_array($row)));
        if ($limit > 0 && count($rows) > $limit) {
            $rows = array_slice($rows, -$limit);
        }

        return $rows;
    }

    private function appendMemoryMessage(string $role, string $content, array $metadata = []): void
    {
        $role = strtolower(trim($role));
        if (!in_array($role, ['user', 'assistant'], true)) {
            $role = 'assistant';
        }

        $content = trim($content);
        if ($content === '') {
            return;
        }

        $key = $this->memoryStoreKey();
        $rows = $_SESSION[$key] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $rows[] = [
            'role' => $role,
            'content' => $content,
            'created_at' => now(),
            'metadata' => $metadata,
        ];

        if (count($rows) > 120) {
            $rows = array_slice($rows, -120);
        }

        $_SESSION[$key] = $rows;
    }

    private function shouldExposeWarning(string $reason): bool
    {
        $reason = strtolower(trim($reason));
        if ($reason === '') {
            return false;
        }

        if (str_contains($reason, 'desativado') || str_contains($reason, 'faltam credenciais')) {
            return false;
        }

        return str_contains($reason, 'http')
            || str_contains($reason, 'erro');
    }

    private function directAnswerFromContext(string $question, array $context): ?string
    {
        $normalized = $this->normalizeIntent($question);
        if ($normalized === '') {
            return null;
        }

        $summary = is_array($context['summary'] ?? null) ? $context['summary'] : [];
        $matches = is_array($context['matches'] ?? null) ? $context['matches'] : [];
        $payload = is_array($context['payload'] ?? null) ? $context['payload'] : [];
        $alerts = is_array($payload['alertas_operacionais'] ?? null) ? $payload['alertas_operacionais'] : [];

        // Resumo geral / panorama
        $asksOverview = str_contains($normalized, 'resumo')
            || str_contains($normalized, 'panorama')
            || str_contains($normalized, 'visao geral')
            || str_contains($normalized, 'situacao geral')
            || str_contains($normalized, 'como esta');
        if ($asksOverview) {
            $active   = (int) ($summary['active_students'] ?? 0);
            $leads    = (int) ($summary['total_leads'] ?? 0);
            $invoices = (int) ($summary['open_invoices'] ?? 0);
            $overdue  = (int) ($summary['overdue_invoices'] ?? 0);
            $balance  = number_format((float) ($summary['open_balance'] ?? 0), 2, ',', '.');
            $received = number_format((float) ($summary['received_this_month'] ?? 0), 2, ',', '.');
            return "Panorama atual da escola:\n"
                . '• Alunos ativos: ' . $active . "\n"
                . '• Leads cadastrados: ' . $leads . "\n"
                . '• Faturas em aberto: ' . $invoices . ' (vencidas: ' . $overdue . ')' . "\n"
                . '• Saldo a receber: R$ ' . $balance . "\n"
                . '• Recebido no mês: R$ ' . $received;
        }

        // Total de leads cadastrados
        $asksLeadTotal = str_contains($normalized, 'lead')
            && (str_contains($normalized, 'quant') || str_contains($normalized, 'total')
                || str_contains($normalized, 'disponiv') || str_contains($normalized, 'cadastr')
                || str_contains($normalized, 'tem') || str_contains($normalized, 'ha'))
            && !str_contains($normalized, 'contat')
            && !str_contains($normalized, 'precis')
            && !str_contains($normalized, 'aguard');
        if ($asksLeadTotal) {
            $total     = (int) ($summary['total_leads'] ?? 0);
            $converted = (int) ($summary['converted_leads'] ?? 0);
            $pending   = max(0, $total - $converted);
            return 'Temos ' . $total . ' lead(s) cadastrado(s) no total: '
                . $converted . ' já convertido(s) em aluno(s) e '
                . $pending . ' ainda em negociação.';
        }

        // Total de alunos cadastrados (sem filtro de ativo/inativo)
        $asksStudentTotal = str_contains($normalized, 'alun')
            && (str_contains($normalized, 'quant') || str_contains($normalized, 'total') || str_contains($normalized, 'cadastr'))
            && !str_contains($normalized, 'ativ')
            && !str_contains($normalized, 'inativ')
            && !str_contains($normalized, 'inadimplent')
            && !str_contains($normalized, 'atras')
            && !str_contains($normalized, 'vencid')
            && !str_contains($normalized, 'saldo')
            && !str_contains($normalized, 'fatura')
            && !str_contains($normalized, 'financeir')
            && !str_contains($normalized, 'matricul');
        if ($asksStudentTotal) {
            $total    = (int) ($summary['total_students'] ?? 0);
            $active   = (int) ($summary['active_students'] ?? 0);
            $inactive = (int) ($summary['inactive_students'] ?? 0);
            return 'Temos ' . $total . ' aluno(s) cadastrado(s) no total: '
                . $active . ' ativo(s) e ' . $inactive . ' inativo(s).';
        }

        $asksReceivable = (str_contains($normalized, 'receb') || str_contains($normalized, 'conta a receber'))
            && (str_contains($normalized, 'conta') || str_contains($normalized, 'fatura') || str_contains($normalized, 'titulo'));
        if ($asksReceivable) {
            $openInvoices = (int) ($summary['open_invoices'] ?? 0);
            $openBalance = (float) ($summary['open_balance'] ?? 0);
            return 'No momento, há ' . $openInvoices . ' conta(s) a receber em aberto, totalizando R$ '
                . number_format($openBalance, 2, ',', '.') . '.';
        }

        $asksOverdueBalance = (str_contains($normalized, 'saldo') || str_contains($normalized, 'valor') || str_contains($normalized, 'montante'))
            && (str_contains($normalized, 'vencid') || str_contains($normalized, 'atras') || str_contains($normalized, 'inadimpl'));
        if ($asksOverdueBalance) {
            $overdueBalance = (float) ($summary['overdue_balance'] ?? 0);
            $studentsWithOverdue = (int) ($summary['students_with_overdue'] ?? 0);
            return 'O saldo vencido atual e de R$ ' . number_format($overdueBalance, 2, ',', '.')
                . ', distribuido em ' . $studentsWithOverdue . ' aluno(s) com atraso.';
        }

        $asksOverdue = (str_contains($normalized, 'vencid') || str_contains($normalized, 'atras'))
            && (str_contains($normalized, 'conta') || str_contains($normalized, 'fatura') || str_contains($normalized, 'receb'));
        if ($asksOverdue) {
            $overdue = (int) ($summary['overdue_invoices'] ?? 0);
            return 'Temos ' . $overdue . ' conta(s) vencida(s) no contas a receber.';
        }

        $asksLargestOpenInvoices = (str_contains($normalized, 'fatura') || str_contains($normalized, 'conta') || str_contains($normalized, 'titulo'))
            && (str_contains($normalized, 'maior') || str_contains($normalized, 'maiores') || str_contains($normalized, 'top') || str_contains($normalized, 'aberto'));
        if ($asksLargestOpenInvoices) {
            $largestOpenInvoices = is_array($matches['maiores_faturas_em_aberto'] ?? null) ? $matches['maiores_faturas_em_aberto'] : [];
            if ($largestOpenInvoices === []) {
                return 'Não encontrei faturas em aberto para listar no momento.';
            }

            $lines = ['Maiores faturas em aberto agora:'];
            foreach (array_slice($largestOpenInvoices, 0, 5) as $row) {
                $invoiceNumber = trim((string) ($row['invoice_number'] ?? 'Fatura'));
                $studentName = trim((string) ($row['student_name'] ?? '-'));
                $dueDate = trim((string) ($row['due_date'] ?? '-'));
                $balance = number_format((float) ($row['saldo_devedor'] ?? 0), 2, ',', '.');
                $lines[] = '- ' . $invoiceNumber . ' | aluno: ' . $studentName . ' | venc: ' . $dueDate . ' | saldo: R$ ' . $balance;
            }

            return implode("\n", $lines);
        }

        $asksNegotiation = str_contains($normalized, 'negoci')
            || str_contains($normalized, 'renegoci')
            || str_contains($normalized, 'aditivo')
            || str_contains($normalized, 'acordo');
        $asksPending = str_contains($normalized, 'pendent')
            || str_contains($normalized, 'abert')
            || str_contains($normalized, 'andamento')
            || str_contains($normalized, 'fila');
        if ($asksNegotiation && ($asksPending || str_contains($normalized, 'quant') || str_contains($normalized, 'tem') || str_contains($normalized, 'ha'))) {
            $pendingTotal = (int) ($summary['pending_mobile_negotiations'] ?? 0);
            $pendingNegociacoes = (int) ($summary['pending_mobile_negociacoes'] ?? 0);
            $pendingAditivos = (int) ($summary['pending_mobile_aditivos'] ?? 0);

            if ($pendingTotal <= 0) {
                return 'No momento não há fluxos mobile de negociacao ou aditivo pendentes.';
            }

            return 'Temos ' . $pendingTotal . ' fluxo(s) mobile pendente(s): '
                . $pendingNegociacoes . ' negociacao(oes) e '
                . $pendingAditivos . ' aditivo(s).';
        }

        $asksCommercial = str_contains($normalized, 'lead')
            || str_contains($normalized, 'comercial')
            || str_contains($normalized, 'pipeline')
            || str_contains($normalized, 'funil');
        $asksPriority = str_contains($normalized, 'prior')
            || str_contains($normalized, 'urg')
            || str_contains($normalized, 'atacar')
            || str_contains($normalized, 'trabalhar');
        if ($asksCommercial && $asksPriority) {
            $leadsWithoutContact = (int) ($summary['leads_without_recent_contact'] ?? 0);
            $openLeadValue = (float) ($summary['open_lead_value'] ?? 0);
            $priorityLeads = is_array($matches['leads_comerciais_prioritarios'] ?? null) ? $matches['leads_comerciais_prioritarios'] : [];
            if ($priorityLeads === []) {
                return 'No comercial, o foco imediato são ' . $leadsWithoutContact . ' lead(s) sem contato recente, com pipeline em aberto de R$ '
                    . number_format($openLeadValue, 2, ',', '.') . '.';
            }

            $lines = [
                'Prioridade comercial agora:',
                '',
                '- ' . $leadsWithoutContact . ' lead(s) sem contato recente | pipeline aberto: R$ ' . number_format($openLeadValue, 2, ',', '.'),
                '',
                'Top leads para atacar primeiro:',
            ];

            foreach (array_slice($priorityLeads, 0, 5) as $row) {
                $name = trim((string) ($row['full_name'] ?? 'Lead'));
                $status = trim((string) ($row['status_name'] ?? '-'));
                $source = trim((string) ($row['source'] ?? '-'));
                $unit = trim((string) ($row['unit_name'] ?? '-'));
                $leadValue = number_format((float) ($row['lead_value'] ?? 0), 2, ',', '.');
                $lastContactRaw = trim((string) ($row['last_contact_at'] ?? ''));
                $lastContactLabel = $lastContactRaw !== '' ? $lastContactRaw : 'sem contato registrado';

                $lines[] = '- ' . $name
                    . ' | potencial: R$ ' . $leadValue
                    . ' | status: ' . $status
                    . ' | origem: ' . $source
                    . ' | unidade: ' . $unit
                    . ' | ultimo contato: ' . $lastContactLabel;
            }

            $lines[] = '';
            $lines[] = 'Proximo passo sugerido: atacar primeiro os sem contato recente e maior potencial financeiro.';

            return implode("\n", $lines);
        }

        $asksLeadContact = str_contains($normalized, 'lead')
            && (str_contains($normalized, 'contat') || str_contains($normalized, 'precis') || str_contains($normalized, 'aguard'));
        if ($asksLeadContact) {
            $leads = is_array($matches['leads'] ?? null) ? $matches['leads'] : [];
            if ($leads === []) {
                return 'Não encontrei leads com esse criterio de contato na base interna.';
            }

            $names = [];
            foreach ($leads as $row) {
                $name = trim((string) ($row['full_name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $names = array_values(array_unique($names));
            $preview = implode(', ', array_slice($names, 0, 5));
            if ($preview === '') {
                $preview = 'sem nomes disponíveis';
            }

            $asksNames = str_contains($normalized, 'qual')
                || str_contains($normalized, 'quem')
                || str_contains($normalized, 'nome');
            if ($asksNames) {
                return 'Leads que precisam de contato: ' . $preview . '.';
            }

            return 'Existe(m) ' . count($leads) . ' lead(s) que precisa(m) de contato: ' . $preview . '.';
        }

        $asksPriorityToday = str_contains($normalized, 'priorizar')
            || str_contains($normalized, 'prioridade')
            || str_contains($normalized, 'decidir primeiro')
            || str_contains($normalized, 'o que fazer primeiro');
        if ($asksPriorityToday && $alerts !== []) {
            $topAlerts = [];
            foreach (array_slice($alerts, 0, 3) as $alert) {
                $title = trim((string) ($alert['title'] ?? ''));
                $detail = trim((string) ($alert['detail'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $topAlerts[] = '- ' . $title . ($detail !== '' ? ': ' . $detail : '');
            }

            if ($topAlerts !== []) {
                return "Prioridades recomendadas agora:\n\n" . implode("\n", $topAlerts);
            }
        }

        $asksSupport = str_contains($normalized, 'solicit')
            || str_contains($normalized, 'ticket')
            || str_contains($normalized, 'chamad')
            || str_contains($normalized, 'suporte');
        $asksInProgress = str_contains($normalized, 'andamento')
            || str_contains($normalized, 'em progresso')
            || str_contains($normalized, 'in progress');
        $asksOpen = str_contains($normalized, 'abert');
        $asksResolved = str_contains($normalized, 'resolvid') || str_contains($normalized, 'fechad');

        if ($asksSupport && ($asksInProgress || $asksOpen || $asksResolved || str_contains($normalized, 'quant') || str_contains($normalized, 'tem') || str_contains($normalized, 'ha'))) {
            $supportOpen = (int) ($summary['support_open_tickets'] ?? 0);
            $supportInProgress = (int) ($summary['support_in_progress_tickets'] ?? 0);
            $supportResolved = (int) ($summary['support_resolved_tickets'] ?? 0);
            $supportClosed = (int) ($summary['support_closed_tickets'] ?? 0);
            $supportOpenTotal = (int) ($summary['open_support_tickets'] ?? ($supportOpen + $supportInProgress));

            if ($asksInProgress) {
                if ($supportInProgress > 0) {
                    return 'Sim. Temos ' . $supportInProgress . ' ticket(s) de suporte em andamento.';
                }
                return 'Não. No momento não há tickets de suporte em andamento.';
            }

            if ($asksResolved) {
                return 'Temos ' . $supportResolved . ' ticket(s) resolvido(s) e ' . $supportClosed . ' fechado(s) no suporte.';
            }

            if ($asksOpen) {
                return 'Temos ' . $supportOpenTotal . ' ticket(s) de suporte abertos (abertos: ' . $supportOpen . ', em andamento: ' . $supportInProgress . ').';
            }

            return 'No suporte, temos ' . $supportOpenTotal . ' ticket(s) abertos no total (abertos: ' . $supportOpen . ', em andamento: ' . $supportInProgress . ', resolvidos: ' . $supportResolved . ').';
        }

        $asksCourseEnrollments = (str_contains($normalized, 'curso') || str_contains($normalized, 'turma'))
            && (str_contains($normalized, 'matricul') || str_contains($normalized, 'inscrit') || str_contains($normalized, 'alun'));
        if ($asksCourseEnrollments) {
            $enrollments = is_array($matches['course_enrollments'] ?? null) ? $matches['course_enrollments'] : [];
            if ($enrollments === []) {
                return 'Não encontrei alunos matriculados para esse curso no banco interno.';
            }

            $byCourse = [];
            foreach ($enrollments as $row) {
                $courseName = trim((string) ($row['course_name'] ?? ''));
                $studentName = trim((string) ($row['student_name'] ?? ''));
                if ($courseName === '' || $studentName === '') {
                    continue;
                }

                if (!array_key_exists($courseName, $byCourse)) {
                    $byCourse[$courseName] = [];
                }

                if (!in_array($studentName, $byCourse[$courseName], true)) {
                    $byCourse[$courseName][] = $studentName;
                }
            }

            if ($byCourse === []) {
                return 'Encontrei registros de matrícula, mas sem nomes de alunos para exibir.';
            }

            $courseNames = array_keys($byCourse);
            if (count($courseNames) === 1) {
                $courseName = (string) $courseNames[0];
                $studentsInCourse = $byCourse[$courseName];
                $total = count($studentsInCourse);
                $preview = implode(', ', array_slice($studentsInCourse, 0, 8));
                $suffix = $total > 8 ? ' (mostrando 8 de ' . $total . ')' : '';

                return 'No curso ' . $courseName . ' encontrei ' . $total . ' aluno(s) matriculado(s): ' . $preview . $suffix . '.';
            }

            $summary = [];
            foreach ($byCourse as $courseName => $studentsInCourse) {
                $summary[] = $courseName . ': ' . count($studentsInCourse) . ' aluno(s)';
            }

            return 'Encontrei alunos matriculados em ' . count($byCourse) . ' curso(s): '
                . implode('; ', array_slice($summary, 0, 3)) . '.';
        }

        $asksInactive = str_contains($normalized, 'alun')
            && str_contains($normalized, 'inativ')
            && (str_contains($normalized, 'quant') || str_contains($normalized, 'qtd') || str_contains($normalized, 'numero') || str_contains($normalized, 'tem'));
        if ($asksInactive) {
            $inactive = (int) ($summary['inactive_students'] ?? max(0, (int) ($summary['total_students'] ?? 0) - (int) ($summary['active_students'] ?? 0)));
            return 'Temos ' . $inactive . ' aluno(s) inativo(s) na base interna.';
        }

        if (str_contains($normalized, 'quant')
            && str_contains($normalized, 'alun')
            && str_contains($normalized, 'ativ')
            && !str_contains($normalized, 'inativ')) {
            $active = (int) ($summary['active_students'] ?? 0);
            return 'Temos ' . $active . ' aluno(s) ativo(s) na base interna.';
        }

        $students = is_array($matches['students'] ?? null) ? $matches['students'] : [];
        $isNameLookup = (str_contains($normalized, 'alun') || str_contains($normalized, 'nome'))
            && (str_contains($normalized, 'tem') || str_contains($normalized, 'existe'))
            && (str_contains($normalized, 'chamad') || str_contains($normalized, 'nome'));
        if ($isNameLookup) {
            if ($students === []) {
                return 'Não encontrei aluno com esse nome na base interna.';
            }

            $names = [];
            foreach ($students as $row) {
                $name = trim((string) ($row['full_name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $names = array_values(array_unique($names));
            $preview = implode(', ', array_slice($names, 0, 3));
            if ($preview === '') {
                $preview = 'registro sem nome';
            }

            return 'Sim. Encontrei ' . count($students) . ' aluno(s) com esse termo: ' . $preview . '.';
        }

        return null;
    }

    private function normalizeIntent(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

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

        $value = preg_replace('/[^a-z0-9\\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function resolveQuestionForContext(string $question, array $history): string
    {
        $question = trim($question);
        if ($question === '') {
            return '';
        }

        $normalized = $this->normalizeIntent($question);
        if ($normalized === '') {
            return $question;
        }

        $wordCount = $this->wordCount($normalized);
        $hasFollowupPronoun = str_contains($normalized, 'dele')
            || str_contains($normalized, 'deles')
            || str_contains($normalized, 'dela')
            || str_contains($normalized, 'delas')
            || str_contains($normalized, 'isso')
            || str_contains($normalized, 'isto')
            || str_contains($normalized, 'esse')
            || str_contains($normalized, 'essa')
            || str_contains($normalized, 'esses')
            || str_contains($normalized, 'essas');
        $hasStatusFollowup = str_contains($normalized, 'status')
            || str_contains($normalized, 'andamento')
            || str_contains($normalized, 'aberto')
            || str_contains($normalized, 'resolvido')
            || str_contains($normalized, 'fechado');
        $hasExplicitTopic = str_contains($normalized, 'lead')
            || str_contains($normalized, 'alun')
            || str_contains($normalized, 'fatura')
            || str_contains($normalized, 'conta')
            || str_contains($normalized, 'boleto')
            || str_contains($normalized, 'pagamento')
            || str_contains($normalized, 'receb')
            || str_contains($normalized, 'negoci')
            || str_contains($normalized, 'aditivo')
            || str_contains($normalized, 'ticket')
            || str_contains($normalized, 'chamad')
            || str_contains($normalized, 'suporte');

        $shouldAppendPrevious = $wordCount <= 2
            || ($hasFollowupPronoun && $wordCount <= 10)
            || ($hasStatusFollowup && !$hasExplicitTopic && $wordCount <= 10);
        if (!$shouldAppendPrevious) {
            return $question;
        }

        for ($i = count($history) - 1; $i >= 0; $i--) {
            $row = $history[$i] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $role = strtolower(trim((string) ($row['role'] ?? '')));
            if ($role !== 'user') {
                continue;
            }

            $candidate = trim((string) ($row['content'] ?? ''));
            if ($candidate === '' || $candidate === $question) {
                continue;
            }

            $candidateWords = $this->wordCount($this->normalizeIntent($candidate));
            if ($candidateWords < 3) {
                continue;
            }

            return $candidate . ' ' . $question;
        }

        return $question;
    }

    private function wordCount(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $parts = preg_split('/\\s+/', $value) ?: [];
        $parts = array_values(array_filter($parts, fn ($item) => trim((string) $item) !== ''));

        return count($parts);
    }

    private function formatMatchLine(string $section, array $row): string
    {
        return match ($section) {
            'students',
            'lista_alunos_ativos',
            'lista_alunos_inativos'
                => trim((string) ($row['full_name'] ?? 'Aluno'))
                   . ' | ' . (((int) ($row['is_active'] ?? 1)) === 1 ? 'ativo' : 'inativo')
                   . ' | ' . trim((string) ($row['email_primary'] ?? '-')),

            'alunos_inadimplentes'
                => trim((string) ($row['full_name'] ?? 'Aluno'))
                   . ' | ' . (int) ($row['faturas_vencidas'] ?? 0) . ' fatura(s) vencida(s)'
                   . ' | Saldo: R$ ' . number_format((float) ($row['saldo_devedor'] ?? 0), 2, ',', '.'),

            'leads',
            'leads_sem_contato_recente'
                => trim((string) ($row['full_name'] ?? 'Lead'))
                   . ' | status: ' . trim((string) ($row['status_name'] ?? '-'))
                   . ' | último contato: ' . (trim((string) ($row['last_contact_at'] ?? '')) ?: 'nunca'),

            'leads_comerciais_prioritarios'
                => trim((string) ($row['full_name'] ?? 'Lead'))
                   . ' | status: ' . trim((string) ($row['status_name'] ?? '-'))
                   . ' | potencial: R$ ' . number_format((float) ($row['lead_value'] ?? 0), 2, ',', '.'),

            'invoices',
            'faturas_vencidas_detalhe'
                => trim((string) ($row['invoice_number'] ?? 'Fatura'))
                   . ' | aluno: ' . trim((string) ($row['student_name'] ?? '-'))
                   . ' | venc: ' . trim((string) ($row['due_date'] ?? '-'))
                   . ' | R$ ' . number_format((float) ($row['amount'] ?? 0), 2, ',', '.'),

            'maiores_faturas_em_aberto'
                => trim((string) ($row['invoice_number'] ?? 'Fatura'))
                   . ' | aluno: ' . trim((string) ($row['student_name'] ?? '-'))
                   . ' | saldo: R$ ' . number_format((float) ($row['saldo_devedor'] ?? 0), 2, ',', '.'),

            'courses'
                => trim((string) ($row['name'] ?? 'Curso'))
                   . ' | status: ' . trim((string) ($row['status'] ?? '-')),

            'payments',
            'recebimentos_mes_atual'
                => trim((string) ($row['payment_ref'] ?? 'Pagamento'))
                   . ' | R$ ' . number_format((float) ($row['amount'] ?? 0), 2, ',', '.')
                   . ' | ' . trim((string) ($row['paid_at'] ?? '-')),

            'support_tickets'
                => trim((string) ($row['ticket_code'] ?? 'Chamado'))
                   . ' | ' . trim((string) ($row['subject'] ?? '-'))
                   . ' | status: ' . trim((string) ($row['status'] ?? '-')),

            'renegociacoes_mobile_pendentes'
                => trim((string) ($row['ticket_code'] ?? 'Chamado'))
                   . ' | ' . trim((string) ($row['subject'] ?? '-'))
                   . ' | prioridade: ' . trim((string) ($row['priority'] ?? '-')),

            default => trim((string) ($row['full_name'] ?? $row['name'] ?? $row['id'] ?? '-')),
        };
    }
}
