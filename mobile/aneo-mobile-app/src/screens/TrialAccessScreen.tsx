import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { AUTO_REFRESH_MS } from '../config/constants';
import {
  createTrialAccessFromMobile,
  type CreatedTrialAccess,
  listTrialAccessesForMobile,
  loadPublishedCoursesForTrialAccess,
} from '../services/trialAccessService';
import type { ApiConfig, ApiCourse, ApiTrialAccess } from '../types';
import { formatDateIso } from '../utils/format';

type TrialAccessScreenProps = {
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

export function TrialAccessScreen({ apiConfig }: TrialAccessScreenProps) {
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
            setError('API do ERP sem o recurso trial_accesses. Publique a atualizacao no HML.');
          } else if (trialMessage.includes('Permissao insuficiente: trial_accesses.search')) {
            setError('Token mobile sem permissao de degustacao. Faça logout/login para renovar.');
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

    const intervalId = setInterval(() => {
      void refreshData('auto');
    }, AUTO_REFRESH_MS);

    return () => clearInterval(intervalId);
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
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.statusCard}>
        <Text style={styles.statusLabel}>Fonte de dados</Text>
        <Text style={styles.statusValue}>{connected ? 'API em tempo real' : 'Desconectado'}</Text>
        <Text style={styles.statusHint}>Atualizacao automatica a cada 5 minutos.</Text>
        {loading ? <Text style={styles.statusHint}>Atualizando cursos e acessos...</Text> : null}
        {error ? <Text style={styles.errorText}>Falha: {error}</Text> : null}

        <Pressable
          style={[styles.refreshButton, loading && styles.refreshButtonDisabled]}
          onPress={() => void refreshData('manual')}
          disabled={!connected || loading}
        >
          <Text style={styles.refreshButtonText}>{loading ? 'Atualizando...' : 'Atualizar agora'}</Text>
        </Pressable>
      </View>

      {!connected ? (
        <View style={styles.emptyCard}>
          <Text style={styles.emptyTitle}>Conecte a API para usar a degustacao</Text>
          <Text style={styles.emptyText}>
            Abra a aba Conexao, informe URL e token, e volte para criar acessos rapidos.
          </Text>
        </View>
      ) : null}

      {connected ? (
        <View style={styles.formCard}>
          <Text style={styles.formTitle}>Criar acesso rapido de degustacao</Text>
          <Text style={styles.formHint}>
            O app cria aluno + login de portal + matricula no curso selecionado.
          </Text>

          <View style={styles.fieldWrap}>
            <Text style={styles.fieldLabel}>Nome do aluno</Text>
            <TextInput
              style={styles.input}
              value={studentName}
              onChangeText={setStudentName}
              placeholder="Nome completo"
              placeholderTextColor="#6f8fb5"
            />
          </View>

          <View style={styles.fieldWrap}>
            <Text style={styles.fieldLabel}>E-mail (opcional)</Text>
            <TextInput
              style={styles.input}
              value={studentEmail}
              onChangeText={setStudentEmail}
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="aluno@email.com"
              placeholderTextColor="#6f8fb5"
            />
          </View>

          <View style={styles.fieldWrap}>
            <Text style={styles.fieldLabel}>Telefone (opcional)</Text>
            <TextInput
              style={styles.input}
              value={studentPhone}
              onChangeText={setStudentPhone}
              placeholder="(00) 00000-0000"
              placeholderTextColor="#6f8fb5"
            />
          </View>

          <View style={styles.fieldWrap}>
            <Text style={styles.fieldLabel}>Data liberada (YYYY-MM-DD)</Text>
            <TextInput
              style={styles.input}
              value={accessDate}
              onChangeText={setAccessDate}
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="2026-04-27"
              placeholderTextColor="#6f8fb5"
            />
          </View>

          <View style={styles.fieldWrap}>
            <Text style={styles.fieldLabel}>Curso publicado</Text>
            <TextInput
              style={styles.input}
              value={courseQuery}
              onChangeText={setCourseQuery}
              placeholder="Buscar curso..."
              placeholderTextColor="#6f8fb5"
            />
          </View>

          <View style={styles.courseList}>
            {filteredCourses.slice(0, 10).map((course) => {
              const selected = Number(course.id) === selectedCourseId;
              return (
                <Pressable
                  key={course.id}
                  style={[styles.courseOption, selected && styles.courseOptionSelected]}
                  onPress={() => setSelectedCourseId(Number(course.id))}
                >
                  <Text style={[styles.courseOptionText, selected && styles.courseOptionTextSelected]}>
                    {course.name}
                  </Text>
                </Pressable>
              );
            })}
            {filteredCourses.length === 0 ? (
              <Text style={styles.noCourseText}>Nenhum curso publicado encontrado.</Text>
            ) : null}
          </View>

          {selectedCourse ? (
            <Text style={styles.selectedCourseText}>Curso selecionado: {selectedCourse.name}</Text>
          ) : null}

          <Pressable
            style={[styles.primaryButton, creating && styles.refreshButtonDisabled]}
            onPress={() => void handleCreateAccess()}
            disabled={creating || !trialApiReady}
          >
            <Text style={styles.primaryButtonText}>
              {creating ? 'Criando acesso...' : 'Criar acesso rapido'}
            </Text>
          </Pressable>
        </View>
      ) : null}

      {lastCreated ? (
        <View style={styles.successCard}>
          <Text style={styles.successTitle}>Acesso criado com sucesso</Text>
          <Text style={styles.successText}>Aluno: {lastCreated.student_name}</Text>
          <Text style={styles.successText}>Curso: {lastCreated.course_name}</Text>
          <Text style={styles.successText}>Login: {lastCreated.portal_login}</Text>
          <Text style={styles.successText}>Senha: {lastCreated.portal_password}</Text>
          <Text style={styles.successText}>Data liberada: {formatDateIso(lastCreated.access_date)}</Text>
        </View>
      ) : null}

      {connected ? (
        <View style={styles.listCard}>
          <Text style={styles.listTitle}>Ultimos acessos de degustacao</Text>
          {rows.length === 0 ? (
            <Text style={styles.emptyText}>Nenhum acesso de degustacao cadastrado.</Text>
          ) : (
            <View style={styles.rowsWrap}>
              {rows.slice(0, 12).map((row) => {
                const isRevoked = String(row.status ?? '').toLowerCase() === 'revoked';
                return (
                  <View key={row.id} style={styles.rowCard}>
                    <View style={styles.rowHeader}>
                      <Text style={styles.rowTitle}>{String(row.student_name ?? 'Aluno sem nome')}</Text>
                      <Text style={[styles.statusPill, isRevoked ? styles.statusPillRevoked : styles.statusPillActive]}>
                        {statusLabel(row.status)}
                      </Text>
                    </View>
                    <Text style={styles.rowMeta}>Curso: {String(row.course_name ?? '-')}</Text>
                    <Text style={styles.rowMeta}>Login portal: {String(row.portal_login ?? '-')}</Text>
                    <Text style={styles.rowMeta}>Data liberada: {formatDateIso(String(row.access_date ?? ''))}</Text>
                  </View>
                );
              })}
            </View>
          )}
        </View>
      ) : null}
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
  statusCard: {
    borderWidth: 1,
    borderColor: '#224567',
    borderRadius: 12,
    backgroundColor: '#0f2239',
    padding: 12,
    gap: 6,
  },
  statusLabel: {
    color: '#9fc1eb',
    fontSize: 12,
    fontWeight: '600',
  },
  statusValue: {
    color: '#e9f2ff',
    fontSize: 16,
    fontWeight: '700',
  },
  statusHint: {
    color: '#9fc1eb',
    fontSize: 12,
  },
  errorText: {
    color: '#ff9da2',
    fontSize: 12,
  },
  refreshButton: {
    marginTop: 4,
    borderWidth: 1,
    borderColor: '#2c5f94',
    borderRadius: 10,
    backgroundColor: '#123258',
    paddingVertical: 10,
    alignItems: 'center',
  },
  refreshButtonDisabled: {
    opacity: 0.6,
  },
  refreshButtonText: {
    color: '#d9ebff',
    fontSize: 13,
    fontWeight: '700',
  },
  emptyCard: {
    borderWidth: 1,
    borderColor: '#2f4c6f',
    borderRadius: 12,
    backgroundColor: '#10263f',
    padding: 14,
    gap: 4,
  },
  emptyTitle: {
    color: '#f2f7ff',
    fontSize: 16,
    fontWeight: '700',
  },
  emptyText: {
    color: '#b0ccec',
    fontSize: 13,
    lineHeight: 18,
  },
  formCard: {
    borderWidth: 1,
    borderColor: '#1f3e61',
    borderRadius: 12,
    backgroundColor: '#0f2239',
    padding: 12,
    gap: 8,
  },
  formTitle: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
  },
  formHint: {
    color: '#9fc1eb',
    fontSize: 12,
    marginBottom: 2,
  },
  fieldWrap: {
    gap: 4,
  },
  fieldLabel: {
    color: '#cde2ff',
    fontSize: 12,
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
  courseList: {
    borderWidth: 1,
    borderColor: '#1f3e61',
    borderRadius: 10,
    padding: 8,
    gap: 6,
    backgroundColor: '#0e2035',
  },
  courseOption: {
    borderWidth: 1,
    borderColor: '#244261',
    borderRadius: 9,
    backgroundColor: '#0f233b',
    paddingVertical: 9,
    paddingHorizontal: 10,
  },
  courseOptionSelected: {
    borderColor: '#1f7aff',
    backgroundColor: '#123258',
  },
  courseOptionText: {
    color: '#cfe4ff',
    fontSize: 13,
    fontWeight: '600',
  },
  courseOptionTextSelected: {
    color: '#ffffff',
  },
  noCourseText: {
    color: '#9fc1eb',
    fontSize: 12,
  },
  selectedCourseText: {
    color: '#7ce3a5',
    fontSize: 12,
    fontWeight: '600',
  },
  primaryButton: {
    backgroundColor: '#1f7aff',
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
    marginTop: 2,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontWeight: '700',
    fontSize: 13,
  },
  successCard: {
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#2b7c52',
    backgroundColor: '#0f2b22',
    padding: 12,
    gap: 4,
  },
  successTitle: {
    color: '#7ce3a5',
    fontSize: 14,
    fontWeight: '700',
  },
  successText: {
    color: '#c5f3d7',
    fontSize: 12,
  },
  listCard: {
    borderWidth: 1,
    borderColor: '#1f3e61',
    borderRadius: 12,
    backgroundColor: '#0f2239',
    padding: 12,
    gap: 8,
  },
  listTitle: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '700',
  },
  rowsWrap: {
    gap: 8,
  },
  rowCard: {
    borderWidth: 1,
    borderColor: '#244261',
    borderRadius: 10,
    backgroundColor: '#0f233b',
    padding: 10,
    gap: 4,
  },
  rowHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
  },
  rowTitle: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
    flex: 1,
  },
  rowMeta: {
    color: '#a9c7ec',
    fontSize: 12,
  },
  statusPill: {
    borderRadius: 999,
    paddingVertical: 3,
    paddingHorizontal: 9,
    fontSize: 11,
    fontWeight: '700',
    overflow: 'hidden',
  },
  statusPillActive: {
    backgroundColor: '#baf2d1',
    color: '#0f6c46',
  },
  statusPillRevoked: {
    backgroundColor: '#ffd3d5',
    color: '#9b1d22',
  },
});
