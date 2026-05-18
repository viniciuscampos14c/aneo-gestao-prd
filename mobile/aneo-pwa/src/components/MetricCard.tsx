import type { ExecutiveMetric } from '../types';

type MetricCardProps = {
  metric: ExecutiveMetric;
};

export function MetricCard({ metric }: MetricCardProps) {
  return (
    <article className={`metric-card metric-${metric.tone}`}>
      <p className="eyebrow">{metric.title}</p>
      <div className="metric-value">{metric.value}</div>
      <p className="metric-trend">{metric.trend}</p>
      <p className="muted">{metric.description}</p>
    </article>
  );
}
