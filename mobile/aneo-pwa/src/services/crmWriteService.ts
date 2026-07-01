import type { ApiConfig, ApiEnvelope, StudentDebtInvoice, StudentDebtProfile } from '../types';
import { apiPost } from './apiClient';
import { formatCurrency } from '../utils/format';

type NegotiationWriteMode = 'negociacao' | 'aditivo';
type NegotiationScope = 'total' | 'overdue';

type TicketResponse = {
  id: number;
  subject: string;
  status: string;
};

type SendNegotiationInput = {
  mode: NegotiationWriteMode;
  scope: NegotiationScope;
  profile: StudentDebtProfile;
  discountPercent: number;
  installments: number;
  firstDueDate: string;
  totalDebt: number;
  discountedTotal: number;
  installmentValue: number;
  scopedInvoices: StudentDebtInvoice[];
  paymentChannel: string;
  paymentMethodId?: number | null;
  paymentMethodName?: string;
};

function ticketSubject(mode: NegotiationWriteMode, studentName: string): string {
  if (mode === 'aditivo') {
    return `Aditivo financeiro - ${studentName}`;
  }
  return `Negociacao financeira - ${studentName}`;
}

function ticketDescription(input: SendNegotiationInput): string {
  const {
    mode,
    profile,
    discountPercent,
    installments,
    firstDueDate,
    totalDebt,
    discountedTotal,
    installmentValue,
    scope,
    scopedInvoices,
    paymentChannel,
    paymentMethodId,
    paymentMethodName,
  } = input;

  const scopeLabel = scope === 'overdue' ? 'Parcelas vencidas' : 'Saldo total do aluno';
  const invoiceLines =
    scope === 'overdue' && scopedInvoices.length > 0
      ? [
          'Faturas vencidas consideradas:',
          ...scopedInvoices.map(
            (invoice) =>
              `- ${invoice.number} | vencimento ${invoice.dueDate || '-'} | pendente ${formatCurrency(invoice.outstandingAmount)}`
          ),
        ]
      : [];

  const lines = [
    `Origem: App Mobile Diretoria`,
    `Tipo: ${mode === 'aditivo' ? 'Geracao de aditivo' : 'Negociacao financeira'}`,
    `Escopo da renegociacao: ${scopeLabel}`,
    `Aluno: ${profile.name} (ID ${profile.id})`,
    `Documento: ${profile.document}`,
    `Titulos em aberto: ${profile.invoicesOpen}`,
    `Saldo original: ${formatCurrency(totalDebt)}`,
    `Desconto aplicado: ${discountPercent.toFixed(2)}%`,
    `Novo valor total: ${formatCurrency(discountedTotal)}`,
    `Parcelamento: ${installments}x de ${formatCurrency(installmentValue)}`,
    `Primeiro vencimento: ${firstDueDate}`,
    `Canal de pagamento: ${paymentChannel || 'não informado'}`,
    `Saldo aberto atual: ${formatCurrency(profile.openAmount)}`,
    `Saldo vencido atual: ${formatCurrency(profile.overdueAmount)}`,
    ...invoiceLines,
    `Observacao: registro criado automaticamente para fluxo financeiro.`,
  ];

  if (paymentMethodId && paymentMethodName) {
    lines.splice(11, 0, `Forma de pagamento selecionada: ${paymentMethodName} (ID ${paymentMethodId})`);
  }

  return lines.join('\n');
}

export async function sendNegotiationToCrm(
  config: ApiConfig,
  input: SendNegotiationInput
): Promise<ApiEnvelope<TicketResponse>> {
  const payload = {
    subject: ticketSubject(input.mode, input.profile.name),
    description: ticketDescription(input),
    requester_name: 'Diretoria ANEO (App Mobile)',
    requester_email: '',
    requester_phone: '',
    priority: input.mode === 'aditivo' ? 'high' : 'normal',
    category: 'financeiro',
    external_reference: `student:${input.profile.id}`,
  };

  return apiPost<TicketResponse, typeof payload>(config, 'tickets', payload);
}
