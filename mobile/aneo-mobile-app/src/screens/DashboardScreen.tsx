import { useEffect, useMemo, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { executiveMetrics } from '../data/mock';
import { MetricCard } from '../components/MetricCard';
import { loadExecutiveMetricsFromApi } from '../services/dashboardService';
import { formatDateTime } from '../utils/format';
import type { ApiConfig, ExecutiveMetric } from '../types';

type DashboardScreenProps = {
  apiConfig: ApiConfig | null;
};

export function DashboardScreen({ apiConfig }: DashboardScreenProps) {
  const [metrics, setMetrics] = useState<ExecutiveMetric[]>(executiveMetrics);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [lastSync, setLastSync] = useState<Date | null>(null);
  const source = useMemo<'mock' | 'live'>(() => (apiConfig ? 'live' : 'mock'), [apiConfig]);

  useEffect(() => {
    let active = true;

    async function loadRealMetrics(config: ApiConfig) {
      setLoading(true);
      setError('');
      try {
        const rows = await loadExecutiveMetricsFromApi(config);
        if (!active) return;
        setMetrics(rows);
        setLastSync(new Date());
      } catch (err) {
        if (!active) return;
        setMetrics(executiveMetrics);
        setError(err instanceof Error ? err.message : 'Falha ao carregar dados reais.');
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    if (!apiConfig) {
      setMetrics(executiveMetrics);
      setLoading(false);
      setError('');
      return () => {
        active = false;
      };
    }

    loadRealMetrics(apiConfig);
    return () => {
      active = false;
    };
  }, [apiConfig]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.statusCard}>
        <Text style={styles.statusLabel}>Fonte de dados</Text>
        <Text style={styles.statusValue}>
          {source === 'live' ? 'API em tempo real' : 'Mock local'}
        </Text>
        {loading ? <Text style={styles.statusHint}>Atualizando indicadores...</Text> : null}
        {!loading && source === 'live' && lastSync ? (
          <Text style={styles.statusHint}>Ultima sincronizacao: {formatDateTime(lastSync)}</Text>
        ) : null}
        {error ? <Text style={styles.errorText}>Falha API: {error}</Text> : null}
      </View>

      <Text style={styles.sectionTitle}>Visao Financeira</Text>

      <View style={styles.metricsGrid}>
        {metrics.map((metric) => (
          <MetricCard key={metric.id} metric={metric} />
        ))}
      </View>

      <Text style={styles.sectionTitle}>Acoes Prioritarias</Text>

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
    gap: 4,
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
