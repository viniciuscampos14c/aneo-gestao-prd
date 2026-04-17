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
