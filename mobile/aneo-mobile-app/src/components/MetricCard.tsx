import { StyleSheet, Text, View } from 'react-native';
import type { ExecutiveMetric, MetricTone } from '../types';

type Props = {
  metric: ExecutiveMetric;
};

const toneMap: Record<MetricTone, { border: string; chipBg: string; chipText: string }> = {
  positive: { border: '#1d7f45', chipBg: '#133b24', chipText: '#7ee7a6' },
  warning: { border: '#9b6b12', chipBg: '#3a2b12', chipText: '#f6cf78' },
  critical: { border: '#9f2a2f', chipBg: '#3e1618', chipText: '#ff9da2' },
  neutral: { border: '#2f4c6f', chipBg: '#12273f', chipText: '#93b7e6' },
};

export function MetricCard({ metric }: Props) {
  const tone = toneMap[metric.tone];

  return (
    <View style={[styles.card, { borderColor: tone.border }]}>
      <Text style={styles.title}>{metric.title}</Text>
      <Text style={styles.value}>{metric.value}</Text>

      <View style={[styles.chip, { backgroundColor: tone.chipBg }]}>
        <Text style={[styles.chipText, { color: tone.chipText }]}>{metric.trend}</Text>
      </View>

      <Text style={styles.description}>{metric.description}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    backgroundColor: '#10263f',
    borderRadius: 14,
    padding: 14,
    width: '100%',
    gap: 6,
  },
  title: {
    color: '#d0e2ff',
    fontSize: 12,
    fontWeight: '600',
  },
  value: {
    color: '#ffffff',
    fontSize: 24,
    fontWeight: '700',
  },
  chip: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingVertical: 5,
    paddingHorizontal: 10,
    marginTop: 2,
  },
  chipText: {
    fontSize: 11,
    fontWeight: '700',
  },
  description: {
    color: '#8fb0d9',
    fontSize: 12,
    lineHeight: 18,
    marginTop: 2,
  },
});
