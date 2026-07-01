import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadExecutiveDashboardFromApi } from '../services/dashboardService';
import { formatDateTime } from '../utils/format';
import type { ApiConfig, ExecutiveDashboardData } from '../types';

type DashboardTab = 'negotiation' | 'trial-access' | 'students' | 'tickets' | 'connection';

type DashboardViewProps = {
  apiConfig: ApiConfig | null;
  onNavigateTab: (tab: DashboardTab) => void;
};

export function DashboardView({ apiConfig, onNavigateTab }: DashboardViewProps) {
  const [dashboard, setDashboard] = useState<ExecutiveDashboardData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [lastSync, setLastSync] = useState<Date | null>(null);
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
        const nextDashboard = await loadExecutiveDashboardFromApi(apiConfig);
        setDashboard(nextDashboard);
        setLastSync(new Date());
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Falha ao carregar indicadores.';
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
        setDashboard(null);
        setLoading(false);
        setError('');
        setLastSync(null);
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

  const toneClass = dashboard ? `summary-panel summary-${dashboard.summary.tone}` : 'summary-panel';
  const priorityMetrics = useMemo(() => {
    if (!dashboard) {
      return [];
    }

    const priorityOrder = [
      'pending_total',
      'invoices_overdue',
      'students_total',
      'default_rate',
      'invoices_open',
      'invoices_paid',
    ];

    return [...dashboard.metrics]
      .sort((left, right) => priorityOrder.indexOf(left.id) - priorityOrder.indexOf(right.id))
      .slice(0, 3);
  }, [dashboard]);

  return (
    <>
      <section className={toneClass}>
        <div className="summary-copy">
          <p className="eyebrow">Visão do dia</p>
          <h2>{dashboard?.summary.headline ?? 'Painel executivo'}</h2>
          <p className="summary-message">
            {dashboard?.summary.message ?? 'Conecte a API para acompanhar a operação em tempo real.'}
          </p>
          {loading && connected ? <div className="loading-chip">Sincronizando painel...</div> : null}
          {!loading && connected && lastSync ? (
            <p className="muted">Última sincronização: {formatDateTime(lastSync)}</p>
          ) : null}
          {error ? <p className="alert-text">{error}</p> : null}
        </div>

        <div className="summary-stats">
          <article className="summary-stat-card">
            <span className="summary-stat-label">{dashboard?.summary.pendingLabel ?? 'Saldo pendente'}</span>
            <strong>{dashboard?.summary.pendingValue ?? '-'}</strong>
          </article>
          <article className="summary-stat-card">
            <span className="summary-stat-label">{dashboard?.summary.recoveryLabel ?? 'Recuperacao atual'}</span>
            <strong>{dashboard?.summary.recoveryValue ?? '-'}</strong>
          </article>
          <button
            type="button"
            className="primary-button summary-refresh"
            onClick={() => void refreshData('manual')}
            disabled={!connected || loading}
          >
            {loading ? 'Atualizando...' : 'Atualizar agora'}
          </button>
        </div>
      </section>

      <section className="surface-card">
        <div className="section-head">
          <div>
            <p className="eyebrow">Painel prioritario</p>
            <h3>O que decidir primeiro</h3>
            <p className="muted">
              Leitura curta para a diretoria entender pressão financeira, carteira ativa e próximos passos.
            </p>
          </div>
        </div>

        {loading && connected && !dashboard ? (
          <div className="skeleton-grid" style={{ marginTop: 16 }}>
            <div className="skeleton-card" />
            <div className="skeleton-card" />
            <div className="skeleton-card" />
          </div>
        ) : connected && dashboard ? (
          <div className="executive-priority-grid" style={{ marginTop: 16 }}>
            <div className="priority-metric-grid">
              {priorityMetrics.map((metric) => (
                <article key={metric.id} className={`priority-metric-card metric-${metric.tone}`}>
                  <span className="priority-metric-title">{metric.title}</span>
                  <strong>{metric.value}</strong>
                  <p className="metric-trend">{metric.trend}</p>
                  <p className="muted">{metric.description}</p>
                </article>
              ))}
            </div>

            <div className="priority-actions-card">
              <div className="section-head">
                <div>
                  <p className="eyebrow">Atencao do dia</p>
                  <h4>Acoes sugeridas</h4>
                </div>
              </div>
              <div className="priority-action-list">
                {dashboard.alerts.slice(0, 3).map((alert) => (
                  <article key={alert.id} className={`alert-card alert-${alert.tone}`}>
                    <div>
                      <div className="alert-meta">
                        <span className="pill pill-warning">{alert.category}</span>
                        <span className={`pill ${alert.priority === 'Alta' ? 'pill-danger' : alert.priority === 'Media' ? 'pill-warning' : 'pill-success'}`}>
                          {alert.priority}
                        </span>
                      </div>
                      <h4>{alert.title}</h4>
                      <p className="muted">{alert.detail}</p>
                    </div>
                    <button
                      type="button"
                      className="secondary-button"
                      onClick={() => onNavigateTab(alert.targetTab)}
                    >
                      {alert.actionLabel}
                    </button>
                  </article>
                ))}
              </div>
            </div>
          </div>
        ) : (
          <div className="status-card" style={{ marginTop: 16 }}>
            <h3>Sessão indisponível</h3>
            <p className="muted">Se os dados não carregarem, encerre a sessão e entre novamente para renovar o acesso.</p>
          </div>
        )}
      </section>

      <section className="surface-card">
        <div className="section-head">
          <div>
            <p className="eyebrow">Panorama geral</p>
            <h3>Resumo rápido da operação</h3>
            <p className="muted">Uma leitura unica da base, carteira, chamados e degustacoes.</p>
          </div>
        </div>

        {loading && connected && !dashboard ? (
          <div className="skeleton-grid" style={{ marginTop: 16 }}>
            <div className="skeleton-card" />
            <div className="skeleton-card" />
            <div className="skeleton-card" />
          </div>
        ) : connected && dashboard ? (
          <div className="snapshot-grid" style={{ marginTop: 16 }}>
            {dashboard.snapshots.map((snapshot) => (
              <article key={snapshot.id} className="snapshot-card">
                <span className="snapshot-label">{snapshot.label}</span>
                <strong>{snapshot.value}</strong>
                <p className="muted">{snapshot.helper}</p>
              </article>
            ))}
          </div>
        ) : (
          <div className="status-card" style={{ marginTop: 16 }}>
            <p className="muted">Conecte a API para liberar o panorama geral.</p>
          </div>
        )}
      </section>
    </>
  );
}
