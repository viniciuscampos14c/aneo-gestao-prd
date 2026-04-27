import { useEffect, useMemo, useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import { Image, Pressable, SafeAreaView, StyleSheet, Text, View } from 'react-native';
import { DashboardScreen } from './src/screens/DashboardScreen';
import { NegotiationScreen } from './src/screens/NegotiationScreen';
import { ConnectionScreen } from './src/screens/ConnectionScreen';
import { AppLoginScreen } from './src/screens/AppLoginScreen';
import { TrialAccessScreen } from './src/screens/TrialAccessScreen';
import { StudentDirectoryScreen } from './src/screens/StudentDirectoryScreen';
import { TicketCenterScreen } from './src/screens/TicketCenterScreen';
import type { ApiConfig } from './src/types';
import {
  clearStoredApiConfig,
  loadStoredApiConfig,
  saveStoredApiConfig,
} from './src/services/apiConfigStorage';
import { DEFAULT_API_BASE_URL } from './src/config/constants';

type AppTab =
  | 'dashboard'
  | 'negotiation'
  | 'trial-access'
  | 'connection'
  | 'students'
  | 'tickets';

export default function App() {
  const [tab, setTab] = useState<AppTab>('dashboard');
  const [apiConfig, setApiConfig] = useState<ApiConfig | null>(null);
  const [sessionAuthenticated, setSessionAuthenticated] = useState(false);
  const [configReady, setConfigReady] = useState(false);

  useEffect(() => {
    let active = true;

    void (async () => {
      const storedConfig = await loadStoredApiConfig();
      if (!active) {
        return;
      }

      if (storedConfig) {
        setApiConfig(storedConfig);
      }
      setConfigReady(true);
    })();

    return () => {
      active = false;
    };
  }, []);

  const pageTitle = useMemo(() => {
    if (tab === 'dashboard') return 'Dashboard Executivo';
    if (tab === 'negotiation') return 'Negociacao de Alunos';
    if (tab === 'trial-access') return 'Degustacao Cursos';
    if (tab === 'students') return 'Alunos';
    if (tab === 'tickets') return 'Chamados';
    return 'Conexao API';
  }, [tab]);

  if (!sessionAuthenticated) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <StatusBar style="light" />

        <View style={styles.loginHeader}>
          <View style={styles.loginBrandWrap}>
            <View style={styles.loginLogoFrame}>
              <Image
                source={require('./assets/logo-aneo-original.png')}
                style={styles.loginLogo}
                resizeMode="contain"
              />
            </View>

            <View style={styles.loginBrandTextBlock}>
              <Text style={styles.loginBrand}>ANEO DIRETORIA</Text>
            </View>
          </View>
        </View>

        <AppLoginScreen
          initialBaseUrl={apiConfig?.baseUrl ?? DEFAULT_API_BASE_URL}
          onAuthenticated={(config) => {
            setApiConfig(config);
            setSessionAuthenticated(true);
            setTab('dashboard');
            void saveStoredApiConfig(config);
          }}
        />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar style="light" />

      <View style={styles.header}>
        <View style={styles.brandRow}>
          <Pressable style={styles.logoFrame} onPress={() => setTab('dashboard')}>
            <Image
              source={require('./assets/logo-aneo-original.png')}
              style={styles.logo}
              resizeMode="contain"
            />
          </Pressable>

          <View style={styles.brandTextBlock}>
            <Text style={styles.brand}>ANEO DIRETORIA</Text>
          </View>
        </View>

        <View>
          <Text style={styles.title}>{pageTitle}</Text>
          <Text style={styles.connectionStatus}>
            {apiConfig
              ? 'API conectada'
              : configReady
                ? 'API desconectada'
                : 'Carregando conexao...'}
          </Text>
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

          <Pressable
            style={[styles.tab, tab === 'connection' && styles.tabActive]}
            onPress={() => setTab('connection')}
          >
            <Text style={[styles.tabText, tab === 'connection' && styles.tabTextActive]}>
              Conexao
            </Text>
          </Pressable>

          <Pressable
            style={[styles.tab, tab === 'trial-access' && styles.tabActive]}
            onPress={() => setTab('trial-access')}
          >
            <Text style={[styles.tabText, tab === 'trial-access' && styles.tabTextActive]}>
              Degustacao
            </Text>
          </Pressable>

          <Pressable
            style={[styles.tab, tab === 'students' && styles.tabActive]}
            onPress={() => setTab('students')}
          >
            <Text style={[styles.tabText, tab === 'students' && styles.tabTextActive]}>Alunos</Text>
          </Pressable>

          <Pressable
            style={[styles.tab, tab === 'tickets' && styles.tabActive]}
            onPress={() => setTab('tickets')}
          >
            <Text style={[styles.tabText, tab === 'tickets' && styles.tabTextActive]}>Chamados</Text>
          </Pressable>
        </View>
      </View>

      {tab === 'dashboard' ? (
        <DashboardScreen
          apiConfig={apiConfig}
          onNavigateTab={(nextTab) => {
            setTab(nextTab);
          }}
        />
      ) : null}
      {tab === 'negotiation' ? <NegotiationScreen apiConfig={apiConfig} /> : null}
      {tab === 'trial-access' ? <TrialAccessScreen apiConfig={apiConfig} /> : null}
      {tab === 'students' ? <StudentDirectoryScreen apiConfig={apiConfig} /> : null}
      {tab === 'tickets' ? <TicketCenterScreen apiConfig={apiConfig} /> : null}
      {tab === 'connection' ? (
        <ConnectionScreen
          apiConfig={apiConfig}
          onConnect={(config) => {
            setApiConfig(config);
            setSessionAuthenticated(true);
            void saveStoredApiConfig(config);
          }}
          onDisconnect={() => {
            setApiConfig(null);
            setSessionAuthenticated(false);
            void clearStoredApiConfig();
          }}
        />
      ) : null}
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
  loginHeader: {
    borderBottomWidth: 1,
    borderBottomColor: '#1f3a5a',
    paddingHorizontal: 18,
    paddingTop: 12,
    paddingBottom: 14,
  },
  loginBrandWrap: {
    alignItems: 'center',
    gap: 10,
  },
  loginLogoFrame: {
    width: '100%',
    maxWidth: 360,
    height: 148,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#2f5a83',
    backgroundColor: '#0f2944',
    paddingHorizontal: 10,
    paddingVertical: 8,
    shadowColor: '#03101f',
    shadowOpacity: 0.35,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 3 },
    elevation: 3,
  },
  loginLogo: {
    width: '100%',
    height: '100%',
    borderRadius: 12,
  },
  loginBrandTextBlock: {
    alignItems: 'center',
  },
  loginBrand: {
    color: '#7db2ff',
    fontSize: 32,
    fontWeight: '700',
    letterSpacing: 3,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  logoFrame: {
    width: 154,
    height: 78,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#2f5a83',
    backgroundColor: '#0f2944',
    paddingHorizontal: 8,
    paddingVertical: 6,
    shadowColor: '#03101f',
    shadowOpacity: 0.35,
    shadowRadius: 7,
    shadowOffset: { width: 0, height: 3 },
    elevation: 3,
  },
  logo: {
    width: '100%',
    height: '100%',
    borderRadius: 10,
  },
  brandTextBlock: {
    flexDirection: 'column',
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
  connectionStatus: {
    color: '#9ec0e9',
    fontSize: 12,
    marginTop: 2,
  },
  tabRow: {
    flexDirection: 'row',
    gap: 8,
    flexWrap: 'wrap',
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
