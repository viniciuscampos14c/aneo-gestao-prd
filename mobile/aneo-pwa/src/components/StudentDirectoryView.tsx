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

function isActive(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  return Number(value) === 1;
}

export function StudentDirectoryView({ apiConfig }: StudentDirectoryViewProps) {
  const [rows, setRows] = useState<ApiStudent[]>([]);
  const [financialByStudentId, setFinancialByStudentId] = useState<Record<number, FinancialSummary>>({});
  const [expandedStudentId, setExpandedStudentId] = useState<number | null>(null);
  const [query, setQuery] = useState('');
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
        setError(mode === 'manual' ? `Atualizacao manual falhou: ${message}` : message);
      } finally {
        loadingRef.current = false;
        setLoading(false);
      }
    },
    [apiConfig]
  );

  useEffect(() => {
    if (!apiConfig) {
      setRows([]);
      setFinancialByStudentId({});
      setExpandedStudentId(null);
      setLoading(false);
      setError('');
      return;
    }

    void refreshData('initial');
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

  return (
    <section className="surface-card">
      <div className="inline-between">
        <div>
          <p className="eyebrow">Base de alunos</p>
          <h3>Consulta rapida</h3>
          <p className="muted">Expanda um aluno para ver a situacao financeira resumida.</p>
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
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Buscar aluno por nome, e-mail ou telefone..."
            style={{ marginTop: 16 }}
          />

          <div className="list-stack" style={{ marginTop: 16 }}>
            {filtered.map((student) => {
              const active = isActive(student.is_active);
              const financial = financialByStudentId[student.id];
              const overdue = Number(financial?.overdueAmount ?? 0);
              const open = Number(financial?.openAmount ?? 0);
              const totalPending = overdue + open;
              const financialStatus = totalPending > 0 ? 'inadimplente' : 'adimplente';
              const isExpanded = expandedStudentId === student.id;

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

                  <div className="install-actions">
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
                        <h4>Situacao Financeira</h4>
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
                    </div>
                  ) : null}
                </article>
              );
            })}
            {filtered.length === 0 ? <p className="muted">Nenhum aluno encontrado.</p> : null}
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
