import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadDebtProfilesFromApi } from '../services/negotiationService';
import { loadStudentsFromApi } from '../services/studentDirectoryService';
import type { ApiConfig, ApiStudent } from '../types';
import { formatCurrency, formatDateIso } from '../utils/format';

type StudentDirectoryViewProps = {
  apiConfig: ApiConfig | null;
};

type FinancialSummary = {
  invoicesOpen: number;
  openAmount: number;
  overdueAmount: number;
  lastPaymentDate: string;
};

const RESULTS_PAGE_SIZE = 12;

function isActive(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  return Number(value) === 1;
}

function parseNumber(value: unknown): number {
  if (value === null || value === undefined || value === '') return 0;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function formatOptionalDate(value: unknown): string {
  return formatDateIso(String(value ?? ''));
}

function contractEndDate(student: ApiStudent): string {
  const firstDueDate = String(student.financial_plan_first_due_date ?? '');
  const installments = Math.trunc(parseNumber(student.financial_plan_installments));

  if (!firstDueDate || installments <= 0) {
    return '-';
  }

  const [year, month, day] = firstDueDate.split('-').map((part) => Number(part));
  if (!year || !month || !day) {
    return '-';
  }

  const date = new Date(Date.UTC(year, month - 1, day));
  date.setUTCMonth(date.getUTCMonth() + installments - 1);

  return formatDateIso(date.toISOString().slice(0, 10));
}

function financialPlanLabel(student: ApiStudent): string {
  const profile = String(student.financial_plan_profile ?? '').trim();
  const presetMatch = profile.match(/^PRESET_(\d+)_(\d+(?:[.,]\d+)?)$/i);

  if (presetMatch) {
    const installments = Number(presetMatch[1]);
    const amount = Number(String(presetMatch[2]).replace(',', '.'));

    if (installments > 0 && Number.isFinite(amount)) {
      return `Plano ${installments}x de ${formatCurrency(amount)}`;
    }
  }

  if (profile) {
    return profile.replace(/_/g, ' ');
  }

  return parseNumber(student.monthly_fee) > 0 || parseNumber(student.financial_plan_installments) > 0
    ? 'Plano financeiro'
    : 'Não informado';
}

export function StudentDirectoryView({ apiConfig }: StudentDirectoryViewProps) {
  const [rows, setRows] = useState<ApiStudent[]>([]);
  const [financialByStudentId, setFinancialByStudentId] = useState<Record<number, FinancialSummary>>({});
  const [expandedStudentId, setExpandedStudentId] = useState<number | null>(null);
  const [query, setQuery] = useState('');
  const [visibleCount, setVisibleCount] = useState(RESULTS_PAGE_SIZE);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const loadingRef = useRef(false);

  const connected = useMemo(() => !!apiConfig?.token, [apiConfig]);

  const refreshData = useCallback(
    async (mode: 'manual' | 'auto' | 'initial') => {
      if (!apiConfig || loadingRef.current) {
        return;
      }

      loadingRef.current = true;
      setLoading(true);
      setError('');

      try {
        const [students, financialProfiles] = await Promise.all([
          loadStudentsFromApi(apiConfig),
          loadDebtProfilesFromApi(apiConfig),
        ]);
        setRows(students);

        const map: Record<number, FinancialSummary> = {};
        for (const profile of financialProfiles) {
          map[profile.id] = {
            invoicesOpen: profile.invoicesOpen,
            openAmount: profile.openAmount,
            overdueAmount: profile.overdueAmount,
            lastPaymentDate: profile.lastPaymentDate,
          };
        }
        setFinancialByStudentId(map);
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Falha ao carregar alunos.';
        setError(mode === 'manual' ? `Atualização manual falhou: ${message}` : message);
      } finally {
        loadingRef.current = false;
        setLoading(false);
      }
    },
    [apiConfig]
  );

  useEffect(() => {
    if (!apiConfig) {
      const timeoutId = window.setTimeout(() => {
        setRows([]);
        setFinancialByStudentId({});
        setExpandedStudentId(null);
        setLoading(false);
        setError('');
      }, 0);

      return () => window.clearTimeout(timeoutId);
    }

    const timeoutId = window.setTimeout(() => {
      void refreshData('initial');
    }, 0);

    return () => window.clearTimeout(timeoutId);
  }, [apiConfig, refreshData]);

  useEffect(() => {
    if (!apiConfig) {
      return;
    }

    const intervalId = window.setInterval(() => {
      void refreshData('auto');
    }, AUTO_REFRESH_MS);

    return () => window.clearInterval(intervalId);
  }, [apiConfig, refreshData]);

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return rows;

    return rows.filter((student) => {
      const name = String(student.full_name ?? '').toLowerCase();
      const email = String(student.email_primary ?? '').toLowerCase();
      const phone = String(student.phone ?? '').toLowerCase();
      return name.includes(term) || email.includes(term) || phone.includes(term);
    });
  }, [rows, query]);

  const visibleRows = useMemo(() => filtered.slice(0, visibleCount), [filtered, visibleCount]);
  const hasMoreResults = visibleRows.length < filtered.length;

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      setVisibleCount(RESULTS_PAGE_SIZE);
    }, 0);

    return () => window.clearTimeout(timeoutId);
  }, [query, rows]);

  return (
    <section className="surface-card">
      <div className="module-toolbar">
        <div>
          <p className="eyebrow">Base de alunos</p>
          <h3>Consulta rápida</h3>
          <p className="muted">Expanda um aluno para ver a situação financeira resumida.</p>
          {error ? <p className="alert-text">{error}</p> : null}
        </div>

        <button
          type="button"
          className="primary-button"
          onClick={() => void refreshData('manual')}
          disabled={!connected || loading}
        >
          {loading ? 'Atualizando...' : 'Atualizar agora'}
        </button>
      </div>

      {connected ? (
        <>
          <input
            className="search-input"
            type="search"
            inputMode="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Buscar aluno por nome, e-mail ou telefone..."
            style={{ marginTop: 16 }}
          />

          {loading && filtered.length === 0 ? (
            <div className="skeleton-grid" style={{ marginTop: 16 }}>
              <div className="skeleton-card" />
              <div className="skeleton-card" />
              <div className="skeleton-card" />
            </div>
          ) : null}

          <div className="list-stack" style={{ marginTop: 16 }}>
            <div className="list-results-meta">
              <span className="muted">
                {filtered.length === 0
                  ? 'Nenhum aluno encontrado'
                  : `Mostrando ${visibleRows.length} de ${filtered.length} alunos`}
              </span>
              {hasMoreResults ? (
                <button
                  type="button"
                  className="secondary-button list-more-button"
                  onClick={() => setVisibleCount((current) => current + RESULTS_PAGE_SIZE)}
                >
                  Ver mais alunos
                </button>
              ) : null}
            </div>

            {visibleRows.map((student) => {
              const active = isActive(student.is_active);
              const financial = financialByStudentId[student.id];
              const overdue = Number(financial?.overdueAmount ?? 0);
              const open = Number(financial?.openAmount ?? 0);
              const totalPending = overdue + open;
              const financialStatus = totalPending > 0 ? 'inadimplente' : 'adimplente';
              const isExpanded = expandedStudentId === student.id;
              const monthlyFee = parseNumber(student.monthly_fee);
              const installments = Math.trunc(parseNumber(student.financial_plan_installments));

              return (
                <article key={student.id} className="list-card">
                  <div className="inline-between">
                    <strong>{student.full_name}</strong>
                    <span className={`pill ${active ? 'pill-success' : 'pill-danger'}`}>
                      {active ? 'Ativo' : 'Inativo'}
                    </span>
                  </div>

                  <p className="muted">E-mail: {String(student.email_primary ?? '-')}</p>
                  <p className="muted">Telefone: {String(student.phone ?? '-')}</p>
                  <p className="muted">RA/RG: {String(student.ra ?? student.rg ?? '-')}</p>

                  <div className="install-actions compact-actions">
                    <button
                      type="button"
                      className="secondary-button"
                      onClick={() => {
                        setExpandedStudentId((current) => (current === student.id ? null : student.id));
                      }}
                    >
                      {isExpanded ? 'Ocultar financeiro' : 'Ver financeiro'}
                    </button>
                  </div>

                  {isExpanded ? (
                    <div className="detail-card">
                      <div className="inline-between">
                        <h4>Situação Financeira</h4>
                        <span className={`pill ${financialStatus === 'adimplente' ? 'pill-success' : 'pill-danger'}`}>
                          {financialStatus === 'adimplente' ? 'Adimplente' : 'Inadimplente'}
                        </span>
                      </div>
                      <p className="muted">Titulos em aberto: {Number(financial?.invoicesOpen ?? 0)}</p>
                      <p className="muted">Saldo em aberto: {formatCurrency(open)}</p>
                      <p className="muted">Saldo vencido: {formatCurrency(overdue)}</p>
                      <p className="muted">Pendencia total: {formatCurrency(totalPending)}</p>
                      <p className="muted">
                        Ultimo pagamento: {formatDateIso(String(financial?.lastPaymentDate ?? ''))}
                      </p>

                      <div className="student-plan-card">
                        <div className="inline-between">
                          <h4>Dados para renegociacao</h4>
                          <span className="pill pill-warning">{financialPlanLabel(student)}</span>
                        </div>
                        <div className="student-plan-grid">
                          <span>
                            <strong>Entrada do aluno</strong>
                            {formatOptionalDate(student.enrolled_at)}
                          </span>
                          <span>
                            <strong>Fim estimado do contrato</strong>
                            {contractEndDate(student)}
                          </span>
                          <span>
                            <strong>Mensalidade</strong>
                            {monthlyFee > 0 ? formatCurrency(monthlyFee) : '-'}
                          </span>
                          <span>
                            <strong>Parcelas do plano</strong>
                            {installments > 0 ? installments : '-'}
                          </span>
                          <span>
                            <strong>Primeiro vencimento</strong>
                            {formatOptionalDate(student.financial_plan_first_due_date)}
                          </span>
                          <span>
                            <strong>Dia de vencimento</strong>
                            {student.billing_day ? String(student.billing_day) : '-'}
                          </span>
                        </div>
                      </div>
                    </div>
                  ) : null}
                </article>
              );
            })}
            {!loading && filtered.length === 0 ? (
              <div className="empty-state">
                <h4>Nenhum aluno encontrado</h4>
                <p className="muted">Ajuste a busca por nome, e-mail ou telefone para localizar a base.</p>
              </div>
            ) : null}
            {!loading && filtered.length > 0 && hasMoreResults ? (
              <button
                type="button"
                className="secondary-button list-load-more"
                onClick={() => setVisibleCount((current) => current + RESULTS_PAGE_SIZE)}
              >
                Carregar mais {Math.min(RESULTS_PAGE_SIZE, filtered.length - visibleRows.length)} alunos
              </button>
            ) : null}
          </div>
        </>
      ) : (
        <div className="status-card" style={{ marginTop: 16 }}>
          <p className="muted">Conecte a API para listar alunos.</p>
        </div>
      )}
    </section>
  );
}
