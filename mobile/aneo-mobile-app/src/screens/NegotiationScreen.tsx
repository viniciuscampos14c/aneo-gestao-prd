import { useMemo, useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { debtProfiles } from '../data/mock';
import type { StudentDebtProfile } from '../types';

function formatCurrency(value: number) {
  return value.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });
}

function formatDate(isoDate: string) {
  const [year, month, day] = isoDate.split('-');
  return `${day}/${month}/${year}`;
}

export function NegotiationScreen() {
  const [query, setQuery] = useState('');
  const [selected, setSelected] = useState<StudentDebtProfile | null>(null);
  const [discountPercent, setDiscountPercent] = useState('8');
  const [installments, setInstallments] = useState('3');
  const [firstDueDate, setFirstDueDate] = useState('2026-05-10');
  const [lastAction, setLastAction] = useState('');

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return debtProfiles;

    return debtProfiles.filter((student) => {
      return (
        student.name.toLowerCase().includes(term) ||
        student.document.toLowerCase().includes(term)
      );
    });
  }, [query]);

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

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.label}>Buscar aluno para negociar</Text>
      <TextInput
        style={styles.input}
        placeholder="Nome ou CPF"
        placeholderTextColor="#6f8fb5"
        value={query}
        onChangeText={setQuery}
      />

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

      {selected ? (
        <View style={styles.negotiationBlock}>
          <Text style={styles.blockTitle}>Simulador de negociacao</Text>
          <Text style={styles.blockSubTitle}>
            Aluno: {selected.name} | Ultimo pagamento: {formatDate(selected.lastPaymentDate)}
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
              <TextInput
                style={styles.input}
                value={firstDueDate}
                onChangeText={setFirstDueDate}
              />
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
            style={styles.primaryButton}
            onPress={() => {
              setLastAction(
                `Aditivo gerado para ${selected.name}, ${installments}x com 1o vencimento em ${firstDueDate}.`
              );
            }}
          >
            <Text style={styles.primaryButtonText}>Gerar aditivo (mock)</Text>
          </Pressable>

          <Pressable
            style={styles.secondaryButton}
            onPress={() => {
              setLastAction(`Negociacao enviada para sincronizacao no sistema central (mock).`);
            }}
          >
            <Text style={styles.secondaryButtonText}>Enviar para sistema central (mock)</Text>
          </Pressable>

          {lastAction ? <Text style={styles.feedback}>{lastAction}</Text> : null}
        </View>
      ) : (
        <View style={styles.emptyState}>
          <Text style={styles.emptyText}>
            Selecione um aluno para abrir a tela de negociacao.
          </Text>
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
    paddingBottom: 28,
    gap: 12,
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
    fontSize: 14,
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
