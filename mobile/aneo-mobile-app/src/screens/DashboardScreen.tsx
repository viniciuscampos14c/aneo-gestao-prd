import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { MetricCard } from '../components/MetricCard';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadExecutiveMetricsFromApi } from '../services/dashboardService';
import { formatDateTime } from '../utils/format';
import type { ApiConfig, ExecutiveMetric } from '../types';

type DashboardTab =
  | 'dashboard'
  | 'negotiation'
  | 'trial-access'
  | 'connection'
  | 'students'
  | 'tickets';

type DashboardScreenProps = {
  apiConfig: ApiConfig | null;
  onNavigateTab?: (tab: DashboardTab) => void;
};

const quickModules: Array<{ id: string; icon: string; label: string; targetTab: DashboardTab }> = [
  { id: 'indicadores', icon: 'I', label: 'Indicadores', targetTab: 'dashboard' },
  { id: 'negociacao', icon: '%', label: 'Negociacao', targetTab: 'negotiation' },
  { id: 'degustacao', icon: 'D', label: 'Degustacao', targetTab: 'trial-access' },
  { id: 'conexao', icon: 'C', label: 'Conexao', targetTab: 'connection' },
  { id: 'alunos', icon: 'A', label: 'Alunos', targetTab: 'students' },
  { id: 'chamados', icon: 'T', label: 'Chamados', targetTab: 'tickets' },
];

const highlights: Array<{ id: string; title: string; description: string; targetTab: DashboardTab; icon: string }> = [
  {
    id: 'hl-negociacao',
    title: 'Negociacoes pendentes',
    description: 'Atalho para enviar acordos e aditivos.',
    targetTab: 'negotiation',
    icon: '%',
  },
  {
    id: 'hl-alunos',
    title: 'Base de Alunos',
    description: 'Consulta rapida de alunos e contatos.',
    targetTab: 'students',
    icon: 'A',
  },
  {
    id: 'hl-chamados',
    title: 'Fila de Chamados',
    description: 'Acompanhe solicitacoes abertas da operacao.',
    targetTab: 'tickets',
    icon: 'T',
  },
];

export function DashboardScreen({ apiConfig, onNavigateTab }: DashboardScreenProps) {
  const [metrics, setMetrics] = useState<ExecutiveMetric[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [lastSync, setLastSync] = useState<Date | null>(null);
  const [query, setQuery] = useState('');
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

    const intervalId = setInterval(() => {
      void refreshData('auto');
    }, AUTO_REFRESH_MS);

    return () => clearInterval(intervalId);
  }, [apiConfig, refreshData]);

  const filteredModules = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) {
      return quickModules;
    }

    return quickModules.filter((module) => module.label.toLowerCase().includes(term));
  }, [query]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.searchCard}>
        <Text style={styles.searchIcon}>Q</Text>
        <TextInput
          style={styles.searchInput}
          value={query}
          onChangeText={setQuery}
          placeholder="Pesquisar modulo..."
          placeholderTextColor="#7f9ab9"
        />
      </View>

      <Text style={styles.sectionTitle}>Modulos rapidos</Text>
      <View style={styles.moduleGrid}>
        {filteredModules.map((module) => (
          <Pressable key={module.id} style={styles.moduleButton} onPress={() => onNavigateTab?.(module.targetTab)}>
            <View style={styles.moduleIconWrap}>
              <Text style={styles.moduleIcon}>{module.icon}</Text>
            </View>
            <Text style={styles.moduleLabel}>{module.label}</Text>
          </Pressable>
        ))}
      </View>

      <Text style={styles.sectionTitle}>Destaques de uso</Text>
      <View style={styles.featureList}>
        {highlights.map((item) => (
          <Pressable key={item.id} style={styles.featureCard} onPress={() => onNavigateTab?.(item.targetTab)}>
            <View style={styles.featureIconWrap}>
              <Text style={styles.featureIcon}>{item.icon}</Text>
            </View>
            <View style={styles.featureTextWrap}>
              <Text style={styles.featureTitle}>{item.title}</Text>
              <Text style={styles.featureDescription}>{item.description}</Text>
            </View>
          </Pressable>
        ))}
      </View>

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
          onPress={() => void refreshData('manual')}
          disabled={!connected || loading}
        >
          <Text style={styles.refreshButtonText}>{loading ? 'Atualizando...' : 'Atualizar agora'}</Text>
        </Pressable>
      </View>

      {connected ? (
        <View style={styles.metricsGrid}>
          {metrics.map((metric) => (
            <MetricCard key={metric.id} metric={metric} />
          ))}
        </View>
      ) : (
        <View style={styles.emptyCard}>
          <Text style={styles.emptyTitle}>Conecte a API para ver os indicadores</Text>
          <Text style={styles.emptyText}>Abra a aba Conexao, valide o acesso e volte para atualizar.</Text>
        </View>
      )}
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
  searchCard: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#325781',
    backgroundColor: '#173052',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    gap: 8,
  },
  searchIcon: {
    color: '#8fb9ea',
    fontSize: 16,
    fontWeight: '700',
  },
  searchInput: {
    flex: 1,
    color: '#e8f2ff',
    paddingVertical: 12,
    fontSize: 14,
  },
  sectionTitle: {
    color: '#dcecff',
    fontSize: 16,
    fontWeight: '700',
  },
  moduleGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  moduleButton: {
    width: '31%',
    minWidth: 94,
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#2b4f77',
    backgroundColor: '#10263f',
    paddingVertical: 10,
    paddingHorizontal: 8,
    gap: 6,
  },
  moduleIconWrap: {
    width: 48,
    height: 48,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: '#3f79b3',
    backgroundColor: '#1f3f65',
    alignItems: 'center',
    justifyContent: 'center',
  },
  moduleIcon: {
    color: '#cbe3ff',
    fontSize: 18,
    fontWeight: '800',
  },
  moduleLabel: {
    color: '#d5e8ff',
    fontSize: 12,
    fontWeight: '600',
    textAlign: 'center',
  },
  featureList: {
    gap: 10,
  },
  featureCard: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#294f76',
    backgroundColor: '#0f243c',
    padding: 10,
    gap: 10,
    flexDirection: 'row',
    alignItems: 'center',
  },
  featureIconWrap: {
    width: 56,
    height: 56,
    borderRadius: 28,
    borderWidth: 1,
    borderColor: '#3b6a9a',
    backgroundColor: '#1a3453',
    alignItems: 'center',
    justifyContent: 'center',
  },
  featureIcon: {
    color: '#d6e9ff',
    fontSize: 21,
    fontWeight: '800',
  },
  featureTextWrap: {
    flex: 1,
    gap: 2,
  },
  featureTitle: {
    color: '#f3f8ff',
    fontSize: 15,
    fontWeight: '700',
  },
  featureDescription: {
    color: '#9fc1eb',
    fontSize: 12,
    lineHeight: 17,
  },
  statusCard: {
    borderWidth: 1,
    borderColor: '#224567',
    borderRadius: 12,
    backgroundColor: '#0f2239',
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
  metricsGrid: {
    gap: 10,
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
});
