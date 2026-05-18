export type MetricTone = 'positive' | 'warning' | 'critical' | 'neutral';

export type ExecutiveMetric = {
  id: string;
  title: string;
  value: string;
  trend: string;
  tone: MetricTone;
  description: string;
};

export type ExecutiveSummaryTone = 'positive' | 'warning' | 'critical';

export type ExecutiveSummary = {
  headline: string;
  message: string;
  tone: ExecutiveSummaryTone;
  pendingLabel: string;
  pendingValue: string;
  recoveryLabel: string;
  recoveryValue: string;
};

export type ExecutiveAlert = {
  id: string;
  category: string;
  priority: string;
  title: string;
  detail: string;
  actionLabel: string;
  targetTab: 'negotiation' | 'trial-access' | 'students' | 'tickets' | 'connection';
  tone: MetricTone;
};

export type ExecutiveSnapshot = {
  id: string;
  label: string;
  value: string;
  helper: string;
};

export type ExecutivePulse = {
  id: string;
  label: string;
  status: string;
  detail: string;
  tone: MetricTone;
};

export type ExecutiveDashboardData = {
  metrics: ExecutiveMetric[];
  summary: ExecutiveSummary;
  alerts: ExecutiveAlert[];
  snapshots: ExecutiveSnapshot[];
  pulses: ExecutivePulse[];
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

export type ApiCourse = {
  id: number;
  name: string;
  status?: string | null;
};

export type ApiTrialAccess = {
  id: number;
  student_id: number;
  course_id: number;
  access_date: string;
  status: string;
  created_at?: string | null;
  last_login_at?: string | null;
  student_name?: string | null;
  student_email?: string | null;
  student_phone?: string | null;
  course_name?: string | null;
  portal_login?: string | null;
};

export type ApiTicket = {
  id: number;
  ticket_code?: string | null;
  subject?: string | null;
  status?: string | null;
  priority?: string | null;
  requester_name?: string | null;
  requester_email?: string | null;
  source?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};
