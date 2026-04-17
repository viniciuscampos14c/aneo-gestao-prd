import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { AUTO_REFRESH_MS } from '../config/constants';
import { loadDebtProfilesFromApi } from '../services/negotiationService';
import { sendNegotiationToCrm } from '../services/crmWriteService';
import type { ApiConfig, StudentDebtProfile } from '../types';
import { formatCurrency, formatDateIso } from '../utils/format';

type NegotiationScreenProps = {
  apiConfig: ApiConfig | null;
};

export function NegotiationScreen({ apiConfig }: NegotiationScreenProps) {
  const [query, setQuery] = useState('');
  const [profiles, setProfiles] = useState<StudentDebtProfile[]>([]);
  const [selected, setSelected] = useState<StudentDebtProfile | null>(null);
  const [discountPercent, setDiscountPercent] = useState('8');
  const [installments, setInstallments] = useState('3');
  const [firstDueDate, setFirstDueDate] = useState('2026-05-10');
  const [lastAction, setLastAction] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [sending, setSending] = useState<'none' | 'aditivo' | 'negociacao'>('none');
  const loadingRef = useRef(false);

  const connected = useMemo(() => !!apiConfig?.token, [apiConfig]);

  const refreshProfiles = useCallback(
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
        const rows = await loadDebtProfilesFromApi(apiConfig);
        setProfiles(rows);

        if (selected) {
          const updatedSelection = rows.find((row) => row.id === selected.id) ?? null;
          setSelected(updatedSelection);
        }
      } catch (err) {
        const message =
          err instanceof Error ? err.message : 'Falha ao carregar negociacoes em tempo real.';
        setError(mode === 'manual' ? `Atualizacao manual falhou: ${message}` : message);
      } finally {
        loadingRef.current = false;
        setLoading(false);
      }
    },
    [apiConfig, selected]
  );

  useEffect(() => {
    if (!apiConfig) {
      setProfiles([]);
      setSelected(null);
      setLoading(false);
      setError('');
      setLastAction('');
      return;
    }

    refreshProfiles('initial');
  }, [apiConfig, refreshProfiles]);

  useEffect(() => {
    if (!apiConfig) {
      return;
    }

    const intervalId = setInterval(() => {
      refreshProfiles('auto');
    }, AUTO_REFRESH_MS);

    return () => clearInterval(intervalId);
  }, [apiConfig, refreshProfiles]);

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return profiles;

    return profiles.filter((student) => {
      return (
        student.name.toLowerCase().includes(term) || student.document.toLowerCase().includes(term)
      );
    });
  }, [query, profiles]);

  const totalDebt = selected ? selected.openAmount + selected.overdueAmount : 0;

  const simulatedDeal = useMemo(() => {
    if (!selected) {
      return {
        withDiscount: 0,
        installmentValue: 0,
      };
    }

    const discount = Number(discountPercent) / 100;
    const parcels = Math.max(1, Number(installments));
    const withDiscount = totalDebt * (1 - (Number.isNaN(discount) ? 0 : discount));
    return {
      withDiscount,
      installmentValue: withDiscount / parcels,
    };
  }, [selected, discountPercent, installments, totalDebt]);

  async function handleSend(mode: 'aditivo' | 'negociacao') {
    if (!apiConfig || !selected) {
      setError('Conecte a API e selecione um aluno para enviar.');
      return;
    }

    setSending(mode);
    setError('');
    setLastAction('');

    try {
      const response = await sendNegotiationToCrm(apiConfig, {
        mode,
        profile: selected,
        discountPercent: Number(discountPercent) || 0,
        installments: Math.max(1, Number(installments) || 1),
        firstDueDate,
        totalDebt,
        discountedTotal: simulatedDeal.withDiscount,
        installmentValue: simulatedDeal.installmentValue,
      });

      const ticketId = (response.data as { id?: number })?.id;
      setLastAction(
        `${mode === 'aditivo' ? 'Aditivo' : 'Negociacao'} registrada no CRM com sucesso${
          ticketId ? ` (ID ${ticketId})` : ''
        }.`
      );
      await refreshProfiles('manual');
    } catch (err) {
      const message =
        err instanceof Error ? err.message : 'Falha ao enviar negociacao para o CRM.';
      setError(message);
    } finally {
      setSending('none');
    }
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.statusCard}>
        <Text style={styles.statusLabel}>Fonte de dados</Text>
        <Text style={styles.statusValue}>{connected ? 'API em tempo real' : 'Desconectado'}</Text>
        <Text style={styles.statusHint}>Atualizacao automatica a cada 5 minutos.</Text>
        {loading ? <Text style={styles.statusHint}>Atualizando alunos e dividas...</Text> : null}
        {error ? <Text style={styles.errorText}>Falha: {error}</Text> : null}

        <Pressable
          style={[styles.refreshButton, loading && styles.refreshButtonDisabled]}
          onPress={() => refreshProfiles('manual')}
          disabled={!connected || loading}
        >
          <Text style={styles.refreshButtonText}>{loading ? 'Atualizando...' : 'Atualizar agora'}</Text>
        </Pressable>
      </View>

      {!connected ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyText}>
            Conecte a API na aba Conexao para carregar alunos e enviar negociacoes.
          </Text>
        </View>
      ) : null}

      {connected ? <Text style={styles.label}>Buscar aluno para negociar</Text> : null}
      {connected ? (
        <TextInput
          style={styles.input}
          placeholder="Nome ou documento"
          placeholderTextColor="#6f8fb5"
          value={query}
          onChangeText={setQuery}
        />
      ) : null}

      {connected ? (
        <View style={styles.results}>
          {filtered.map((student) => {
            const isSelected = selected?.id === student.id;
            return (
              <Pressable
                key={student.id}
                style={[styles.studentCard, isSelected && styles.studentCardSelected]}
                onPress={() => setSelected(student)}
              >
                <Text style={styles.studentName}>{student.name}</Text>
                <Text style={styles.studentMeta}>
                  {student.course} - {student.document}
                </Text>
                <Text style={styles.studentMeta}>
                  Aberto: {formatCurrency(student.openAmount)} | Vencido:{' '}
                  {formatCurrency(student.overdueAmount)}
                </Text>
              </Pressable>
            );
          })}
        </View>
      ) : null}

      {connected && selected ? (
        <View style={styles.negotiationBlock}>
          <Text style={styles.blockTitle}>Simulador de negociacao</Text>
          <Text style={styles.blockSubTitle}>
            Aluno: {selected.name} | Ultimo pagamento: {formatDateIso(selected.lastPaymentDate)}
          </Text>
          <Text style={styles.blockSubTitle}>
            Divida total: {formatCurrency(totalDebt)} ({selected.invoicesOpen} titulos)
          </Text>

          <View style={styles.formGrid}>
            <View style={styles.formField}>
              <Text style={styles.fieldLabel}>Desconto (%)</Text>
              <TextInput
                style={styles.input}
                keyboardType="numeric"
                value={discountPercent}
                onChangeText={setDiscountPercent}
              />
            </View>

            <View style={styles.formField}>
              <Text style={styles.fieldLabel}>Parcelas</Text>
              <TextInput
                style={styles.input}
                keyboardType="numeric"
                value={installments}
                onChangeText={setInstallments}
              />
            </View>

            <View style={styles.formField}>
              <Text style={styles.fieldLabel}>1o vencimento</Text>
              <TextInput style={styles.input} value={firstDueDate} onChangeText={setFirstDueDate} />
            </View>
          </View>

          <View style={styles.previewCard}>
            <Text style={styles.previewText}>
              Total com desconto: {formatCurrency(simulatedDeal.withDiscount)}
            </Text>
            <Text style={styles.previewText}>
              Parcela estimada: {formatCurrency(simulatedDeal.installmentValue)}
            </Text>
          </View>

          <Pressable
            style={[styles.primaryButton, sending !== 'none' && styles.refreshButtonDisabled]}
            onPress={() => handleSend('aditivo')}
            disabled={sending !== 'none'}
          >
            <Text style={styles.primaryButtonText}>
              {sending === 'aditivo' ? 'Enviando aditivo...' : 'Gerar aditivo'}
            </Text>
          </Pressable>

          <Pressable
            style={[styles.secondaryButton, sending !== 'none' && styles.refreshButtonDisabled]}
            onPress={() => handleSend('negociacao')}
            disabled={sending !== 'none'}
          >
            <Text style={styles.secondaryButtonText}>
              {sending === 'negociacao' ? 'Enviando negociacao...' : 'Enviar negociacao'}
            </Text>
          </Pressable>

          {lastAction ? <Text style={styles.feedback}>{lastAction}</Text> : null}
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
    paddingBottom: 28,
    gap: 12,
  },
  statusCard: {
    borderWidth: 1,
    borderColor: '#224567',
    borderRadius: 10,
    backgroundColor: '#0f2239',
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
  label: {
    color: '#dcebff',
    fontSize: 15,
    fontWeight: '600',
  },
  input: {
    borderWidth: 1,
    borderColor: '#2a4769',
    borderRadius: 10,
    backgroundColor: '#0f223a',
    color: '#ffffff',
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
  },
  results: {
    gap: 8,
  },
  studentCard: {
    borderWidth: 1,
    borderColor: '#244261',
    borderRadius: 10,
    backgroundColor: '#0f233b',
    padding: 12,
    gap: 3,
  },
  studentCardSelected: {
    borderColor: '#1f7aff',
    backgroundColor: '#123258',
  },
  studentName: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '700',
  },
  studentMeta: {
    color: '#a9c7ec',
    fontSize: 12,
  },
  negotiationBlock: {
    borderWidth: 1,
    borderColor: '#1f3e61',
    borderRadius: 12,
    backgroundColor: '#0f2239',
    padding: 12,
    gap: 10,
  },
  blockTitle: {
    color: '#ffffff',
    fontSize: 17,
    fontWeight: '700',
  },
  blockSubTitle: {
    color: '#a4c5ec',
    fontSize: 12,
  },
  formGrid: {
    gap: 8,
  },
  formField: {
    gap: 4,
  },
  fieldLabel: {
    color: '#cde2ff',
    fontSize: 12,
    fontWeight: '600',
  },
  previewCard: {
    borderRadius: 10,
    padding: 12,
    backgroundColor: '#15314f',
    borderWidth: 1,
    borderColor: '#2c5f94',
    gap: 4,
  },
  previewText: {
    color: '#ecf5ff',
    fontSize: 13,
    fontWeight: '600',
  },
  primaryButton: {
    backgroundColor: '#1f7aff',
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
  },
  primaryButtonText: {
    color: '#ffffff',
    fontWeight: '700',
    fontSize: 13,
  },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#2a547e',
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
    backgroundColor: '#102944',
  },
  secondaryButtonText: {
    color: '#d2e5ff',
    fontWeight: '700',
    fontSize: 13,
  },
  feedback: {
    color: '#7ce3a5',
    fontSize: 12,
    lineHeight: 18,
  },
  emptyState: {
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#224567',
    backgroundColor: '#0e233a',
    padding: 12,
  },
  emptyText: {
    color: '#a4c5ec',
    fontSize: 13,
  },
});
