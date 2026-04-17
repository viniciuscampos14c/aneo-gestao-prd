import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { executiveMetrics } from '../data/mock';
import { MetricCard } from '../components/MetricCard';

export function DashboardScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.sectionTitle}>Visao Financeira</Text>

      <View style={styles.metricsGrid}>
        {executiveMetrics.map((metric) => (
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
