import type { ApiConfig, ApiInvoice, ApiStudent, StudentDebtInvoice, StudentDebtProfile } from '../types';
import { fetchAllPages } from './apiClient';

function asNumber(value: unknown): number {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function outstandingAmount(invoice: ApiInvoice): number {
  return Math.max(0, asNumber(invoice.amount) - asNumber(invoice.paid_amount));
}

function compareIsoDesc(a: string, b: string): number {
  if (a === b) return 0;
  return a > b ? -1 : 1;
}

function safeIsoDate(value: unknown): string {
  const text = String(value ?? '').trim();
  if (!text) return '';
  return text.slice(0, 10);
}

export async function loadDebtProfilesFromApi(config: ApiConfig): Promise<StudentDebtProfile[]> {
  const [studentsResponse, invoicesResponse] = await Promise.all([
    fetchAllPages<ApiStudent>(config, 'students'),
    fetchAllPages<ApiInvoice>(config, 'invoices'),
  ]);

  const students = studentsResponse.rows;
  const invoices = invoicesResponse.rows;
  const invoicesByStudent = new Map<number, ApiInvoice[]>();

  for (const invoice of invoices) {
    const studentId = asNumber(invoice.student_id);
    if (studentId <= 0) continue;
    const bucket = invoicesByStudent.get(studentId) ?? [];
    bucket.push(invoice);
    invoicesByStudent.set(studentId, bucket);
  }

  const profiles: StudentDebtProfile[] = students.map((student) => {
    const studentId = asNumber(student.id);
    const studentInvoices = invoicesByStudent.get(studentId) ?? [];

    const openInvoices = studentInvoices.filter(
      (invoice) =>
        invoice.status === 'open' || invoice.status === 'partial' || invoice.status === 'overdue'
    );

    const openAmount = studentInvoices
      .filter((invoice) => invoice.status === 'open' || invoice.status === 'partial')
      .reduce((sum, invoice) => sum + outstandingAmount(invoice), 0);

    const overdueAmount = studentInvoices
      .filter((invoice) => invoice.status === 'overdue')
      .reduce((sum, invoice) => sum + outstandingAmount(invoice), 0);

    const overdueInvoices: StudentDebtInvoice[] = studentInvoices
      .filter((invoice) => invoice.status === 'overdue')
      .map((invoice) => ({
        id: asNumber(invoice.id),
        number: String(invoice.invoice_number ?? '').trim() || `Título ${invoice.id}`,
        dueDate: safeIsoDate(invoice.due_date),
        amount: asNumber(invoice.amount),
        paidAmount: asNumber(invoice.paid_amount),
        outstandingAmount: outstandingAmount(invoice),
        status: invoice.status,
      }))
      .sort((a, b) => compareIsoDesc(a.dueDate, b.dueDate));

    const paidDates = studentInvoices
      .map((invoice) => safeIsoDate(invoice.paid_at))
      .filter((date) => date !== '');
    paidDates.sort(compareIsoDesc);

    const document = String(student.rg ?? '').trim() || String(student.ra ?? '').trim() || `ID ${studentId}`;

    return {
      id: studentId,
      name: student.full_name,
      document,
      course: 'Não informado',
      invoicesOpen: openInvoices.length,
      openAmount,
      overdueAmount,
      lastPaymentDate: paidDates[0] ?? '',
      overdueInvoices,
    };
  });

  profiles.sort((a, b) => {
    const pendingA = a.openAmount + a.overdueAmount;
    const pendingB = b.openAmount + b.overdueAmount;
    return pendingB - pendingA;
  });

  return profiles;
}
