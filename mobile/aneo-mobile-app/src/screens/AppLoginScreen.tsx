import { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { DEFAULT_API_BASE_URL } from '../config/constants';
import {
  connectWithMobileCredentials,
  type MobileCompanyOption,
} from '../services/mobileAuthService';
import type { ApiConfig } from '../types';

type AppLoginScreenProps = {
  initialBaseUrl?: string;
  onAuthenticated: (config: ApiConfig) => void;
};

export function AppLoginScreen({ initialBaseUrl, onAuthenticated }: AppLoginScreenProps) {
  const baseUrl = (initialBaseUrl || DEFAULT_API_BASE_URL).trim();
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [companyOptions, setCompanyOptions] = useState<MobileCompanyOption[]>([]);
  const [selectedCompanyId, setSelectedCompanyId] = useState<number | null>(null);

  const requiresCompany = useMemo(() => companyOptions.length > 0, [companyOptions.length]);

  function resetCompanySelection() {
    setCompanyOptions([]);
    setSelectedCompanyId(null);
  }

  async function handleLogin(companyId?: number) {
    setLoading(true);
    setError('');
    setMessage('');

    try {
      const result = await connectWithMobileCredentials({
        baseUrl,
        login,
        password,
        companyId,
      });

      if (result.status === 'company_required') {
        setCompanyOptions(result.companies);
        const defaultCompany =
          result.companies.find((company) => company.isDefault) ?? result.companies[0];
        setSelectedCompanyId(defaultCompany?.id ?? null);
        setMessage(result.message);
        return;
      }

      setPassword('');
      resetCompanySelection();
      onAuthenticated(result.config);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Nao foi possivel autenticar no app.';
      setError(msg);
      resetCompanySelection();
    } finally {
      setLoading(false);
    }
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Entrar no App</Text>
      <Text style={styles.subtitle}>
        Informe login e senha para acessar o aplicativo da diretoria.
      </Text>

      <View style={styles.formField}>
        <Text style={styles.fieldLabel}>Usuario ou e-mail</Text>
        <TextInput
          style={styles.input}
          value={login}
          onChangeText={(value) => {
            setLogin(value);
            resetCompanySelection();
          }}
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="diretoria@empresa.com"
          placeholderTextColor="#6f8fb5"
        />
      </View>

      <View style={styles.formField}>
        <Text style={styles.fieldLabel}>Senha</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={(value) => {
            setPassword(value);
            resetCompanySelection();
          }}
          autoCapitalize="none"
          autoCorrect={false}
          secureTextEntry
          placeholder="Digite sua senha"
          placeholderTextColor="#6f8fb5"
        />
      </View>

      <Pressable style={styles.primaryButton} onPress={() => handleLogin()} disabled={loading}>
        <Text style={styles.primaryButtonText}>
          {loading ? 'Autenticando...' : requiresCompany ? 'Validar novamente' : 'Entrar'}
        </Text>
      </Pressable>

      {requiresCompany ? (
        <View style={styles.companyCard}>
          <Text style={styles.companyTitle}>Selecione a empresa</Text>
          <Text style={styles.companyHint}>
            Esse usuario possui mais de um CNPJ. Escolha a empresa para continuar.
          </Text>

          <View style={styles.companyList}>
            {companyOptions.map((company) => {
              const selected = selectedCompanyId === company.id;
              return (
                <Pressable
                  key={company.id}
                  style={[styles.companyOption, selected && styles.companyOptionSelected]}
                  onPress={() => setSelectedCompanyId(company.id)}
                >
                  <Text style={[styles.companyName, selected && styles.companyNameSelected]}>
                    {company.name}
                  </Text>
                  {company.isDefault ? <Text style={styles.companyBadge}>Padrao</Text> : null}
                </Pressable>
              );
            })}
          </View>

          <Pressable
            style={[
              styles.secondaryButton,
              (loading || !selectedCompanyId) && styles.secondaryButtonDisabled,
            ]}
            onPress={() => {
              if (selectedCompanyId) {
                void handleLogin(selectedCompanyId);
              }
            }}
            disabled={loading || !selectedCompanyId}
          >
            <Text style={styles.secondaryButtonText}>
              {loading ? 'Autenticando...' : 'Entrar com empresa selecionada'}
            </Text>
          </Pressable>
        </View>
      ) : null}

      {message ? <Text style={styles.successText}>{message}</Text> : null}
      {error ? <Text style={styles.errorText}>{error}</Text> : null}
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
  companyCard: {
    borderWidth: 1,
    borderColor: '#2a4d72',
    borderRadius: 10,
    backgroundColor: '#0e2238',
    padding: 12,
    gap: 8,
  },
  companyTitle: {
    color: '#dcebff',
    fontSize: 14,
    fontWeight: '700',
  },
  companyHint: {
    color: '#9ec0e9',
    fontSize: 12,
    lineHeight: 17,
  },
  companyList: {
    gap: 8,
  },
  companyOption: {
    borderWidth: 1,
    borderColor: '#2a4769',
    borderRadius: 10,
    backgroundColor: '#0f223a',
    padding: 10,
    gap: 4,
  },
  companyOptionSelected: {
    borderColor: '#1f7aff',
    backgroundColor: '#123258',
  },
  companyName: {
    color: '#dcebff',
    fontSize: 13,
    fontWeight: '600',
  },
  companyNameSelected: {
    color: '#ffffff',
  },
  companyBadge: {
    alignSelf: 'flex-start',
    color: '#9ec0e9',
    fontSize: 11,
    fontWeight: '600',
  },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#2a547e',
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
    backgroundColor: '#102944',
  },
  secondaryButtonDisabled: {
    opacity: 0.6,
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
});
