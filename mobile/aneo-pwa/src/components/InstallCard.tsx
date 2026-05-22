import { useEffect, useState } from 'react';

type DeferredBeforeInstallPrompt = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
};

type InstallCardProps = {
  compact?: boolean;
};

function isStandaloneMode(): boolean {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    (window.navigator as Navigator & { standalone?: boolean }).standalone === true
  );
}

function detectPlatform(): 'ios' | 'android' | 'desktop' {
  const userAgent = window.navigator.userAgent.toLowerCase();
  if (/iphone|ipad|ipod/.test(userAgent)) {
    return 'ios';
  }
  if (/android/.test(userAgent)) {
    return 'android';
  }
  return 'desktop';
}

export function InstallCard({ compact = false }: InstallCardProps) {
  const [promptEvent, setPromptEvent] = useState<DeferredBeforeInstallPrompt | null>(null);
  const [installed, setInstalled] = useState(isStandaloneMode());
  const [platform] = useState(detectPlatform());

  useEffect(() => {
    function handleBeforeInstallPrompt(event: Event) {
      event.preventDefault();
      setPromptEvent(event as DeferredBeforeInstallPrompt);
    }

    function handleInstalled() {
      setInstalled(true);
      setPromptEvent(null);
    }

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleInstalled);

    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
      window.removeEventListener('appinstalled', handleInstalled);
    };
  }, []);

  async function handleInstall() {
    if (!promptEvent) {
      return;
    }

    await promptEvent.prompt();
    await promptEvent.userChoice;
    setPromptEvent(null);
  }

  if (compact) {
    if (installed) {
      return <span className="launch-badge launch-badge-install is-installed">Adicionado ao celular</span>;
    }

    return (
      <button
        type="button"
        className="launch-badge launch-badge-install"
        onClick={() => {
          if (promptEvent) {
            void handleInstall();
          }
        }}
        disabled={!promptEvent}
        title={promptEvent ? 'Adicionar ao celular' : 'Instalacao disponivel no navegador'}
      >
        Adicionar ao celular
      </button>
    );
  }

  return (
    <section className={`install-card${compact ? ' compact-card' : ''}`}>
      <p className="eyebrow">Instalacao</p>
      <h3>Adicionar ao celular</h3>
      {installed ? (
        <p className="success-text">Este APP ja esta aberto em modo instalado.</p>
      ) : (
        <div className="install-guide">
          <p className="muted">
            A instalacao nao depende de login. Primeiro instale o app no navegador, depois abra o atalho e faca o acesso seguro.
          </p>

          {platform === 'android' ? (
            <ol className="install-steps">
              <li>Abra este link no Chrome do celular.</li>
              <li>Se aparecer, toque em <strong>Instalar app</strong>.</li>
              <li>
                Se nao aparecer, abra o menu do Chrome e toque em <strong>Adicionar a tela inicial</strong> ou <strong>Instalar app</strong>.
              </li>
            </ol>
          ) : null}

          {platform === 'ios' ? (
            <ol className="install-steps">
              <li>Abra este link no Safari.</li>
              <li>Toque no botao <strong>Compartilhar</strong>.</li>
              <li>Escolha <strong>Adicionar a Tela de Inicio</strong>.</li>
            </ol>
          ) : null}

          {platform === 'desktop' ? (
            <ol className="install-steps">
              <li>Abra o link em um navegador compativel.</li>
              <li>Use o botao de instalar do navegador ou o menu lateral.</li>
            </ol>
          ) : null}
        </div>
      )}

      {!installed && promptEvent ? (
        <div className="install-actions">
          <button type="button" className="primary-button" onClick={() => void handleInstall()}>
            Instalar app
          </button>
        </div>
      ) : null}

      {!installed && platform === 'android' && !promptEvent ? (
        <div className="install-hint-box">
          <strong>Botao nao apareceu?</strong>
          <p className="muted">
            Isso pode acontecer no primeiro acesso. Tente abrir o menu do Chrome e instalar manualmente.
          </p>
        </div>
      ) : null}
    </section>
  );
}
