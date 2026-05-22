import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import { sendNegotiationToCrm } from '../services/crmWriteService';
import { loadDebtProfilesFromApi } from '../services/negotiationService';
import type { ApiConfig, StudentDebtProfile } from '../types';
import { formatCurrency, formatDateIso } from '../utils/format';

type NegotiationViewProps = {
  apiConfig: ApiConfig | null;
};

const RESULTS_PAGE_SIZE = 12;

function localIsoDateNow(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function NegotiationView({ apiConfig }: NegotiationViewProps) {
  const [negotiationScope, setNegotiationScope] = useState<'total' | 'overdue'>('total');
  const [query, setQuery] = useState('');
  const [profiles, setProfiles] = useState<StudentDebtProfile[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [listCollapsed, setListCollapsed] = useState(false);
  const [visibleCount, setVisibleCount] = useState(RESULTS_PAGE_SIZE);
  const [discountPercent, setDiscountPercent] = useState('8');
  const [installments, setInstallments] = useState('3');
  const [firstDueDate, setFirstDueDate] = useState(localIsoDateNow());
  const [lastAction, setLastAction] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [sending, setSending] = useState<'none' | 'aditivo' | 'negociacao'>('none');
  const loadingRef = useRef(false);
  const searchSectionRef = useRef<HTMLElement | null>(null);
  const simulatorSectionRef = useRef<HTMLElement | null>(null);

  const connected = useMemo(() => !!apiConfig?.token, [apiConfig]);
  const selected = useMemo(
    () => profiles.find((profile) => profile.id === selectedId) ?? null,
    [profiles, selectedId]
  );

  const refreshProfiles = useCallback(
    async (mode: 'manual' | 'auto' | 'initial') => {
      if (!apiConfig || loadingRef.current) {
        return;
      }

      loadingRef.current = true;
      setLoading(true);
      setError('');

      try {
        const rows = await loadDebtProfilesFromApi(apiConfig);
        setProfiles(rows);
      } catch (err) {
        const message =
          err instanceof Error ? err.message : 'Falha ao carregar negociacoes em tempo real.';
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
      setProfiles([]);
      setSelectedId(null);
      setListCollapsed(false);
      setLoading(false);
      setError('');
      setLastAction('');
      return;
    }

    void refreshProfiles('initial');
  }, [apiConfig, refreshProfiles]);

  useEffect(() => {
    if (!apiConfig) {
      return;
    }

    const intervalId = window.setInterval(() => {
      void refreshProfiles('auto');
    }, AUTO_REFRESH_MS);

    return () => window.clearInterval(intervalId);
  }, [apiConfig, refreshProfiles]);

  useEffect(() => {
    if (!selected) {
      return;
    }

    setNegotiationScope('total');
    setListCollapsed(true);
    window.setTimeout(() => {
      simulatorSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 80);
  }, [selected]);

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return profiles;

    return profiles.filter((student) => {
      return (
        student.name.toLowerCase().includes(term) || student.document.toLowerCase().includes(term)
      );
    });
  }, [query, profiles]);

  const visibleProfiles = useMemo(
    () => filtered.slice(0, visibleCount),
    [filtered, visibleCount]
  );

  const hasMoreResults = visibleProfiles.length < filtered.length;

  useEffect(() => {
    setVisibleCount(RESULTS_PAGE_SIZE);
  }, [query, profiles]);

  const scopedInvoices = useMemo(() => {
    if (!selected) {
      return [];
    }

    if (negotiationScope === 'overdue') {
      return selected.overdueInvoices;
    }

    return [];
  }, [negotiationScope, selected]);

  const totalDebt = useMemo(() => {
    if (!selected) {
      return 0;
    }

    if (negotiationScope === 'overdue') {
      return selected.overdueAmount;
    }

    return selected.openAmount + selected.overdueAmount;
  }, [negotiationScope, selected]);

  const simulatedDeal = useMemo(() => {
    if (!selected) {
      return {
        withDiscount: 0,
        installmentValue: 0,
      };
    }

    const discount = Number(discountPercent) / 100;
    const parcels = Math.max(1, Number(installments));
    const withDiscount = totalDebt * (1 - (Number.isNaN(discount) ? 0 : discount));
    return {
      withDiscount,
      installmentValue: withDiscount / parcels,
    };
  }, [selected, discountPercent, installments, totalDebt]);

  async function handleSend(mode: 'aditivo' | 'negociacao') {
    if (!apiConfig || !selected) {
      setError('Conecte a API e selecione um aluno para enviar.');
      return;
    }

    setSending(mode);
    setError('');
    setLastAction('');

    try {
      const response = await sendNegotiationToCrm(apiConfig, {
        mode,
        scope: negotiationScope,
        profile: selected,
        discountPercent: Number(discountPercent) || 0,
        installments: Math.max(1, Number(installments) || 1),
        firstDueDate,
        totalDebt,
        discountedTotal: simulatedDeal.withDiscount,
        installmentValue: simulatedDeal.installmentValue,
        scopedInvoices,
      });

      const ticketId = (response.data as { id?: number })?.id;
      setLastAction(
        `${mode === 'aditivo' ? 'Aditivo' : 'Negociacao'} registrada com sucesso${
          ticketId ? ` (ID ${ticketId})` : ''
        }.`
      );
      await refreshProfiles('manual');
    } catch (err) {
      const message =
        err instanceof Error ? err.message : 'Falha ao enviar negociacao para o CRM.';
      setError(message);
    } finally {
      setSending('none');
    }
  }

  function handleSelectStudent(studentId: number) {
    setSelectedId(studentId);
  }

  function handleChangeStudent() {
    setNegotiationScope('total');
    setListCollapsed(false);
    setVisibleCount(RESULTS_PAGE_SIZE);
    window.setTimeout(() => {
      searchSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 80);
  }

  return (
    <div className="field-grid">
      <section ref={searchSectionRef} className="surface-card">
        <div className="module-toolbar">
          <div>
            <p className="eyebrow">Base financeira</p>
            <h3>Buscar aluno para negociar</h3>
            <p className="muted">Consulta em tempo real com base nos recursos `students` e `invoices`.</p>
            {selected ? (
              <p className="success-text">
                Aluno selecionado: {selected.name}. {listCollapsed ? 'Toque em "Trocar aluno" para voltar a lista.' : ''}
              </p>
            ) : null}
            {error ? <p className="alert-text">{error}</p> : null}
          </div>

          <button
            type="button"
            className="primary-button"
            onClick={() => void refreshProfiles('manual')}
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
              placeholder="Nome ou documento"
              style={{ marginTop: 16 }}
            />

            {selected && listCollapsed ? (
              <div className="selection-summary-card" style={{ marginTop: 16 }}>
                <div>
                  <strong>{selected.name}</strong>
                  <p className="muted">Documento: {selected.document}</p>
                  <p className="muted">
                    Aberto: {formatCurrency(selected.openAmount)} | Vencido: {formatCurrency(selected.overdueAmount)}
                  </p>
                </div>
                <button type="button" className="secondary-button" onClick={handleChangeStudent}>
                  Trocar aluno
                </button>
              </div>
            ) : null}

            {!listCollapsed && loading && filtered.length === 0 ? (
              <div className="skeleton-grid" style={{ marginTop: 16 }}>
                <div className="skeleton-card" />
                <div className="skeleton-card" />
                <div className="skeleton-card" />
              </div>
            ) : null}

            {!listCollapsed ? (
              <div className="list-stack" style={{ marginTop: 16 }}>
                <div className="list-results-meta">
                  <span className="muted">
                    {filtered.length === 0
                      ? 'Nenhum aluno encontrado'
                      : `Mostrando ${visibleProfiles.length} de ${filtered.length} alunos`}
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

                {visibleProfiles.map((student) => {
                  const isSelected = selectedId === student.id;
                  return (
                    <button
                      key={student.id}
                      type="button"
                      className={`list-row${isSelected ? ' is-selected' : ''}`}
                      onClick={() => handleSelectStudent(student.id)}
                    >
                      <div className="list-row-header">
                        <strong>{student.name}</strong>
                        <span className="pill pill-warning">{student.document}</span>
                      </div>
                      <p className="muted">Aberto: {formatCurrency(student.openAmount)}</p>
                      <p className="muted">Vencido: {formatCurrency(student.overdueAmount)}</p>
                    </button>
                  );
                })}
                {!loading && filtered.length === 0 ? (
                  <div className="empty-state">
                    <h4>Nenhum aluno encontrado</h4>
                    <p className="muted">Tente buscar por outro nome ou documento para iniciar a negociacao.</p>
                  </div>
                ) : null}
                {!loading && filtered.length > 0 && hasMoreResults ? (
                  <button
                    type="button"
                    className="secondary-button list-load-more"
                    onClick={() => setVisibleCount((current) => current + RESULTS_PAGE_SIZE)}
                  >
                    Carregar mais {Math.min(RESULTS_PAGE_SIZE, filtered.length - visibleProfiles.length)} alunos
                  </button>
                ) : null}
              </div>
            ) : null}
          </>
        ) : (
          <div className="status-card" style={{ marginTop: 16 }}>
            <p className="muted">Entre novamente no APP para renovar a sessao e carregar os dados de negociacao.</p>
          </div>
        )}
      </section>

      {connected && selected ? (
        <section ref={simulatorSectionRef} className="surface-card">
          <p className="eyebrow">Simulador</p>
          <h3>Negociacao de {selected.name}</h3>
          <p className="muted">
            Ultimo pagamento: {formatDateIso(selected.lastPaymentDate)} | Titulos em aberto: {selected.invoicesOpen}
          </p>
          <p className="muted">
            Saldo aberto: {formatCurrency(selected.openAmount)} | Saldo vencido: {formatCurrency(selected.overdueAmount)}
          </p>

          <div className="list-stack" style={{ marginTop: 16 }}>
            <div className="field-wrap">
              <label>Escopo da renegociacao</label>
              <div className="install-actions compact-actions">
                <button
                  type="button"
                  className={negotiationScope === 'total' ? 'primary-button' : 'secondary-button'}
                  onClick={() => setNegotiationScope('total')}
                >
                  Saldo total
                </button>
                <button
                  type="button"
                  className={negotiationScope === 'overdue' ? 'primary-button' : 'secondary-button'}
                  onClick={() => setNegotiationScope('overdue')}
                  disabled={selected.overdueInvoices.length === 0}
                >
                  Parcelas vencidas
                </button>
              </div>
            </div>

            {negotiationScope === 'total' ? (
              <div className="result-card">
                <h4>Renegociando o saldo completo do aluno</h4>
                <p className="muted">
                  Base atual: {formatCurrency(totalDebt)} considerando valores abertos e vencidos.
                </p>
              </div>
            ) : (
              <div className="detail-card">
                <h4>Parcelas vencidas para renegociar</h4>
                <p className="muted">
                  {selected.overdueInvoices.length} fatura(s) vencida(s) somando {formatCurrency(totalDebt)}.
                </p>
                <div className="list-stack" style={{ marginTop: 12 }}>
                  {selected.overdueInvoices.map((invoice) => (
                    <div key={invoice.id} className="list-row">
                      <div className="list-row-header">
                        <strong>{invoice.number}</strong>
                        <span className="pill pill-danger">{formatCurrency(invoice.outstandingAmount)}</span>
                      </div>
                      <p className="muted">
                        Vencimento: {formatDateIso(invoice.dueDate)} | Valor: {formatCurrency(invoice.amount)}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {selected.overdueInvoices.length === 0 ? (
              <p className="muted">Este aluno nao possui parcelas vencidas no momento.</p>
            ) : null}
          </div>

          <div className="field-grid three-columns" style={{ marginTop: 16 }}>
            <div className="field-wrap">
              <label htmlFor="discount">Desconto (%)</label>
              <input
                id="discount"
                className="text-input"
                type="number"
                inputMode="decimal"
                value={discountPercent}
                onChange={(event) => setDiscountPercent(event.target.value)}
              />
            </div>

            <div className="field-wrap">
              <label htmlFor="installments">Parcelas</label>
              <input
                id="installments"
                className="text-input"
                type="number"
                inputMode="numeric"
                value={installments}
                onChange={(event) => setInstallments(event.target.value)}
              />
            </div>

            <div className="field-wrap">
              <label htmlFor="dueDate">Primeiro vencimento</label>
              <input
                id="dueDate"
                className="text-input"
                type="date"
                value={firstDueDate}
                onChange={(event) => setFirstDueDate(event.target.value)}
              />
            </div>
          </div>

          <div className="result-card" style={{ marginTop: 16 }}>
            <h4>Total com desconto: {formatCurrency(simulatedDeal.withDiscount)}</h4>
            <p className="muted">Parcela estimada: {formatCurrency(simulatedDeal.installmentValue)}</p>
            <p className="muted">
              Base da simulacao: {negotiationScope === 'overdue' ? 'parcelas vencidas' : 'saldo total do aluno'}.
            </p>
          </div>

          <div className="install-actions action-cluster">
            <button
              type="button"
              className="primary-button"
              onClick={() => void handleSend('aditivo')}
              disabled={sending !== 'none'}
            >
              {sending === 'aditivo' ? 'Enviando aditivo...' : 'Gerar aditivo'}
            </button>

            <button
              type="button"
              className="secondary-button emphasis-button"
              onClick={() => void handleSend('negociacao')}
              disabled={sending !== 'none'}
            >
              {sending === 'negociacao' ? 'Enviando negociacao...' : 'Enviar negociacao'}
            </button>
          </div>

          {lastAction ? <p className="success-text">{lastAction}</p> : null}
        </section>
      ) : null}
    </div>
  );
}
