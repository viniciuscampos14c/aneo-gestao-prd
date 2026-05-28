import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadTicketsFromApi } from '../services/ticketCenterService';
import type { ApiConfig, ApiTicket } from '../types';

type TicketCenterViewProps = {
  apiConfig: ApiConfig | null;
};

const RESULTS_PAGE_SIZE = 12;

function isAdditiveTicket(ticket: ApiTicket): boolean {
  const subject = String(ticket.subject ?? '').trim().toLowerCase();
  return subject.startsWith('aditivo financeiro -');
}

function extractStudentName(ticket: ApiTicket): string {
  const subject = String(ticket.subject ?? '').trim();
  if (/^aditivo financeiro - /i.test(subject)) {
    return subject.replace(/^aditivo financeiro - /i, '').trim() || 'Aluno nao identificado';
  }

  const description = String(ticket.description ?? '');
  const descriptionMatch = description.match(/Aluno:\s*(.+?)\s+\(ID\s*\d+\)/i);
  if (descriptionMatch?.[1]) {
    return descriptionMatch[1].trim();
  }

  return 'Aluno nao identificado';
}

function statusLabel(status: string | null | undefined): string {
  const normalized = String(status ?? '').trim().toLowerCase();
  if (normalized === 'open') return 'Aberto';
  if (normalized === 'in_progress') return 'Em andamento';
  if (normalized === 'resolved') return 'Resolvido';
  if (normalized === 'closed') return 'Fechado';
  return normalized === '' ? '-' : normalized;
}

function additiveStatusLabel(status: string | null | undefined): string {
  const normalized = String(status ?? '').trim().toLowerCase();
  if (normalized === 'open') return 'Aguardando analise';
  if (normalized === 'in_progress') return 'Solicitar ajuste';
  if (normalized === 'resolved') return 'Aprovado';
  if (normalized === 'closed') return 'Reprovado';
  return statusLabel(status);
}

function additiveStatusClass(status: string | null | undefined): string {
  const normalized = String(status ?? '').trim().toLowerCase();
  if (normalized === 'resolved') return 'pill-success';
  if (normalized === 'closed') return 'pill-danger';
  return 'pill-warning';
}

function priorityLabel(priority: string | null | undefined): string {
  const normalized = String(priority ?? '').trim().toLowerCase();
  if (normalized === 'low') return 'Baixa';
  if (normalized === 'medium') return 'Media';
  if (normalized === 'high') return 'Alta';
  if (normalized === 'urgent') return 'Urgente';
  return normalized === '' ? '-' : normalized;
}

