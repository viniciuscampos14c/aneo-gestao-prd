import { useEffect, useMemo, useState } from 'react';
import { AppLoginForm } from './components/AppLoginForm';
import { ConnectionPanel } from './components/ConnectionPanel';
import { DashboardView } from './components/DashboardView';
import { InstallCard } from './components/InstallCard';
import { NegotiationView } from './components/NegotiationView';
import { StudentDirectoryView } from './components/StudentDirectoryView';
import { TicketCenterView } from './components/TicketCenterView';
import { TrialAccessView } from './components/TrialAccessView';
import { DEFAULT_API_BASE_URL } from './config/constants';
import {
  clearStoredApiConfig,
  loadStoredApiConfig,
  saveStoredApiConfig,
} from './services/apiConfigStorage';
import type { ApiConfig } from './types';

type AppTab = 'dashboard' | 'negotiation' | 'trial-access' | 'students' | 'tickets' | 'connection';

const tabs: Array<{ id: AppTab; label: string; badge: string }> = [
  { id: 'dashboard', label: 'Indicadores', badge: 'I' },
  { id: 'negotiation', label: 'Negociacao', badge: '%' },
  { id: 'trial-access', label: 'Degustacao', badge: 'D' },
  { id: 'students', label: 'Alunos', badge: 'A' },
  { id: 'tickets', label: 'Chamados', badge: 'T' },
  { id: 'connection', label: 'Conexao', badge: 'C' },
];

export default function App() {
  const [activeTab, setActiveTab] = useState<AppTab>('dashboard');
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
        setSessionAuthenticated(true);
      }
      setConfigReady(true);
    })();

    return () => {
      active = false;
    };
  }, []);

  const pageTitle = useMemo(() => {
    if (activeTab === 'dashboard') return 'Painel executivo';
    if (activeTab === 'negotiation') return 'Negociacao financeira';
    if (activeTab === 'trial-access') return 'Degustacao de cursos';
    if (activeTab === 'students') return 'Base de alunos';
    if (activeTab === 'tickets') return 'Central de chamados';
    return 'Conexao e sessao';
  }, [activeTab]);

  async function handleAuthenticated(config: ApiConfig) {
    setApiConfig(config);
    setSessionAuthenticated(true);
    setActiveTab('dashboard');
    await saveStoredApiConfig(config);
  }

  async function handleDisconnect() {
    setApiConfig(null);
    setSessionAuthenticated(false);
    setActiveTab('dashboard');
    await clearStoredApiConfig();
  }

  if (!configReady) {
    return (
      <div className="app-shell loading-shell">
        <div className="surface-card centered-card">
          <p className="eyebrow">ANEO Diretoria</p>
          <h1>Carregando sessao</h1>
          <p>Estamos preparando o painel PWA para continuar de onde voce parou.</p>
        </div>
      </div>
    );
  }

  if (!sessionAuthenticated) {
    return (
      <div className="app-shell login-shell">
        <main className="login-layout">
          <section className="brand-panel">
            <div className="brand-mark">
              <img src="/aneo-logo.png" alt="ANEO" className="brand-logo" />
            </div>
            <p className="eyebrow">PWA Executivo</p>
            <h1>ANEO Diretoria</h1>
            <p className="lead">
              Versao instalavel para celular e desktop, sem loja e sem alterar o ERP principal.
            </p>
            <InstallCard compact />
          </section>

          <section className="surface-card auth-panel">
            <AppLoginForm
              initialBaseUrl={apiConfig?.baseUrl ?? DEFAULT_API_BASE_URL}
              onAuthenticated={(config) => {
                void handleAuthenticated(config);
              }}
            />
          </section>
        </main>
      </div>
    );
  }

  return (
    <div className="app-shell">
      <div className="backdrop-orb backdrop-orb-left" />
      <div className="backdrop-orb backdrop-orb-right" />

      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="sidebar-logo-frame">
            <img src="/aneo-logo.png" alt="ANEO" className="sidebar-logo" />
          </div>
          <div>
            <p className="eyebrow">PWA Executivo</p>
            <h2>ANEO Diretoria</h2>
          </div>
        </div>

        <nav className="nav-list">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              className={`nav-item${activeTab === tab.id ? ' is-active' : ''}`}
              onClick={() => setActiveTab(tab.id)}
            >
              <span className="nav-badge">{tab.badge}</span>
              <span>{tab.label}</span>
            </button>
          ))}
        </nav>
      </aside>

      <main className="main-panel">
        <header className="topbar surface-card">
          <div>
            <p className="eyebrow">Ambiente instalado</p>
            <h1>{pageTitle}</h1>
            <p className="status-line">
              {apiConfig?.token ? 'API conectada em tempo real' : 'Sem conexao ativa'}
            </p>
          </div>

          <button
            type="button"
            className="ghost-button"
            onClick={() => {
              void handleDisconnect();
            }}
          >
            Encerrar sessao
          </button>
        </header>

        <section className="content-grid">
          {activeTab === 'dashboard' ? (
            <DashboardView apiConfig={apiConfig} />
          ) : null}
          {activeTab === 'negotiation' ? <NegotiationView apiConfig={apiConfig} /> : null}
          {activeTab === 'trial-access' ? <TrialAccessView apiConfig={apiConfig} /> : null}
          {activeTab === 'students' ? <StudentDirectoryView apiConfig={apiConfig} /> : null}
          {activeTab === 'tickets' ? <TicketCenterView apiConfig={apiConfig} /> : null}
          {activeTab === 'connection' ? (
            <ConnectionPanel
              apiConfig={apiConfig}
              onConnect={(config) => {
                void handleAuthenticated(config);
              }}
              onDisconnect={() => {
                void handleDisconnect();
              }}
            />
          ) : null}
        </section>
      </main>
    </div>
  );
}
