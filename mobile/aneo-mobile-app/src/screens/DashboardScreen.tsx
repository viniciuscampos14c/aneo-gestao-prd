import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MetricCard } from '../components/MetricCard';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadExecutiveMetricsFromApi } from '../services/dashboardService';
import { formatDateTime } from '../utils/format';
import type { ApiConfig, ExecutiveMetric } from '../types';

type DashboardScreenProps = {
  apiConfig: ApiConfig | null;
};

export function DashboardScreen({ apiConfig }: DashboardScreenProps) {
  const [metrics, setMetrics] = useState<ExecutiveMetric[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [lastSync, setLastSync] = useState<Date | null>(null);
  const loadingRef = useRef(false);

  const connected = useMemo(() => !!apiConfig?.token, [apiConfig]);

  const refreshData = useCallback(
    async (mode: 'manual' | 'auto' | 'initial') => {
      if (!apiConfig) {
        return;
      }
      if (loadingRef.current) {
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

    refreshData('initial');
  }, [apiConfig, refreshData]);

  useEffect(() => {
    if (!apiConfig) {
      return;
    }

    refreshData('auto');
    const intervalId = setInterval(() => {
      refreshData('auto');
    }, AUTO_REFRESH_MS);

    return () => clearInterval(intervalId);
  }, [apiConfig, refreshData]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.statusCard}>
        <Text style={styles.statusLabel}>Fonte de dados</Text>
        <Text style={styles.statusValue}>{connected ? 'API em tempo real' : 'Desconectado'}</Text>
        <Text style={styles.statusHint}>Atualizacao automatica a cada 5 minutos.</Text>
        {!loading && connected && lastSync ? (
          <Text style={styles.statusHint}>Ultima sincronizacao: {formatDateTime(lastSync)}</Text>
        ) : null}
        {error ? <Text style={styles.errorText}>Falha: {error}</Text> : null}

        <Pressable
          style={[styles.refreshButton, loading && styles.refreshButtonDisabled]}
          onPress={() => refreshData('manual')}
          disabled={!connected || loading}
        >
          <Text style={styles.refreshButtonText}>{loading ? 'Atualizando...' : 'Atualizar agora'}</Text>
        </Pressable>
      </View>

      {!connected ? (
        <View style={styles.emptyCard}>
          <Text style={styles.emptyTitle}>Conecte a API para ver indicadores reais</Text>
          <Text style={styles.emptyText}>
            Abra a aba Conexao, informe URL e token, e volte para atualizar o dashboard.
          </Text>
        </View>
      ) : null}

      {connected ? <Text style={styles.sectionTitle}>Visao Financeira</Text> : null}

      {connected ? (
        <View style={styles.metricsGrid}>
          {metrics.map((metric) => (
            <MetricCard key={metric.id} metric={metric} />
          ))}
        </View>
      ) : null}

      {connected ? <Text style={styles.sectionTitle}>Acoes Prioritarias</Text> : null}

      {connected ? (
        <>
          <View style={styles.actionCard}>
            <Text style={styles.actionTitle}>1) Carteira 60+ dias</Text>
            <Text style={styles.actionText}>
              Focar nos 20 maiores contratos vencidos para reverter caixa em ate 7 dias.
            </Text>
          </View>

          <View style={styles.actionCard}>
            <Text style={styles.actionTitle}>2) Alunos com risco de evasao</Text>
            <Text style={styles.actionText}>
              Cruzar inadimplencia + chamados + ausencia para antecipar retencao comercial.
            </Text>
          </View>

          <View style={styles.actionCard}>
            <Text style={styles.actionTitle}>3) Meta semanal de negociacoes</Text>
            <Text style={styles.actionText}>
              Executar 40 acordos com ticket medio de R$ 1.200 para aumentar recuperacao.
            </Text>
          </View>
        </>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#081628',
  },
  content: {
    padding: 16,
    gap: 12,
    paddingBottom: 28,
  },
  statusCard: {
    backgroundColor: '#0f2239',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#224567',
    padding: 12,
    gap: 6,
  },
  statusLabel: {
    color: '#9fc1eb',
    fontSize: 12,
    fontWeight: '600',
  },
  statusValue: {
    color: '#e9f2ff',
    fontSize: 16,
    fontWeight: '700',
  },
  statusHint: {
    color: '#9fc1eb',
    fontSize: 12,
  },
  errorText: {
    color: '#ff9da2',
    fontSize: 12,
  },
  refreshButton: {
    marginTop: 4,
    borderWidth: 1,
    borderColor: '#2c5f94',
    borderRadius: 10,
    backgroundColor: '#123258',
    paddingVertical: 10,
    alignItems: 'center',
  },
  refreshButtonDisabled: {
    opacity: 0.6,
  },
  refreshButtonText: {
    color: '#d9ebff',
    fontSize: 13,
    fontWeight: '700',
  },
  emptyCard: {
    borderWidth: 1,
    borderColor: '#2f4c6f',
    borderRadius: 12,
    backgroundColor: '#10263f',
    padding: 14,
    gap: 4,
  },
  emptyTitle: {
    color: '#f2f7ff',
    fontSize: 16,
    fontWeight: '700',
  },
  emptyText: {
    color: '#b0ccec',
    fontSize: 13,
    lineHeight: 18,
  },
  sectionTitle: {
    color: '#e9f2ff',
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 2,
  },
  metricsGrid: {
    gap: 10,
  },
  actionCard: {
    backgroundColor: '#10263f',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#1f3a5a',
    padding: 13,
    gap: 4,
  },
  actionTitle: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
  },
  actionText: {
    color: '#9fc1eb',
    fontSize: 13,
    lineHeight: 19,
  },
});
