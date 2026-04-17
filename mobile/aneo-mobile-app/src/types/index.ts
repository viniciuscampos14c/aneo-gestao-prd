export type MetricTone = 'positive' | 'warning' | 'critical' | 'neutral';

export type ExecutiveMetric = {
  id: string;
  title: string;
  value: string;
  trend: string;
  tone: MetricTone;
  description: string;
};

export type StudentDebtProfile = {
  id: number;
  name: string;
  document: string;
  course: string;
  invoicesOpen: number;
  openAmount: number;
  overdueAmount: number;
  lastPaymentDate: string;
};

export type ApiConfig = {
  baseUrl: string;
  token: string;
};

export type ApiMeta = {
  total: number;
  per_page: number;
  page: number;
  pages: number;
};

export type ApiEnvelope<TData> = {
  ok: boolean;
  data: TData;
  meta?: ApiMeta;
  message?: string;
  code?: number;
};

export type ApiStudent = {
  id: number;
  full_name: string;
  email_primary?: string | null;
  phone?: string | null;
  rg?: string | null;
  ra?: string | null;
  is_active?: number | string | boolean;
};

export type ApiInvoice = {
  id: number;
  student_id: number | string;
  invoice_number?: string | null;
  status: string;
  amount: number | string;
  paid_amount: number | string;
  due_date?: string | null;
  paid_at?: string | null;
};
