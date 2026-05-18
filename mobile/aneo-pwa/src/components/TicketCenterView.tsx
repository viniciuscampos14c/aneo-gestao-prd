import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadTicketsFromApi } from '../services/ticketCenterService';
import type { ApiConfig, ApiTicket } from '../types';

type TicketCenterViewProps = {
  apiConfig: ApiConfig | null;
};

const RESULTS_PAGE_SIZE = 12;

function statusLabel(status: string | null | undefined): string {
  const normalized = String(status ?? '').trim().toLowerCase();
  if (normalized === 'open') return 'Aberto';
  if (normalized === 'in_progress') return 'Em andamento';
  if (normalized === 'resolved') return 'Resolvido';
  if (normalized === 'closed') return 'Fechado';
  return normalized === '' ? '-' : normalized;
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
    if (!term) return rows;

    return rows.filter((ticket) => {
      const code = String(ticket.ticket_code ?? '').toLowerCase();
      const subject = String(ticket.subject ?? '').toLowerCase();
      const requester = String(ticket.requester_name ?? '').toLowerCase();
      return code.includes(term) || subject.includes(term) || requester.includes(term);
    });
  }, [rows, query]);

  const visibleRows = useMemo(() => filtered.slice(0, visibleCount), [filtered, visibleCount]);
  const hasMoreResults = visibleRows.length < filtered.length;

  useEffect(() => {
    setVisibleCount(RESULTS_PAGE_SIZE);
  }, [query, rows]);

  return (
    <section className="surface-card">
      <div className="module-toolbar">
        <div>
          <p className="eyebrow">Chamados</p>
          <h3>Central de chamados</h3>
          <p className="muted">Listagem executiva para acompanhar solicitacoes abertas e em andamento.</p>
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
            placeholder="Buscar por codigo, assunto ou solicitante..."
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
                  ? 'Nenhum chamado encontrado'
                  : `Mostrando ${visibleRows.length} de ${filtered.length} chamados`}
              </span>
              {hasMoreResults ? (
                <button
                  type="button"
                  className="secondary-button list-more-button"
                  onClick={() => setVisibleCount((current) => current + RESULTS_PAGE_SIZE)}
                >
                  Ver mais chamados
                </button>
              ) : null}
            </div>

            {visibleRows.map((ticket) => (
              <article key={ticket.id} className="list-card">
                <div className="ticket-row-header">
                  <strong>{String(ticket.ticket_code ?? `ID ${ticket.id}`)}</strong>
                  <span className="pill pill-success">{statusLabel(ticket.status)}</span>
                </div>
                <h4>{String(ticket.subject ?? 'Sem assunto')}</h4>
                <p className="muted">Solicitante: {String(ticket.requester_name ?? '-')}</p>
                <p className="muted">Prioridade: {priorityLabel(ticket.priority)}</p>
                <p className="muted">Origem: {String(ticket.source ?? '-')}</p>
              </article>
            ))}
            {!loading && filtered.length === 0 ? (
              <div className="empty-state">
                <h4>Nenhum chamado encontrado</h4>
                <p className="muted">Tente buscar por codigo, assunto ou solicitante.</p>
              </div>
            ) : null}
            {!loading && filtered.length > 0 && hasMoreResults ? (
              <button
                type="button"
                className="secondary-button list-load-more"
                onClick={() => setVisibleCount((current) => current + RESULTS_PAGE_SIZE)}
              >
                Carregar mais {Math.min(RESULTS_PAGE_SIZE, filtered.length - visibleRows.length)} chamados
              </button>
            ) : null}
          </div>
        </>
      ) : (
        <div className="status-card" style={{ marginTop: 16 }}>
          <p className="muted">Conecte a API para listar chamados.</p>
        </div>
      )}
    </section>
  );
}
