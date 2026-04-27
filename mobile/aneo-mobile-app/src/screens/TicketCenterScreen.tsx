import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadTicketsFromApi } from '../services/ticketCenterService';
import type { ApiConfig, ApiTicket } from '../types';

type TicketCenterScreenProps = {
  apiConfig: ApiConfig | null;
};

function statusLabel(status: string | null | undefined): string {
  const normalized = String(status ?? '').trim().toLowerCase();
  if (normalized === 'open') return 'Aberto';
  if (normalized === 'in_progress') return 'Em andamento';
  if (normalized === 'resolved') return 'Resolvido';
  if (normalized === 'closed') return 'Fechado';
  return normalized === '' ? '-' : normalized;
}

function priorityLabel(priority: string | null | undefined): string {
  const normalized = String(priority ?? '').trim().toLowerCase();
  if (normalized === 'low') return 'Baixa';
  if (normalized === 'medium') return 'Media';
  if (normalized === 'high') return 'Alta';
  if (normalized === 'urgent') return 'Urgente';
  return normalized === '' ? '-' : normalized;
}

export function TicketCenterScreen({ apiConfig }: TicketCenterScreenProps) {
  const [rows, setRows] = useState<ApiTicket[]>([]);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
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
        const tickets = await loadTicketsFromApi(apiConfig);
        setRows(tickets);
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Falha ao carregar chamados.';
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
      setRows([]);
      setLoading(false);
      setError('');
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

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return rows;

    return rows.filter((ticket) => {
      const code = String(ticket.ticket_code ?? '').toLowerCase();
      const subject = String(ticket.subject ?? '').toLowerCase();
      const requester = String(ticket.requester_name ?? '').toLowerCase();
      return code.includes(term) || subject.includes(term) || requester.includes(term);
    });
  }, [rows, query]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.statusCard}>
        <Text style={styles.statusLabel}>Fonte de dados</Text>
        <Text style={styles.statusValue}>{connected ? 'API em tempo real' : 'Desconectado'}</Text>
        {error ? <Text style={styles.errorText}>Falha: {error}</Text> : null}

        <Pressable
          style={[styles.refreshButton, loading && styles.refreshButtonDisabled]}
          onPress={() => void refreshData('manual')}
          disabled={!connected || loading}
        >
          <Text style={styles.refreshButtonText}>{loading ? 'Atualizando...' : 'Atualizar agora'}</Text>
        </Pressable>
      </View>

      {!connected ? (
        <View style={styles.emptyCard}>
          <Text style={styles.emptyTitle}>Conecte a API para listar chamados</Text>
        </View>
      ) : null}

      {connected ? (
        <TextInput
          style={styles.searchInput}
          value={query}
          onChangeText={setQuery}
          placeholder="Buscar por codigo, assunto ou solicitante..."
          placeholderTextColor="#7f9ab9"
        />
      ) : null}

      {connected ? (
        <View style={styles.listWrap}>
          {filtered.map((ticket) => (
            <View key={ticket.id} style={styles.rowCard}>
              <View style={styles.rowHeader}>
                <Text style={styles.rowCode}>{String(ticket.ticket_code ?? `ID ${ticket.id}`)}</Text>
                <Text style={styles.rowStatus}>{statusLabel(ticket.status)}</Text>
              </View>
              <Text style={styles.rowTitle}>{String(ticket.subject ?? 'Sem assunto')}</Text>
              <Text style={styles.rowMeta}>Solicitante: {String(ticket.requester_name ?? '-')}</Text>
              <Text style={styles.rowMeta}>Prioridade: {priorityLabel(ticket.priority)}</Text>
              <Text style={styles.rowMeta}>Origem: {String(ticket.source ?? '-')}</Text>
            </View>
          ))}
          {filtered.length === 0 ? <Text style={styles.emptyText}>Nenhum chamado encontrado.</Text> : null}
        </View>
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
  searchInput: {
    borderWidth: 1,
    borderColor: '#2a4769',
    borderRadius: 10,
    backgroundColor: '#0f223a',
    color: '#ffffff',
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
  },
  listWrap: {
    gap: 8,
  },
  rowCard: {
    borderWidth: 1,
    borderColor: '#244261',
    borderRadius: 10,
    backgroundColor: '#0f233b',
    padding: 12,
    gap: 4,
  },
  rowHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 8,
    alignItems: 'center',
  },
  rowCode: {
    color: '#d5e9ff',
    fontSize: 12,
    fontWeight: '700',
  },
  rowStatus: {
    color: '#7ce3a5',
    fontSize: 12,
    fontWeight: '700',
  },
  rowTitle: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
  },
  rowMeta: {
    color: '#a9c7ec',
    fontSize: 12,
  },
  emptyCard: {
    borderWidth: 1,
    borderColor: '#2f4c6f',
    borderRadius: 12,
    backgroundColor: '#10263f',
    padding: 14,
  },
  emptyTitle: {
    color: '#f2f7ff',
    fontSize: 16,
    fontWeight: '700',
  },
  emptyText: {
    color: '#b0ccec',
    fontSize: 13,
  },
});
