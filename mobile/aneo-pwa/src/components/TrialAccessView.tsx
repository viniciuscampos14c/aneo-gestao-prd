import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AUTO_REFRESH_MS } from '../config/constants';
import {
  createTrialAccessFromMobile,
  type CreatedTrialAccess,
  listTrialAccessesForMobile,
  loadPublishedCoursesForTrialAccess,
} from '../services/trialAccessService';
import type { ApiConfig, ApiCourse, ApiTrialAccess } from '../types';
import { formatDateIso } from '../utils/format';

type TrialAccessViewProps = {
  apiConfig: ApiConfig | null;
};

function localIsoDateNow(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function statusLabel(status: string | null | undefined): string {
  const normalized = String(status ?? '').trim().toLowerCase();
  if (normalized === 'active') return 'Ativo';
  if (normalized === 'revoked') return 'Revogado';
  return normalized === '' ? '-' : normalized;
}

export function TrialAccessView({ apiConfig }: TrialAccessViewProps) {
  const [courses, setCourses] = useState<ApiCourse[]>([]);
  const [rows, setRows] = useState<ApiTrialAccess[]>([]);
  const [trialApiReady, setTrialApiReady] = useState(true);
  const [studentName, setStudentName] = useState('');
  const [studentEmail, setStudentEmail] = useState('');
  const [studentPhone, setStudentPhone] = useState('');
  const [accessDate, setAccessDate] = useState(localIsoDateNow());
  const [courseQuery, setCourseQuery] = useState('');
  const [selectedCourseId, setSelectedCourseId] = useState<number | null>(null);
  const [lastCreated, setLastCreated] = useState<CreatedTrialAccess | null>(null);
  const [loading, setLoading] = useState(false);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');
  const loadingRef = useRef(false);

  const connected = useMemo(() => !!apiConfig?.token, [apiConfig]);

  const selectedCourse = useMemo(() => {
    if (!selectedCourseId) return null;
    return courses.find((course) => Number(course.id) === selectedCourseId) ?? null;
  }, [courses, selectedCourseId]);

  const filteredCourses = useMemo(() => {
    const term = courseQuery.trim().toLowerCase();
    if (!term) {
      return courses;
    }

    return courses.filter((course) => String(course.name).toLowerCase().includes(term));
  }, [courseQuery, courses]);

  const refreshData = useCallback(
    async (mode: 'manual' | 'auto' | 'initial') => {
      if (!apiConfig || loadingRef.current) {
        return;
      }

      loadingRef.current = true;
      setLoading(true);
      setError('');

      try {
        const publishedCourses = await loadPublishedCoursesForTrialAccess(apiConfig);
        setCourses(publishedCourses);

        if (!selectedCourseId && publishedCourses.length > 0) {
          setSelectedCourseId(Number(publishedCourses[0].id));
        }

        try {
          const trialRows = await listTrialAccessesForMobile(apiConfig);
          setRows(trialRows);
          setTrialApiReady(true);
        } catch (trialErr) {
          const trialMessage =
            trialErr instanceof Error ? trialErr.message : 'Falha ao carregar acessos de degustacao.';

          setRows([]);
          setTrialApiReady(false);

          if (trialMessage.includes('Recurso desconhecido: trial_accesses')) {
            setError('API do ERP sem o recurso trial_accesses. Publique a atualizacao no ambiente alvo.');
          } else if (trialMessage.includes('Permissao insuficiente: trial_accesses.search')) {
            setError('Token sem permissao de degustacao. Faca logout/login para renovar.');
          } else {
            setError(mode === 'manual' ? `Atualizacao manual falhou: ${trialMessage}` : trialMessage);
          }
        }
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Falha ao carregar cursos publicados.';
        setError(mode === 'manual' ? `Atualizacao manual falhou: ${message}` : message);
      } finally {
        loadingRef.current = false;
        setLoading(false);
      }
    },
    [apiConfig, selectedCourseId]
  );

  useEffect(() => {
    if (!apiConfig) {
      setCourses([]);
      setRows([]);
      setSelectedCourseId(null);
      setTrialApiReady(true);
      setLoading(false);
      setError('');
      setLastCreated(null);
      return;
    }

    void refreshData('initial');
  }, [apiConfig, refreshData]);

  useEffect(() => {
    if (!apiConfig) {
      return;
    }

    const intervalId = window.setInterval(() => {
      void refreshData('auto');
    }, AUTO_REFRESH_MS);

    return () => window.clearInterval(intervalId);
  }, [apiConfig, refreshData]);

  async function handleCreateAccess() {
    if (!apiConfig) {
      setError('Conecte a API para criar acesso de degustacao.');
      return;
    }

    if (!trialApiReady) {
      setError('Recurso de degustacao indisponivel no backend/token atual. Atualize e reconecte o app.');
      return;
    }

    if (studentName.trim() === '') {
      setError('Informe o nome do aluno para degustacao.');
      return;
    }

    if (!selectedCourseId || selectedCourseId <= 0) {
      setError('Selecione um curso publicado.');
      return;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(accessDate.trim())) {
      setError('Informe a data no formato YYYY-MM-DD.');
      return;
    }

    setCreating(true);
    setError('');
    setLastCreated(null);

    try {
      const created = await createTrialAccessFromMobile(apiConfig, {
        studentName,
        studentEmail,
        studentPhone,
        courseId: selectedCourseId,
        accessDate,
      });

      setLastCreated(created);
      setStudentName('');
      setStudentEmail('');
      setStudentPhone('');
      setCourseQuery('');

      const latestRows = await listTrialAccessesForMobile(apiConfig);
      setRows(latestRows);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Falha ao criar acesso de degustacao.';
      setError(message);
    } finally {
      setCreating(false);
    }
  }

  return (
    <div className="field-grid">
      <section className="surface-card">
        <div className="inline-between">
          <div>
            <p className="eyebrow">Degustacao</p>
            <h3>Criar acesso rapido</h3>
            <p className="muted">O PWA reutiliza os mesmos recursos `courses` e `trial_accesses` do backend.</p>
            {error ? <p className="alert-text">{error}</p> : null}
          </div>

          <button
            type="button"
            className="primary-button"
            onClick={() => void refreshData('manual')}
            disabled={!connected || loading}
          >
            {loading ? 'Atualizando...' : 'Atualizar agora'}
          </button>
        </div>

        {connected ? (
          <>
            <div className="field-grid two-columns" style={{ marginTop: 16 }}>
              <div className="field-wrap">
                <label htmlFor="trial-name">Nome do aluno</label>
                <input
                  id="trial-name"
                  className="text-input"
                  value={studentName}
                  onChange={(event) => setStudentName(event.target.value)}
                  placeholder="Nome completo"
                />
              </div>

              <div className="field-wrap">
                <label htmlFor="trial-email">E-mail</label>
                <input
                  id="trial-email"
                  className="text-input"
                  value={studentEmail}
                  onChange={(event) => setStudentEmail(event.target.value)}
                  placeholder="aluno@email.com"
                />
              </div>

              <div className="field-wrap">
                <label htmlFor="trial-phone">Telefone</label>
                <input
                  id="trial-phone"
                  className="text-input"
                  value={studentPhone}
                  onChange={(event) => setStudentPhone(event.target.value)}
                  placeholder="(00) 00000-0000"
                />
              </div>

              <div className="field-wrap">
                <label htmlFor="trial-date">Data liberada</label>
                <input
                  id="trial-date"
                  className="text-input"
                  value={accessDate}
                  onChange={(event) => setAccessDate(event.target.value)}
                  placeholder="2026-05-18"
                />
              </div>
            </div>

            <div className="field-wrap" style={{ marginTop: 16 }}>
              <label htmlFor="trial-course-query">Curso publicado</label>
              <input
                id="trial-course-query"
                className="text-input"
                value={courseQuery}
                onChange={(event) => setCourseQuery(event.target.value)}
                placeholder="Buscar curso..."
              />
            </div>

            <div className="course-list" style={{ marginTop: 16 }}>
              {filteredCourses.slice(0, 10).map((course) => {
                const selected = Number(course.id) === selectedCourseId;
                return (
                  <button
                    key={course.id}
                    type="button"
                    className={`course-option${selected ? ' is-selected' : ''}`}
                    onClick={() => setSelectedCourseId(Number(course.id))}
                  >
                    <strong>{course.name}</strong>
                  </button>
                );
              })}
            </div>

            {selectedCourse ? <p className="success-text">Curso selecionado: {selectedCourse.name}</p> : null}

            <div className="install-actions">
              <button
                type="button"
                className="primary-button"
                onClick={() => void handleCreateAccess()}
                disabled={creating || !trialApiReady}
              >
                {creating ? 'Criando acesso...' : 'Criar acesso rapido'}
              </button>
            </div>
          </>
        ) : (
          <div className="status-card" style={{ marginTop: 16 }}>
            <p className="muted">Conecte a API para usar o modulo de degustacao.</p>
          </div>
        )}
      </section>

      {lastCreated ? (
        <section className="surface-card">
          <p className="eyebrow">Resultado</p>
          <h3>Acesso criado com sucesso</h3>
          <div className="list-stack">
            <div className="list-card"><strong>Aluno:</strong> {lastCreated.student_name}</div>
            <div className="list-card"><strong>Curso:</strong> {lastCreated.course_name}</div>
            <div className="list-card"><strong>Login:</strong> {lastCreated.portal_login}</div>
            <div className="list-card"><strong>Senha:</strong> {lastCreated.portal_password}</div>
            <div className="list-card"><strong>Data liberada:</strong> {formatDateIso(lastCreated.access_date)}</div>
          </div>
        </section>
      ) : null}

      {connected ? (
        <section className="surface-card">
          <p className="eyebrow">Historico</p>
          <h3>Ultimos acessos de degustacao</h3>
          <div className="list-stack" style={{ marginTop: 16 }}>
            {rows.length === 0 ? <p className="muted">Nenhum acesso de degustacao cadastrado.</p> : null}
            {rows.slice(0, 12).map((row) => {
              const isRevoked = String(row.status ?? '').toLowerCase() === 'revoked';
              return (
                <article key={row.id} className="list-card">
                  <div className="inline-between">
                    <strong>{String(row.student_name ?? 'Aluno sem nome')}</strong>
                    <span className={`pill ${isRevoked ? 'pill-danger' : 'pill-success'}`}>
                      {statusLabel(row.status)}
                    </span>
                  </div>
                  <p className="muted">Curso: {String(row.course_name ?? '-')}</p>
                  <p className="muted">Login portal: {String(row.portal_login ?? '-')}</p>
                  <p className="muted">Data liberada: {formatDateIso(String(row.access_date ?? ''))}</p>
                </article>
              );
            })}
          </div>
        </section>
      ) : null}
    </div>
  );
}
