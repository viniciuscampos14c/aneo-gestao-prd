import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadExecutiveMetricsFromApi } from '../services/dashboardService';
import { formatDateTime } from '../utils/format';
import type { ApiConfig, ExecutiveMetric } from '../types';
import { MetricCard } from './MetricCard';

type DashboardViewProps = {
  apiConfig: ApiConfig | null;
};

export function DashboardView({ apiConfig }: DashboardViewProps) {
  const [metrics, setMetrics] = useState<ExecutiveMetric[]>([]);
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
        const rows = await loadExecutiveMetricsFromApi(apiConfig);
        setMetrics(rows);
        setLastSync(new Date());
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Falha ao carregar indicadores.';
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
      setMetrics([]);
      setLoading(false);
      setError('');
      setLastSync(null);
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

  return (
    <>
      <section className="surface-card">
        <div className="inline-between">
          <div>
            <p className="eyebrow">Indicadores</p>
            <h3>Resumo em tempo real</h3>
            <p className="muted">
              {connected ? 'API conectada com atualizacao automatica a cada 5 minutos.' : 'Conecte a API para liberar os indicadores.'}
            </p>
            {!loading && connected && lastSync ? (
              <p className="muted">Ultima sincronizacao: {formatDateTime(lastSync)}</p>
            ) : null}
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
          <div className="card-grid" style={{ marginTop: 16 }}>
            {metrics.map((metric) => (
              <MetricCard key={metric.id} metric={metric} />
            ))}
          </div>
        ) : (
          <div className="status-card" style={{ marginTop: 16 }}>
            <h3>Sem conexao</h3>
            <p className="muted">Abra o modulo Conexao, valide o acesso e volte para atualizar.</p>
          </div>
        )}
      </section>
    </>
  );
}
