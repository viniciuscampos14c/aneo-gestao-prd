import { useEffect, useMemo, useState } from 'react';
import { DEFAULT_API_BASE_URL } from '../config/constants';
import {
  connectWithMobileCredentials,
  type MobileCompanyOption,
} from '../services/mobileAuthService';
import type { ApiConfig } from '../types';

type ConnectionPanelProps = {
  apiConfig: ApiConfig | null;
  onConnect: (config: ApiConfig) => void;
  onDisconnect: () => void;
};

export function ConnectionPanel({ apiConfig, onConnect, onDisconnect }: ConnectionPanelProps) {
  const [baseUrl, setBaseUrl] = useState(apiConfig?.baseUrl ?? DEFAULT_API_BASE_URL);
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [companyOptions, setCompanyOptions] = useState<MobileCompanyOption[]>([]);
  const [selectedCompanyId, setSelectedCompanyId] = useState<number | null>(null);

  const connected = useMemo(() => !!apiConfig?.token, [apiConfig]);
  const requiresCompany = companyOptions.length > 0;

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      setBaseUrl(apiConfig?.baseUrl ?? DEFAULT_API_BASE_URL);
    }, 0);

    return () => window.clearTimeout(timeoutId);
  }, [apiConfig?.baseUrl]);

  function resetCompanySelection() {
    setCompanyOptions([]);
    setSelectedCompanyId(null);
  }

  async function handleConnect(companyId?: number) {
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

      onConnect(result.config);
      setPassword('');
      resetCompanySelection();
      setMessage('Conexão realizada com sucesso. O token foi gerado automaticamente.');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Falha ao autenticar no ERP.';
      setError(msg);
      resetCompanySelection();
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="field-grid">
      <section className="surface-card">
        <p className="eyebrow">Sessão</p>
        <h3>Conexão com a API ANEO</h3>
        <p className="muted">O APP usa exatamente o fluxo de token que ja existe no backend.</p>

        <div className="status-card">
          <div className="inline-between">
            <strong>Status atual</strong>
            <span className={`pill ${connected ? 'pill-success' : 'pill-warning'}`}>
              {connected ? 'Conectado' : 'Desconectado'}
            </span>
          </div>
        </div>

        <div className="field-grid">
          <div className="field-wrap">
            <label htmlFor="baseUrl">URL da API</label>
            <input
              id="baseUrl"
              className="text-input"
              type="url"
              inputMode="url"
              autoCapitalize="none"
              autoCorrect="off"
              value={baseUrl}
              onChange={(event) => {
                setBaseUrl(event.target.value);
                resetCompanySelection();
              }}
              placeholder="https://aneo.aneobrasil.com.br/api.php"
            />
          </div>

          <div className="field-wrap">
            <label htmlFor="conn-login">Usuário ou e-mail</label>
            <input
              id="conn-login"
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
            <label htmlFor="conn-password">Senha</label>
            <input
              id="conn-password"
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
          <button type="button" className="primary-button" onClick={() => void handleConnect()} disabled={loading}>
            {loading ? 'Conectando...' : requiresCompany ? 'Validar novamente' : 'Entrar e conectar'}
          </button>

          <button
            type="button"
            className="secondary-button"
            onClick={() => {
              onDisconnect();
              setPassword('');
              setMessage('Conexão removida. O APP aguarda novo login.');
              setError('');
              resetCompanySelection();
            }}
          >
            Limpar conexao
          </button>
        </div>

        {loading ? <div className="loading-chip">Atualizando sessão segura...</div> : null}

        {requiresCompany ? (
          <div className="detail-card">
            <h4>Selecione a empresa</h4>
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
                      {company.isDefault ? <span className="pill pill-warning">Padrao</span> : null}
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
                  void handleConnect(selectedCompanyId);
                }
              }}
              disabled={loading || !selectedCompanyId}
            >
              {loading ? 'Conectando...' : 'Conectar com empresa selecionada'}
            </button>
          </div>
        ) : null}

        {message ? <p className="success-text">{message}</p> : null}
        {error ? <p className="alert-text">{error}</p> : null}
      </section>

      <section className="surface-card">
        <p className="eyebrow">Permissões</p>
        <h3>Escopo atual do APP</h3>
        <div className="list-stack">
          <div className="list-card">
            <strong>Leitura</strong>
            <p className="muted">`students.search/get`, `invoices.search/get`, `courses.search/get`, `tickets.search/get`.</p>
          </div>
          <div className="list-card">
            <strong>Escrita</strong>
            <p className="muted">`tickets.create` para negociações e `trial_accesses.create` para degustação.</p>
          </div>
          <div className="list-card">
            <strong>Fonte</strong>
            <p className="muted">Tudo continua vindo do ERP atual, sem alterar `public_html` neste passo.</p>
          </div>
        </div>
      </section>
    </div>
  );
}
