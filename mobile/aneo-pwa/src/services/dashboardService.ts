import type {
  ApiConfig,
  ApiInvoice,
  ApiStudent,
  ApiTicket,
  ApiTrialAccess,
  ExecutiveAlert,
  ExecutiveDashboardData,
  ExecutiveMetric,
  ExecutivePulse,
  ExecutiveSnapshot,
  ExecutiveSummary,
} from '../types';
import { fetchAllPages } from './apiClient';
import { formatCurrency, formatPercent } from '../utils/format';

function asNumber(value: unknown): number {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function isActiveStudent(value: unknown): boolean {
  if (typeof value === 'boolean') {
    return value;
  }

  const n = Number(value);
  return Number.isFinite(n) ? n === 1 : false;
}

function outstandingAmount(invoice: ApiInvoice): number {
  const amount = asNumber(invoice.amount);
  const paid = asNumber(invoice.paid_amount);
  return Math.max(0, amount - paid);
}

function normalizeTicketStatus(status: string | null | undefined): string {
  return String(status ?? '').trim().toLowerCase();
}

function normalizeTrialStatus(status: string | null | undefined): string {
  return String(status ?? '').trim().toLowerCase();
}

async function safeLoadRows<TItem>(
  config: ApiConfig,
  resource: string
): Promise<{ rows: TItem[]; available: boolean }> {
  try {
    const response = await fetchAllPages<TItem>(config, resource);
    return { rows: response.rows, available: true };
  } catch {
    return { rows: [], available: false };
  }
}

function buildMetrics(input: {
  students: ApiStudent[];
  invoices: ApiInvoice[];
  openInvoices: ApiInvoice[];
  overdueInvoices: ApiInvoice[];
  paidInvoices: ApiInvoice[];
  activeStudents: number;
  openValue: number;
  overdueValue: number;
  paidValue: number;
  pendingValue: number;
  delinquencyRate: number;
  recoveryRate: number;
}): ExecutiveMetric[] {
  const {
    students,
    invoices,
    openInvoices,
    overdueInvoices,
    paidInvoices,
    activeStudents,
    openValue,
    overdueValue,
    paidValue,
    pendingValue,
    delinquencyRate,
    recoveryRate,
  } = input;

  return [
    {
      id: 'students_total',
      title: 'Alunos ativos',
      value: activeStudents.toLocaleString('pt-BR'),
      trend: `${students.length} cadastrados`,
      tone: 'positive',
      description: 'Base ativa na empresa conectada.',
    },
    {
      id: 'invoices_open',
      title: 'Boletos em aberto',
      value: formatCurrency(openValue),
      trend: `${openInvoices.length} titulos`,
      tone: openValue > 0 ? 'warning' : 'positive',
      description: 'Valores ainda no prazo, aguardando pagamento.',
    },
    {
      id: 'invoices_overdue',
      title: 'Boletos vencidos',
      value: formatCurrency(overdueValue),
      trend: `${overdueInvoices.length} titulos`,
      tone: overdueValue > 0 ? 'critical' : 'positive',
      description: 'Pendencias vencidas com saldo a recuperar.',
    },
    {
      id: 'pending_total',
      title: 'Saldo pendente',
      value: formatCurrency(pendingValue),
      trend: `Recuperacao estimada ${formatPercent(recoveryRate)}`,
      tone: pendingValue > 0 ? 'warning' : 'positive',
      description: 'Total ainda em aberto ou vencido.',
    },
    {
      id: 'default_rate',
      title: 'Taxa de inadimplencia',
      value: formatPercent(delinquencyRate),
      trend: `${overdueInvoices.length}/${invoices.length || 0} titulos`,
      tone: delinquencyRate >= 20 ? 'critical' : delinquencyRate >= 10 ? 'warning' : 'positive',
      description: 'Peso dos vencidos dentro da carteira.',
    },
    {
      id: 'invoices_paid',
      title: 'Recebido',
      value: formatCurrency(paidValue),
      trend: `${paidInvoices.length} titulos pagos`,
      tone: 'positive',
      description: 'Total efetivamente recebido no conjunto consultado.',
    },
  ];
}

function buildSummary(input: {
  pendingValue: number;
  overdueValue: number;
  delinquencyRate: number;
  recoveryRate: number;
  openTickets: number;
  urgentTickets: number;
}): ExecutiveSummary {
  const { pendingValue, overdueValue, delinquencyRate, recoveryRate, openTickets, urgentTickets } = input;

  if (overdueValue > 0 || delinquencyRate >= 20 || urgentTickets > 0) {
    return {
      headline: 'Operacao exige acao imediata',
      message:
        overdueValue > 0
          ? 'Existem valores vencidos e pontos sensiveis que merecem acompanhamento diario.'
          : 'Chamados urgentes ou inadimplencia elevada pedem resposta rapida da operacao.',
      tone: 'critical',
      pendingLabel: 'Saldo vencido',
      pendingValue: formatCurrency(overdueValue),
      recoveryLabel: 'Recuperacao atual',
      recoveryValue: formatPercent(recoveryRate),
    };
  }

  if (pendingValue > 0 || openTickets > 0 || delinquencyRate >= 10) {
    return {
      headline: 'Operacao em acompanhamento',
      message: 'A carteira esta controlada, mas existem pendencias que merecem monitoramento.',
      tone: 'warning',
      pendingLabel: 'Saldo pendente',
      pendingValue: formatCurrency(pendingValue),
      recoveryLabel: 'Recuperacao atual',
      recoveryValue: formatPercent(recoveryRate),
    };
  }

  return {
    headline: 'Operacao sob controle',
    message: 'Sem sinais fortes de pressao financeira ou operacional neste momento.',
    tone: 'positive',
    pendingLabel: 'Saldo pendente',
    pendingValue: formatCurrency(pendingValue),
    recoveryLabel: 'Recuperacao atual',
    recoveryValue: formatPercent(recoveryRate),
  };
}

function buildAlerts(input: {
  overdueValue: number;
  overdueInvoices: ApiInvoice[];
  delinquencyRate: number;
  openTickets: number;
  urgentTickets: number;
  activeTrials: number;
  activeStudents: number;
  trialApiAvailable: boolean;
  ticketApiAvailable: boolean;
}): ExecutiveAlert[] {
  const alerts: ExecutiveAlert[] = [];

  if (input.overdueValue > 0) {
    alerts.push({
      id: 'collection',
      category: 'Financeiro',
      priority: 'Alta',
      title: 'Inadimplencia pede acao',
      detail: `${input.overdueInvoices.length} titulos vencidos somam ${formatCurrency(input.overdueValue)}.`,
      actionLabel: 'Abrir negociacao',
      targetTab: 'negotiation',
      tone: 'critical',
    });
  }

  if (input.ticketApiAvailable && input.openTickets > 0) {
    alerts.push({
      id: 'tickets',
      category: 'Operacao',
      priority: input.urgentTickets > 0 ? 'Alta' : 'Media',
      title: 'Chamados pedem acompanhamento',
      detail:
        input.urgentTickets > 0
          ? `${input.urgentTickets} chamados urgentes dentro de ${input.openTickets} em aberto.`
          : `${input.openTickets} chamados seguem abertos ou em andamento.`,
      actionLabel: 'Ver chamados',
      targetTab: 'tickets',
      tone: input.urgentTickets > 0 ? 'critical' : 'warning',
    });
  }

  if (input.trialApiAvailable && input.activeTrials > 0) {
    alerts.push({
      id: 'trials',
      category: 'Conversao',
      priority: 'Media',
      title: 'Degustacoes prontas para conversao',
      detail: `${input.activeTrials} acessos ativos podem virar matrícula ou follow-up comercial.`,
      actionLabel: 'Abrir degustação',
      targetTab: 'trial-access',
      tone: 'positive',
    });
  }

  if (input.delinquencyRate >= 10 && input.activeStudents > 0) {
    alerts.push({
      id: 'students',
      category: 'Base',
      priority: input.delinquencyRate >= 20 ? 'Alta' : 'Media',
      title: 'Vale revisar a base ativa',
      detail: `A taxa de inadimplencia esta em ${formatPercent(input.delinquencyRate)} com ${input.activeStudents} alunos ativos.`,
      actionLabel: 'Ver alunos',
      targetTab: 'students',
      tone: input.delinquencyRate >= 20 ? 'critical' : 'warning',
    });
  }

  if (alerts.length === 0) {
    alerts.push({
      id: 'steady',
      category: 'Rotina',
      priority: 'Baixa',
      title: 'Sem alertas criticos no momento',
      detail: 'A home esta limpa para acompanhamento de rotina e leitura dos indicadores.',
      actionLabel: 'Ver conexao',
      targetTab: 'connection',
      tone: 'positive',
    });
  }

  return alerts.slice(0, 4);
}

function buildSnapshots(input: {
  activeStudents: number;
  pendingInvoices: number;
  openTickets: number;
  activeTrials: number;
  trialApiAvailable: boolean;
  ticketApiAvailable: boolean;
}): ExecutiveSnapshot[] {
  return [
    {
      id: 'snapshot_students',
      label: 'Base ativa',
      value: input.activeStudents.toLocaleString('pt-BR'),
      helper: 'alunos em operacao',
    },
    {
      id: 'snapshot_invoices',
      label: 'Titulos pendentes',
      value: input.pendingInvoices.toLocaleString('pt-BR'),
      helper: 'abertos + vencidos',
    },
    {
      id: 'snapshot_tickets',
      label: 'Chamados abertos',
      value: input.ticketApiAvailable ? input.openTickets.toLocaleString('pt-BR') : '-',
      helper: input.ticketApiAvailable ? 'fila operacional atual' : 'recurso indisponivel',
    },
    {
      id: 'snapshot_trials',
      label: 'Degustacoes ativas',
      value: input.trialApiAvailable ? input.activeTrials.toLocaleString('pt-BR') : '-',
      helper: input.trialApiAvailable ? 'acessos em andamento' : 'recurso indisponivel',
    },
  ];
}

function buildPulses(input: {
  overdueValue: number;
  delinquencyRate: number;
  openTickets: number;
  urgentTickets: number;
  activeTrials: number;
  trialApiAvailable: boolean;
  ticketApiAvailable: boolean;
}): ExecutivePulse[] {
  const financialTone =
    input.overdueValue > 0 || input.delinquencyRate >= 20
      ? 'critical'
      : input.delinquencyRate >= 10
        ? 'warning'
        : 'positive';

  const operationsTone =
    input.ticketApiAvailable && input.urgentTickets > 0
      ? 'critical'
      : input.ticketApiAvailable && input.openTickets > 0
        ? 'warning'
        : 'positive';

  const conversionTone =
    input.trialApiAvailable && input.activeTrials > 0
      ? 'positive'
      : input.trialApiAvailable
        ? 'warning'
        : 'neutral';

  return [
    {
      id: 'pulse_financial',
      label: 'Financeiro',
      status:
        financialTone === 'critical'
          ? 'Pressionado'
          : financialTone === 'warning'
            ? 'Em atencao'
            : 'Sob controle',
      detail:
        input.overdueValue > 0
          ? `${formatCurrency(input.overdueValue)} vencidos na carteira.`
          : `Inadimplencia atual em ${formatPercent(input.delinquencyRate)}.`,
      tone: financialTone,
    },
    {
      id: 'pulse_operations',
      label: 'Operacao',
      status:
        operationsTone === 'critical'
          ? 'Prioridade alta'
          : operationsTone === 'warning'
            ? 'Monitorar fila'
            : 'Estavel',
      detail: input.ticketApiAvailable
        ? `${input.openTickets} chamados abertos, ${input.urgentTickets} urgentes.`
        : 'Módulo de chamados indisponível no ambiente atual.',
      tone: operationsTone,
    },
    {
      id: 'pulse_conversion',
      label: 'Conversao',
      status:
        conversionTone === 'positive'
          ? 'Oportunidade ativa'
          : conversionTone === 'warning'
            ? 'Sem tracao'
            : 'Não monitorado',
      detail: input.trialApiAvailable
        ? `${input.activeTrials} degustacoes ativas para follow-up comercial.`
        : 'Módulo de degustação indisponível no ambiente atual.',
      tone: conversionTone,
    },
  ];
}

export async function loadExecutiveDashboardFromApi(config: ApiConfig): Promise<ExecutiveDashboardData> {
  const [studentsResponse, invoicesResponse, ticketsResponse, trialsResponse] = await Promise.all([
    fetchAllPages<ApiStudent>(config, 'students'),
    fetchAllPages<ApiInvoice>(config, 'invoices'),
    safeLoadRows<ApiTicket>(config, 'tickets'),
    safeLoadRows<ApiTrialAccess>(config, 'trial_accesses'),
  ]);

  const students = studentsResponse.rows;
  const invoices = invoicesResponse.rows;
  const tickets = ticketsResponse.rows;
  const trials = trialsResponse.rows;

  const activeStudents = students.filter((student) => isActiveStudent(student.is_active)).length;

  const openInvoices = invoices.filter(
    (invoice) => invoice.status === 'open' || invoice.status === 'partial'
  );
  const overdueInvoices = invoices.filter((invoice) => invoice.status === 'overdue');
  const paidInvoices = invoices.filter((invoice) => invoice.status === 'paid');

  const openValue = openInvoices.reduce((sum, invoice) => sum + outstandingAmount(invoice), 0);
  const overdueValue = overdueInvoices.reduce((sum, invoice) => sum + outstandingAmount(invoice), 0);
  const paidValue = paidInvoices.reduce((sum, invoice) => sum + asNumber(invoice.paid_amount), 0);

  const pendingValue = openValue + overdueValue;
  const totalInvoices = invoices.length;
  const delinquencyRate = totalInvoices > 0 ? (overdueInvoices.length / totalInvoices) * 100 : 0;
  const recoveryRate =
    paidValue + pendingValue > 0 ? (paidValue / (paidValue + pendingValue)) * 100 : 0;

  const openTickets = tickets.filter((ticket) => {
    const status = normalizeTicketStatus(ticket.status);
    return status === 'open' || status === 'in_progress';
  }).length;
  const urgentTickets = tickets.filter((ticket) => String(ticket.priority ?? '').trim().toLowerCase() === 'urgent').length;

  const activeTrials = trials.filter((trial) => normalizeTrialStatus(trial.status) === 'active').length;

  const metrics = buildMetrics({
    students,
    invoices,
    openInvoices,
    overdueInvoices,
    paidInvoices,
    activeStudents,
    openValue,
    overdueValue,
    paidValue,
    pendingValue,
    delinquencyRate,
    recoveryRate,
  });

  const summary = buildSummary({
    pendingValue,
    overdueValue,
    delinquencyRate,
    recoveryRate,
    openTickets,
    urgentTickets,
  });

  const alerts = buildAlerts({
    overdueValue,
    overdueInvoices,
    delinquencyRate,
    openTickets,
    urgentTickets,
    activeTrials,
    activeStudents,
    trialApiAvailable: trialsResponse.available,
    ticketApiAvailable: ticketsResponse.available,
  });

  const snapshots = buildSnapshots({
    activeStudents,
    pendingInvoices: openInvoices.length + overdueInvoices.length,
    openTickets,
    activeTrials,
    trialApiAvailable: trialsResponse.available,
    ticketApiAvailable: ticketsResponse.available,
  });

  const pulses = buildPulses({
    overdueValue,
    delinquencyRate,
    openTickets,
    urgentTickets,
    activeTrials,
    trialApiAvailable: trialsResponse.available,
    ticketApiAvailable: ticketsResponse.available,
  });

  return {
    metrics,
    summary,
    alerts,
    snapshots,
    pulses,
  };
}