export function TicketCenterView({ apiConfig }: TicketCenterViewProps) {
  const [rows, setRows] = useState<ApiTicket[]>([]);
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
        const tickets = await loadTicketsFromApi(apiConfig);
        setRows(tickets);
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Falha ao carregar chamados.';
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
    const additiveRows = rows.filter(isAdditiveTicket);
    if (!term) return additiveRows;

    return additiveRows.filter((ticket) => {
      const code = String(ticket.ticket_code ?? '').toLowerCase();
      const subject = String(ticket.subject ?? '').toLowerCase();
      const studentName = extractStudentName(ticket).toLowerCase();
      const requester = String(ticket.requester_name ?? '').toLowerCase();
      return (
        code.includes(term) ||
        subject.includes(term) ||
        studentName.includes(term) ||
        requester.includes(term)
      );
    });
  }, [rows, query]);

  const visibleRows = useMemo(() => filtered.slice(0, visibleCount), [filtered, visibleCount]);
  const hasMoreResults = visibleRows.length < filtered.length;
  const additiveSummary = useMemo(() => {
    return filtered.reduce(
      (accumulator, ticket) => {
        const normalized = String(ticket.status ?? '').trim().toLowerCase();
        accumulator.total += 1;
        if (normalized === 'resolved') {
          accumulator.approved += 1;
        } else if (normalized === 'in_progress') {
          accumulator.adjust += 1;
        } else if (normalized === 'closed') {
          accumulator.rejected += 1;
        } else {
          accumulator.pending += 1;
        }
        return accumulator;
      },
      { total: 0, pending: 0, adjust: 0, approved: 0, rejected: 0 }
    );
  }, [filtered]);

  useEffect(() => {
    setVisibleCount(RESULTS_PAGE_SIZE);
  }, [query, rows]);

  return (
    <section className="surface-card">
      <div className="module-toolbar">
        <div>
          <p className="eyebrow">Fluxo mobile</p>
          <h3>Acompanhamento de aditivos</h3>
          <p className="muted">Acompanhe por aluno se o aditivo está aguardando analise, em ajuste, aprovado ou reprovado.</p>
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
          <div className="field-grid" style={{ marginTop: 16 }}>
            <article className="summary-stat-card">
              <span className="eyebrow">Na fila</span>
              <strong>{additiveSummary.pending}</strong>
              <p className="muted">Aguardando retorno do administrativo.</p>
            </article>
            <article className="summary-stat-card">
              <span className="eyebrow">Em ajuste</span>
              <strong>{additiveSummary.adjust}</strong>
              <p className="muted">Propostas devolvidas para revisao.</p>
            </article>
            <article className="summary-stat-card">
              <span className="eyebrow">Aprovados</span>
              <strong>{additiveSummary.approved}</strong>
              <p className="muted">Aditivos liberados pela equipe.</p>
            </article>
            <article className="summary-stat-card">
              <span className="eyebrow">Reprovados</span>
              <strong>{additiveSummary.rejected}</strong>
              <p className="muted">Fluxos encerrados sem aprovacao.</p>
            </article>
          </div>

          <input
            className="search-input"
            type="search"
            inputMode="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Buscar por aluno ou codigo do aditivo..."
            style={{ marginTop: 16 }}
          />

          <div className="status-card" style={{ marginTop: 16 }}>
            <p className="muted">
              Aqui a diretoria acompanha apenas o essencial: aluno, codigo do aditivo e o retorno da equipe administrativa.
            </p>
          </div>

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
                  ? 'Nenhum aditivo encontrado'
                  : `Mostrando ${visibleRows.length} de ${filtered.length} aditivos`}
              </span>
              {hasMoreResults ? (
                <button
                  type="button"
                  className="secondary-button list-more-button"
                  onClick={() => setVisibleCount((current) => current + RESULTS_PAGE_SIZE)}
                >
                  Ver mais aditivos
                </button>
              ) : null}
            </div>

            {visibleRows.map((ticket) => (
              <article key={ticket.id} className="list-card">
                <div className="ticket-row-header">
                  <strong>{String(ticket.ticket_code ?? `ID ${ticket.id}`)}</strong>
                  <span className={`pill ${additiveStatusClass(ticket.status)}`}>{additiveStatusLabel(ticket.status)}</span>
                </div>
                <h4>{extractStudentName(ticket)}</h4>
                <p className="muted">Assunto: {String(ticket.subject ?? 'Sem assunto')}</p>
                <p className="muted">Solicitante: {String(ticket.requester_name ?? '-')}</p>
                <p className="muted">Prioridade: {priorityLabel(ticket.priority)}</p>
                <p className="muted">Fluxo administrativo: {statusLabel(ticket.status)}</p>
              </article>
            ))}
            {!loading && filtered.length === 0 ? (
              <div className="empty-state">
                <h4>Nenhum aditivo encontrado</h4>
                <p className="muted">Quando a diretoria gerar um aditivo no app, ele aparecerá aqui para acompanhamento.</p>
              </div>
            ) : null}
            {!loading && filtered.length > 0 && hasMoreResults ? (
              <button
                type="button"
                className="secondary-button list-load-more"
                onClick={() => setVisibleCount((current) => current + RESULTS_PAGE_SIZE)}
              >
                Carregar mais {Math.min(RESULTS_PAGE_SIZE, filtered.length - visibleRows.length)} aditivos
              </button>
            ) : null}
          </div>
        </>
      ) : (
        <div className="status-card" style={{ marginTop: 16 }}>
          <p className="muted">Conecte a API para acompanhar os aditivos gerados no app.</p>
        </div>
      )}
    </section>
  );
}
