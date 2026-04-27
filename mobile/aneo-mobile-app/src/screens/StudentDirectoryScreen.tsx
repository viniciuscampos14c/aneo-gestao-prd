import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadDebtProfilesFromApi } from '../services/negotiationService';
import { loadStudentsFromApi } from '../services/studentDirectoryService';
import type { ApiConfig, ApiStudent } from '../types';
import { formatCurrency, formatDateIso } from '../utils/format';

type StudentDirectoryScreenProps = {
  apiConfig: ApiConfig | null;
};

type FinancialSummary = {
  invoicesOpen: number;
  openAmount: number;
  overdueAmount: number;
  lastPaymentDate: string;
};

function isActive(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  return Number(value) === 1;
}

export function StudentDirectoryScreen({ apiConfig }: StudentDirectoryScreenProps) {
  const [rows, setRows] = useState<ApiStudent[]>([]);
  const [financialByStudentId, setFinancialByStudentId] = useState<Record<number, FinancialSummary>>({});
  const [expandedStudentId, setExpandedStudentId] = useState<number | null>(null);
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
        const [students, financialProfiles] = await Promise.all([
          loadStudentsFromApi(apiConfig),
          loadDebtProfilesFromApi(apiConfig),
        ]);
        setRows(students);

        const map: Record<number, FinancialSummary> = {};
        for (const profile of financialProfiles) {
          map[profile.id] = {
            invoicesOpen: profile.invoicesOpen,
            openAmount: profile.openAmount,
            overdueAmount: profile.overdueAmount,
            lastPaymentDate: profile.lastPaymentDate,
          };
        }
        setFinancialByStudentId(map);
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Falha ao carregar alunos.';
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
      setFinancialByStudentId({});
      setExpandedStudentId(null);
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

    return rows.filter((student) => {
      const name = String(student.full_name ?? '').toLowerCase();
      const email = String(student.email_primary ?? '').toLowerCase();
      const phone = String(student.phone ?? '').toLowerCase();
      return name.includes(term) || email.includes(term) || phone.includes(term);
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
          <Text style={styles.emptyTitle}>Conecte a API para listar alunos</Text>
        </View>
      ) : null}

      {connected ? (
        <TextInput
          style={styles.searchInput}
          value={query}
          onChangeText={setQuery}
          placeholder="Buscar aluno por nome, e-mail ou telefone..."
          placeholderTextColor="#7f9ab9"
        />
      ) : null}

      {connected ? (
        <View style={styles.listWrap}>
          {filtered.map((student) => {
            const active = isActive(student.is_active);
            const financial = financialByStudentId[student.id];
            const overdue = Number(financial?.overdueAmount ?? 0);
            const open = Number(financial?.openAmount ?? 0);
            const totalPending = overdue + open;
            const financialStatus = totalPending > 0 ? 'inadimplente' : 'adimplente';
            const isExpanded = expandedStudentId === student.id;

            return (
              <Pressable
                key={student.id}
                style={styles.rowCard}
                onPress={() => {
                  setExpandedStudentId((current) => (current === student.id ? null : student.id));
                }}
              >
                <View style={styles.rowHeader}>
                  <Text style={styles.rowName}>{student.full_name}</Text>
                  <Text style={[styles.statusPill, active ? styles.statusPillActive : styles.statusPillInactive]}>
                    {active ? 'Ativo' : 'Inativo'}
                  </Text>
                </View>

                <Text style={styles.rowMeta}>E-mail: {String(student.email_primary ?? '-')}</Text>
                <Text style={styles.rowMeta}>Telefone: {String(student.phone ?? '-')}</Text>
                <Text style={styles.rowMeta}>RA/RG: {String(student.ra ?? student.rg ?? '-')}</Text>
                <Text style={styles.rowHint}>Toque para ver situacao financeira</Text>

                {isExpanded ? (
                  <View style={styles.financialCard}>
                    <View style={styles.financialHeader}>
                      <Text style={styles.financialTitle}>Situacao Financeira</Text>
                      <Text
                        style={[
                          styles.financialPill,
                          financialStatus === 'adimplente'
                            ? styles.financialPillPositive
                            : styles.financialPillNegative,
                        ]}
                      >
                        {financialStatus === 'adimplente' ? 'Adimplente' : 'Inadimplente'}
                      </Text>
                    </View>

                    <Text style={styles.financialLine}>
                      Titulos em aberto: {Number(financial?.invoicesOpen ?? 0)}
                    </Text>
                    <Text style={styles.financialLine}>Saldo em aberto: {formatCurrency(open)}</Text>
                    <Text style={styles.financialLine}>Saldo vencido: {formatCurrency(overdue)}</Text>
                    <Text style={styles.financialLine}>Pendencia total: {formatCurrency(totalPending)}</Text>
                    <Text style={styles.financialLine}>
                      Ultimo pagamento: {formatDateIso(String(financial?.lastPaymentDate ?? ''))}
                    </Text>
                  </View>
                ) : null}
              </Pressable>
            );
          })}
          {filtered.length === 0 ? <Text style={styles.emptyText}>Nenhum aluno encontrado.</Text> : null}
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
  rowName: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
    flex: 1,
  },
  rowMeta: {
    color: '#a9c7ec',
    fontSize: 12,
  },
  rowHint: {
    color: '#82addd',
    fontSize: 11,
    marginTop: 3,
  },
  statusPill: {
    borderRadius: 999,
    paddingVertical: 3,
    paddingHorizontal: 9,
    fontSize: 11,
    fontWeight: '700',
    overflow: 'hidden',
  },
  statusPillActive: {
    backgroundColor: '#baf2d1',
    color: '#0f6c46',
  },
  statusPillInactive: {
    backgroundColor: '#ffd3d5',
    color: '#9b1d22',
  },
  financialCard: {
    marginTop: 8,
    borderWidth: 1,
    borderColor: '#2f547a',
    borderRadius: 10,
    backgroundColor: '#132b46',
    padding: 10,
    gap: 3,
  },
  financialHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  financialTitle: {
    color: '#f0f7ff',
    fontSize: 13,
    fontWeight: '700',
  },
  financialPill: {
    borderRadius: 999,
    paddingVertical: 3,
    paddingHorizontal: 9,
    fontSize: 11,
    fontWeight: '700',
    overflow: 'hidden',
  },
  financialPillPositive: {
    backgroundColor: '#baf2d1',
    color: '#0f6c46',
  },
  financialPillNegative: {
    backgroundColor: '#ffd3d5',
    color: '#9b1d22',
  },
  financialLine: {
    color: '#bfd8f6',
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
