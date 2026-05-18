import type { ApiConfig, ApiInvoice, ApiStudent, ExecutiveMetric } from '../types';
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

export async function loadExecutiveMetricsFromApi(config: ApiConfig): Promise<ExecutiveMetric[]> {
  const [studentsResponse, invoicesResponse] = await Promise.all([
    fetchAllPages<ApiStudent>(config, 'students'),
    fetchAllPages<ApiInvoice>(config, 'invoices'),
  ]);

  const students = studentsResponse.rows;
  const invoices = invoicesResponse.rows;

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

  return [
    {
      id: 'students_total',
      title: 'Alunos Ativos',
      value: activeStudents.toLocaleString('pt-BR'),
      trend: `${students.length} cadastrados`,
      tone: 'positive',
      description: 'Quantidade de alunos ativos na empresa do token.',
    },
    {
      id: 'invoices_open',
      title: 'Boletos em Aberto',
      value: formatCurrency(openValue),
      trend: `${openInvoices.length} titulos`,
      tone: 'warning',
      description: 'Faturas em aberto no prazo (open + partial).',
    },
    {
      id: 'invoices_overdue',
      title: 'Boletos Vencidos',
      value: formatCurrency(overdueValue),
      trend: `${overdueInvoices.length} titulos`,
      tone: 'critical',
      description: 'Faturas vencidas com saldo pendente.',
    },
    {
      id: 'invoices_paid',
      title: 'Boletos Pagos',
      value: formatCurrency(paidValue),
      trend: `${paidInvoices.length} titulos`,
      tone: 'positive',
      description: 'Valores recebidos nas faturas pagas.',
    },
    {
      id: 'default_rate',
      title: 'Taxa de Inadimplencia',
      value: formatPercent(delinquencyRate),
      trend: `${overdueInvoices.length}/${totalInvoices || 0} titulos`,
      tone: delinquencyRate >= 20 ? 'critical' : 'neutral',
      description: 'Percentual de titulos vencidos sobre o total.',
    },
    {
      id: 'pending_total',
      title: 'Saldo Pendente',
      value: formatCurrency(pendingValue),
      trend: `Recuperacao estimada ${formatPercent(recoveryRate)}`,
      tone: pendingValue > 0 ? 'warning' : 'positive',
      description: 'Soma dos valores pendentes (abertos + vencidos).',
    },
  ];
}
