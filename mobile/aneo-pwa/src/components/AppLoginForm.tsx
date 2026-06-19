import { useMemo, useState } from 'react';
import { DEFAULT_API_BASE_URL } from '../config/constants';
import {
  connectWithMobileCredentials,
  type MobileCompanyOption,
} from '../services/mobileAuthService';
import type { ApiConfig } from '../types';

type AppLoginFormProps = {
  initialBaseUrl?: string;
  onAuthenticated: (config: ApiConfig) => void;
};

export function AppLoginForm({ initialBaseUrl, onAuthenticated }: AppLoginFormProps) {
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
      const msg = err instanceof Error ? err.message : 'Não foi possível autenticar no app.';
      setError(msg);
      resetCompanySelection();
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="form-card">
      <p className="eyebrow">Acesso seguro</p>
      <h3>Entrar no APP</h3>
      <p className="muted">Use o mesmo login da diretoria. O token continua sendo gerado pelo ERP.</p>

      <div className="field-grid">
        <div className="field-wrap">
          <label htmlFor="login">Usuário ou e-mail</label>
          <input
            id="login"
            className="text-input"
            type="email"
            inputMode="email"
            autoCapitalize="none"
            autoCorrect="off"
            autoComplete="username"
            value={login}
            onChange={(event) => {
              setLogin(event.target.value);
              resetCompanySelection();
            }}
            placeholder="diretoria@empresa.com"
          />
        </div>

        <div className="field-wrap">
          <label htmlFor="password">Senha</label>
          <input
            id="password"
            className="text-input"
            autoComplete="current-password"
            value={password}
            onChange={(event) => {
              setPassword(event.target.value);
              resetCompanySelection();
            }}
            type="password"
            placeholder="Digite sua senha"
          />
        </div>
      </div>

      <div className="install-actions">
        <button type="button" className="primary-button" onClick={() => void handleLogin()} disabled={loading}>
          {loading ? 'Autenticando...' : requiresCompany ? 'Validar novamente' : 'Entrar'}
        </button>
      </div>

      {loading ? <div className="loading-chip">Validando credenciais...</div> : null}

      {requiresCompany ? (
        <div className="detail-card">
          <h4>Selecione a empresa</h4>
          <p className="muted">Esse usuário possui mais de um CNPJ. Escolha a empresa para continuar.</p>

          <div className="course-list">
            {companyOptions.map((company) => {
              const selected = selectedCompanyId === company.id;
              return (
                <button
                  key={company.id}
                  type="button"
                  className={`course-option${selected ? ' is-selected' : ''}`}
                  onClick={() => setSelectedCompanyId(company.id)}
                >
                  <div className="inline-between">
                    <strong>{company.name}</strong>
                    {company.isDefault ? <span className="pill pill-warning">Padrão</span> : null}
                  </div>
                </button>
              );
            })}
          </div>

          <button
            type="button"
            className="secondary-button"
            onClick={() => {
              if (selectedCompanyId) {
                void handleLogin(selectedCompanyId);
              }
            }}
            disabled={loading || !selectedCompanyId}
          >
            {loading ? 'Autenticando...' : 'Entrar com empresa selecionada'}
          </button>
        </div>
      ) : null}

      {message ? <p className="success-text">{message}</p> : null}
      {error ? <p className="alert-text">{error}</p> : null}
    </div>
  );
}
