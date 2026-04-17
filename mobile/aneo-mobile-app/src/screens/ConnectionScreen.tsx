import { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { DEFAULT_API_BASE_URL } from '../config/constants';
import { normalizeApiConfig, testApiConnection } from '../services/apiClient';
import type { ApiConfig } from '../types';

type ConnectionScreenProps = {
  apiConfig: ApiConfig | null;
  onConnect: (config: ApiConfig) => void;
  onDisconnect: () => void;
};

export function ConnectionScreen({ apiConfig, onConnect, onDisconnect }: ConnectionScreenProps) {
  const [baseUrl, setBaseUrl] = useState(apiConfig?.baseUrl ?? DEFAULT_API_BASE_URL);
  const [token, setToken] = useState(apiConfig?.token ?? '');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const connected = useMemo(() => !!apiConfig?.token, [apiConfig]);

  useEffect(() => {
    if (apiConfig) {
      setBaseUrl(apiConfig.baseUrl);
      setToken(apiConfig.token);
      return;
    }

    setBaseUrl(DEFAULT_API_BASE_URL);
    setToken('');
  }, [apiConfig?.baseUrl, apiConfig?.token]);

  async function handleConnect() {
    setLoading(true);
    setError('');
    setMessage('');

    try {
      const finalConfig = normalizeApiConfig({ baseUrl, token });
      if (!finalConfig.baseUrl || !finalConfig.token) {
        throw new Error('Informe URL da API e token.');
      }

      await testApiConnection(finalConfig);
      onConnect(finalConfig);
      setMessage('Conexao validada com sucesso. Dashboard e negociacao agora usam dados reais.');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Falha ao validar conexao.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Conexao com API ANEO</Text>
      <Text style={styles.subtitle}>
        Configure a URL do `api.php` e um Bearer Token com permissoes de leitura em `students` e
        `invoices`.
      </Text>

      <View style={styles.statusCard}>
        <Text style={styles.statusLabel}>Status atual</Text>
        <Text style={[styles.statusValue, connected ? styles.statusOnline : styles.statusOffline]}>
          {connected ? 'Conectado' : 'Desconectado'}
        </Text>
      </View>

      <View style={styles.formField}>
        <Text style={styles.fieldLabel}>URL da API</Text>
        <TextInput
          style={styles.input}
          value={baseUrl}
          onChangeText={setBaseUrl}
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="https://erp-hml.aneobrasil.com.br/api.php"
          placeholderTextColor="#6f8fb5"
        />
      </View>

      <View style={styles.formField}>
        <Text style={styles.fieldLabel}>Token Bearer</Text>
        <TextInput
          style={styles.input}
          value={token}
          onChangeText={setToken}
          autoCapitalize="none"
          autoCorrect={false}
          secureTextEntry
          placeholder="Cole aqui o token"
          placeholderTextColor="#6f8fb5"
        />
      </View>

      <Pressable style={styles.primaryButton} onPress={handleConnect} disabled={loading}>
        <Text style={styles.primaryButtonText}>{loading ? 'Validando...' : 'Conectar API'}</Text>
      </Pressable>

      <Pressable
        style={styles.secondaryButton}
        onPress={() => {
          onDisconnect();
          setMessage('Conexao removida. O app aguarda nova autenticacao.');
          setError('');
        }}
      >
        <Text style={styles.secondaryButtonText}>Limpar conexao</Text>
      </Pressable>

      {message ? <Text style={styles.successText}>{message}</Text> : null}
      {error ? <Text style={styles.errorText}>{error}</Text> : null}

      <View style={styles.hintCard}>
        <Text style={styles.hintTitle}>Permissoes recomendadas para o token</Text>
        <Text style={styles.hintText}>- `students.search`</Text>
        <Text style={styles.hintText}>- `invoices.search`</Text>
        <Text style={styles.hintText}>- `tickets.create`</Text>
        <Text style={styles.hintText}>{'Token criado em: ERP > API > Gerenciamento de API.'}</Text>
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
  title: {
    color: '#ffffff',
    fontSize: 21,
    fontWeight: '700',
  },
  subtitle: {
    color: '#a4c5ec',
    fontSize: 13,
    lineHeight: 19,
  },
  statusCard: {
    borderWidth: 1,
    borderColor: '#2a4d72',
    borderRadius: 10,
    backgroundColor: '#102944',
    padding: 12,
    gap: 4,
  },
  statusLabel: {
    color: '#b8d2f3',
    fontSize: 12,
    fontWeight: '600',
  },
  statusValue: {
    fontSize: 17,
    fontWeight: '700',
  },
  statusOnline: {
    color: '#7ce3a5',
  },
  statusOffline: {
    color: '#f6cf78',
  },
  formField: {
    gap: 6,
  },
  fieldLabel: {
    color: '#d7e9ff',
    fontSize: 13,
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
  successText: {
    color: '#7ce3a5',
    fontSize: 12,
    lineHeight: 18,
  },
  errorText: {
    color: '#ff9da2',
    fontSize: 12,
    lineHeight: 18,
  },
  hintCard: {
    borderWidth: 1,
    borderColor: '#223f60',
    borderRadius: 10,
    backgroundColor: '#0e2238',
    padding: 12,
    gap: 4,
  },
  hintTitle: {
    color: '#dcebff',
    fontSize: 13,
    fontWeight: '700',
  },
  hintText: {
    color: '#9ec0e9',
    fontSize: 12,
  },
});
