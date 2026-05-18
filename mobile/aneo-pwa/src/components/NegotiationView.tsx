import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import { sendNegotiationToCrm } from '../services/crmWriteService';
import { loadDebtProfilesFromApi } from '../services/negotiationService';
import type { ApiConfig, StudentDebtProfile } from '../types';
import { formatCurrency, formatDateIso } from '../utils/format';

type NegotiationViewProps = {
  apiConfig: ApiConfig | null;
};

export function NegotiationView({ apiConfig }: NegotiationViewProps) {
  const [query, setQuery] = useState('');
  const [profiles, setProfiles] = useState<StudentDebtProfile[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [discountPercent, setDiscountPercent] = useState('8');
  const [installments, setInstallments] = useState('3');
  const [firstDueDate, setFirstDueDate] = useState('2026-05-10');
  const [lastAction, setLastAction] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [sending, setSending] = useState<'none' | 'aditivo' | 'negociacao'>('none');
  const loadingRef = useRef(false);

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

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return profiles;

    return profiles.filter((student) => {
      return (
        student.name.toLowerCase().includes(term) || student.document.toLowerCase().includes(term)
      );
    });
  }, [query, profiles]);

  const totalDebt = selected ? selected.openAmount + selected.overdueAmount : 0;

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
        profile: selected,
        discountPercent: Number(discountPercent) || 0,
        installments: Math.max(1, Number(installments) || 1),
        firstDueDate,
        totalDebt,
        discountedTotal: simulatedDeal.withDiscount,
        installmentValue: simulatedDeal.installmentValue,
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

  return (
    <div className="field-grid">
      <section className="surface-card">
        <div className="inline-between">
          <div>
            <p className="eyebrow">Base financeira</p>
            <h3>Buscar aluno para negociar</h3>
            <p className="muted">Consulta em tempo real com base nos recursos `students` e `invoices`.</p>
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
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Nome ou documento"
              style={{ marginTop: 16 }}
            />

            <div className="list-stack" style={{ marginTop: 16 }}>
              {filtered.map((student) => {
                const isSelected = selectedId === student.id;
                return (
                  <button
                    key={student.id}
                    type="button"
                    className={`list-row${isSelected ? ' is-selected' : ''}`}
                    onClick={() => setSelectedId(student.id)}
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
            </div>
          </>
        ) : (
          <div className="status-card" style={{ marginTop: 16 }}>
            <p className="muted">Conecte a API na aba Conexao para carregar alunos e enviar negociacoes.</p>
          </div>
        )}
      </section>

      {connected && selected ? (
        <section className="surface-card">
          <p className="eyebrow">Simulador</p>
          <h3>Negociacao de {selected.name}</h3>
          <p className="muted">
            Ultimo pagamento: {formatDateIso(selected.lastPaymentDate)} | Titulos em aberto: {selected.invoicesOpen}
          </p>
          <p className="muted">Divida total: {formatCurrency(totalDebt)}</p>

          <div className="field-grid three-columns" style={{ marginTop: 16 }}>
            <div className="field-wrap">
              <label htmlFor="discount">Desconto (%)</label>
              <input
                id="discount"
                className="text-input"
                value={discountPercent}
                onChange={(event) => setDiscountPercent(event.target.value)}
              />
            </div>

            <div className="field-wrap">
              <label htmlFor="installments">Parcelas</label>
              <input
                id="installments"
                className="text-input"
                value={installments}
                onChange={(event) => setInstallments(event.target.value)}
              />
            </div>

            <div className="field-wrap">
              <label htmlFor="dueDate">Primeiro vencimento</label>
              <input
                id="dueDate"
                className="text-input"
                value={firstDueDate}
                onChange={(event) => setFirstDueDate(event.target.value)}
              />
            </div>
          </div>

          <div className="result-card" style={{ marginTop: 16 }}>
            <h4>Total com desconto: {formatCurrency(simulatedDeal.withDiscount)}</h4>
            <p className="muted">Parcela estimada: {formatCurrency(simulatedDeal.installmentValue)}</p>
          </div>

          <div className="install-actions">
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
              className="secondary-button"
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
