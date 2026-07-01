import { useCallback, useEffect, useMemo, useState } from 'react';
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
  touchStoredApiConfigSession,
} from './services/apiConfigStorage';
import type { ApiConfig } from './types';

type AppTab = 'dashboard' | 'negotiation' | 'trial-access' | 'students' | 'tickets' | 'connection';

const navigationTabs: Array<{ id: Exclude<AppTab, 'connection'>; label: string; mobileLabel: string; badge: string }> = [
  { id: 'dashboard', label: 'Indicadores', mobileLabel: 'Inicio', badge: 'I' },
  { id: 'negotiation', label: 'Negociacao', mobileLabel: 'Negociar', badge: '%' },
  { id: 'trial-access', label: 'Degustacao', mobileLabel: 'Degustar', badge: 'D' },
  { id: 'students', label: 'Alunos', mobileLabel: 'Alunos', badge: 'A' },
  { id: 'tickets', label: 'Chamados', mobileLabel: 'Fila', badge: 'T' },
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
    if (activeTab === 'tickets') return 'Acompanhamento de aditivos';
    return 'Conexão e sessão';
  }, [activeTab]);

  const handleAuthenticated = useCallback(async (config: ApiConfig) => {
    setApiConfig(config);
    setSessionAuthenticated(true);
    setActiveTab('dashboard');
    await saveStoredApiConfig(config);
  }, []);

  const handleDisconnect = useCallback(async () => {
    setApiConfig(null);
    setSessionAuthenticated(false);
    setActiveTab('dashboard');
    await clearStoredApiConfig();
  }, []);

  useEffect(() => {
    if (!sessionAuthenticated) {
      return;
    }

    let cancelled = false;

    const refreshStoredSession = () => {
      if (document.visibilityState === 'hidden') {
        return;
      }

      void touchStoredApiConfigSession();
    };

    const validateStoredSession = async () => {
      const storedConfig = await loadStoredApiConfig();
      if (cancelled) {
        return;
      }

      if (!storedConfig) {
        await handleDisconnect();
      }
    };

    const intervalId = window.setInterval(() => {
      void validateStoredSession();
    }, 60_000);

    window.addEventListener('pointerdown', refreshStoredSession, { passive: true });
    window.addEventListener('keydown', refreshStoredSession);
    window.addEventListener('scroll', refreshStoredSession, { passive: true });
    document.addEventListener('visibilitychange', refreshStoredSession);

    void touchStoredApiConfigSession();

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
      window.removeEventListener('pointerdown', refreshStoredSession);
      window.removeEventListener('keydown', refreshStoredSession);
      window.removeEventListener('scroll', refreshStoredSession);
      document.removeEventListener('visibilitychange', refreshStoredSession);
    };
  }, [handleDisconnect, sessionAuthenticated]);

  if (!configReady) {
    return (
      <div className="app-shell loading-shell">
        <div className="surface-card centered-card">
          <p className="eyebrow">ANEO Diretoria</p>
          <h1>Carregando sessão</h1>
          <p>Estamos preparando o painel PWA para continuar de onde você parou.</p>
        </div>
      </div>
    );
  }

  if (!sessionAuthenticated) {
    return (
      <div className="app-shell login-shell">
        <main className="login-layout">
          <section className="brand-panel">
            <div className="launch-badge-row">
              <span className="launch-badge">APP oficial</span>
              <span className="launch-badge launch-badge-soft">Acesso direto da diretoria</span>
              <InstallCard compact />
            </div>
            <div className="brand-mark">
              <img src="/aneo-logo.png" alt="ANEO" className="brand-logo" />
            </div>
            <h1>ANEO Diretoria</h1>
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
            <p className="eyebrow">APP Executivo</p>
            <h2>ANEO Diretoria</h2>
          </div>
        </div>

        <nav className="nav-list">
          {navigationTabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              className={`nav-item${activeTab === tab.id ? ' is-active' : ''}`}
              onClick={() => setActiveTab(tab.id)}
            >
              <span className="nav-badge">{tab.badge}</span>
              <span className="nav-label nav-label-desktop">{tab.label}</span>
              <span className="nav-label nav-label-mobile">{tab.mobileLabel}</span>
            </button>
          ))}
        </nav>
      </aside>

      <main className="main-panel">
        <header className="topbar surface-card">
          <div className="topbar-copy">
            <p className="eyebrow">Ambiente instalado</p>
            <h1>{pageTitle}</h1>
            <div className="topbar-badges">
              <span className="topbar-pill">APP oficial ANEO</span>
            </div>
          </div>

          <button
            type="button"
            className="ghost-button topbar-action"
            onClick={() => {
              void handleDisconnect();
            }}
          >
            Encerrar sessão
          </button>
        </header>

        <section className="content-grid">
          {activeTab === 'dashboard' ? (
            <DashboardView apiConfig={apiConfig} onNavigateTab={setActiveTab} />
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
