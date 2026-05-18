import type { ApiConfig, ApiEnvelope, StudentDebtProfile } from '../types';
import { apiPost } from './apiClient';
import { formatCurrency } from '../utils/format';

type NegotiationWriteMode = 'negociacao' | 'aditivo';

type TicketResponse = {
  id: number;
  subject: string;
  status: string;
};

type SendNegotiationInput = {
  mode: NegotiationWriteMode;
  profile: StudentDebtProfile;
  discountPercent: number;
  installments: number;
  firstDueDate: string;
  totalDebt: number;
  discountedTotal: number;
  installmentValue: number;
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
  } = input;

  const lines = [
    `Origem: App Mobile Diretoria`,
    `Tipo: ${mode === 'aditivo' ? 'Geracao de aditivo' : 'Negociacao financeira'}`,
    `Aluno: ${profile.name} (ID ${profile.id})`,
    `Documento: ${profile.document}`,
    `Titulos em aberto: ${profile.invoicesOpen}`,
    `Saldo original: ${formatCurrency(totalDebt)}`,
    `Desconto aplicado: ${discountPercent.toFixed(2)}%`,
    `Novo valor total: ${formatCurrency(discountedTotal)}`,
    `Parcelamento: ${installments}x de ${formatCurrency(installmentValue)}`,
    `Primeiro vencimento: ${firstDueDate}`,
    `Saldo aberto atual: ${formatCurrency(profile.openAmount)}`,
    `Saldo vencido atual: ${formatCurrency(profile.overdueAmount)}`,
    `Observacao: registro criado automaticamente para fluxo financeiro.`,
  ];

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
  };

  return apiPost<TicketResponse, typeof payload>(config, 'tickets', payload);
}
