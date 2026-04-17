import { useMemo, useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import { Pressable, SafeAreaView, StyleSheet, Text, View } from 'react-native';
import { DashboardScreen } from './src/screens/DashboardScreen';
import { NegotiationScreen } from './src/screens/NegotiationScreen';

type AppTab = 'dashboard' | 'negotiation';

export default function App() {
  const [tab, setTab] = useState<AppTab>('dashboard');

  const pageTitle = useMemo(() => {
    return tab === 'dashboard' ? 'Dashboard Executivo' : 'Negociacao de Alunos';
  }, [tab]);

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar style="light" />

      <View style={styles.header}>
        <View>
          <Text style={styles.brand}>ANEO DIRETORIA</Text>
          <Text style={styles.title}>{pageTitle}</Text>
        </View>

        <View style={styles.tabRow}>
          <Pressable
            style={[styles.tab, tab === 'dashboard' && styles.tabActive]}
            onPress={() => setTab('dashboard')}
          >
            <Text style={[styles.tabText, tab === 'dashboard' && styles.tabTextActive]}>
              Indicadores
            </Text>
          </Pressable>

          <Pressable
            style={[styles.tab, tab === 'negotiation' && styles.tabActive]}
            onPress={() => setTab('negotiation')}
          >
            <Text style={[styles.tabText, tab === 'negotiation' && styles.tabTextActive]}>
              Negociacao
            </Text>
          </Pressable>
        </View>
      </View>

      {tab === 'dashboard' ? <DashboardScreen /> : <NegotiationScreen />}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#0a1b2f',
  },
  header: {
    borderBottomWidth: 1,
    borderBottomColor: '#1f3a5a',
    paddingHorizontal: 18,
    paddingBottom: 14,
    paddingTop: 10,
    gap: 14,
  },
  brand: {
    color: '#7db2ff',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 1,
  },
  title: {
    color: '#eef5ff',
    fontSize: 24,
    fontWeight: '700',
    marginTop: 4,
  },
  tabRow: {
    flexDirection: 'row',
    gap: 8,
  },
  tab: {
    borderWidth: 1,
    borderColor: '#2f4c6f',
    borderRadius: 999,
    paddingVertical: 8,
    paddingHorizontal: 14,
  },
  tabActive: {
    backgroundColor: '#1f7aff',
    borderColor: '#1f7aff',
  },
  tabText: {
    color: '#c2d8f8',
    fontSize: 13,
    fontWeight: '600',
  },
  tabTextActive: {
    color: '#ffffff',
  },
});
