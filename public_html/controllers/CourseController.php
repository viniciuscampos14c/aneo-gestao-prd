<?php

class CourseController extends BaseController
{
    private CourseModel $courses;
    private StudentModel $students;
    private AcademicCalendarModel $calendar;
    private AuditLogService $audit;
    private CourseLiveSessionModel $liveSessions;

    public function __construct()
    {
        $this->courses      = new CourseModel();
        $this->students     = new StudentModel();
        $this->calendar     = new AcademicCalendarModel();
        $this->audit        = new AuditLogService();
        $this->liveSessions = new CourseLiveSessionModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('courses');

        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->courses->listCourses($filters, $perPage, $page);

        $this->render('courses/index', [
            'title' => 'Cursos EAD',
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function create(): void
    {
        require_auth();
        require_permission('courses.create');

        $this->render('courses/form', [
            'title' => 'Criar Curso',
            'course' => null,
            'materialFiles' => [],
            'categories' => $this->courses->categories(),
            'lmsFeatureAvailable' => $this->courses->lmsFeatureAvailable(),
            'courseModules' => [],
            'action' => route('courses/store'),
        ]);
    }

    public function store(): void
    {
        require_auth();
        require_permission('courses.create');
        csrf_validate();

        $data = $this->collectFormData();
        if ($data['name'] === '') {
            $this->error('Informe o nome do curso.');
            $this->redirect('courses/create');
        }

        $id = $this->courses->createCourse($data, (int) current_user()['id']);
        $this->success('Curso criado #' . $id . '.');
        $this->redirect('courses');
    }

    public function edit(): void
    {
        require_auth();
        require_permission('courses.edit');

        $id = (int) request('id');
        $course = $this->courses->findCourse($id);

        if (!$course) {
            $this->error('Curso nao encontrado.');
            $this->redirect('courses');
        }

        $companyId      = (int) current_company_id();
        $zoomConfigured = $this->liveSessions->getZoomCredentials($companyId) !== null;
        $courseZoomSessions = $this->liveSessions->tableExists()
            ? $this->liveSessions->listByCourse($id, $companyId)
            : [];

        $this->render('courses/form', [
            'title'              => 'Editar Curso',
            'course'             => $course,
            'materialFiles'      => $this->courses->listCourseMaterials($id),
            'categories'         => $this->courses->categories(),
            'lmsFeatureAvailable'=> $this->courses->lmsFeatureAvailable(),
            'courseModules'      => $this->courses->listCourseModulesWithLessons($id),
            'action'             => route('courses/update&id=' . $id),
            'zoomConfigured'     => $zoomConfigured,
            'courseZoomSessions' => $courseZoomSessions,
        ]);
    }

    public function update(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $id = (int) request('id');
        $course = $this->courses->findCourse($id);

        if (!$course) {
            $this->error('Curso nao encontrado.');
            $this->redirect('courses');
        }

        $data = $this->collectFormData();
        $this->courses->updateCourse($id, $data);

        $this->success('Curso atualizado.');
        $this->redirect('courses');
    }

    public function delete(): void
    {
        require_auth();
        require_permission('courses.delete');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->courses->deleteCourse($id);
            $this->success('Curso removido.');
        }

        $this->redirect('courses');
    }

    public function uploadMaterial(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) request('id');
        if ($courseId <= 0 || !$this->courses->findCourse($courseId)) {
            $this->error('Curso nao encontrado.');
            $this->redirect('courses');
        }

        $uploaded = $this->handleMaterialUpload($courseId, $_FILES['material_files'] ?? null);
        if ($uploaded > 0) {
            $this->success($uploaded . ' arquivo(s) de material anexado(s).');
        } else {
            $this->error('Nenhum arquivo valido foi enviado.');
        }

        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function deleteMaterial(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) post('course_id');
        $uploadId = (int) post('upload_id');

        if ($courseId <= 0 || $uploadId <= 0) {
            $this->error('Material invalido.');
            $this->redirect('courses');
        }

        $course = $this->courses->findCourse($courseId);
        if (!$course) {
            $this->error('Curso nao encontrado.');
            $this->redirect('courses');
        }

        $material = $this->courses->findCourseMaterial($uploadId);
        if (!$material || (int) $material['entity_id'] !== $courseId) {
            $this->error('Arquivo nao encontrado para este curso.');
            $this->redirect('courses/edit&id=' . $courseId);
        }

        $this->safeRemoveCourseMaterialFile((string) $material['file_path']);
        $this->courses->deleteCourseMaterial($uploadId);

        $this->success('Arquivo removido.');
        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function storeModule(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) post('course_id');
        if ($courseId <= 0) {
            $this->error('Curso invalido para cadastro de modulo.');
            $this->redirect('courses');
        }

        if (!$this->courses->lmsFeatureAvailable()) {
            $this->error('LMS modular nao habilitado no banco. Execute a migracao de trilha de aulas.');
            $this->redirect('courses/edit&id=' . $courseId);
        }

        $moduleId = $this->courses->createCourseModule($courseId, [
            'title' => trim((string) post('title')),
            'description' => trim((string) post('description')),
            'display_order' => (int) post('display_order', 0),
            'is_active' => post('is_active') ? 1 : 0,
        ], (int) current_user()['id']);

        if ($moduleId > 0) {
            $this->success('Modulo criado com sucesso.');
        } else {
            $this->error('Nao foi possivel criar o modulo. Verifique os dados obrigatorios.');
        }

        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function updateModule(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) post('course_id');
        $moduleId = (int) post('module_id');
        if ($courseId <= 0 || $moduleId <= 0) {
            $this->error('Modulo invalido.');
            $this->redirect('courses');
        }

        $ok = $this->courses->updateCourseModule($moduleId, [
            'title' => trim((string) post('title')),
            'description' => trim((string) post('description')),
            'display_order' => (int) post('display_order', 1),
            'is_active' => post('is_active') ? 1 : 0,
        ]);

        if ($ok) {
            $this->success('Modulo atualizado.');
        } else {
            $this->error('Nao foi possivel atualizar o modulo.');
        }

        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function deleteModule(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) post('course_id');
        $moduleId = (int) post('module_id');
        if ($courseId <= 0 || $moduleId <= 0) {
            $this->error('Modulo invalido.');
            $this->redirect('courses');
        }

        if ($this->courses->deleteCourseModule($moduleId)) {
            $this->success('Modulo removido.');
        } else {
            $this->error('Nao foi possivel remover o modulo.');
        }

        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function storeLesson(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) post('course_id');
        $moduleId = (int) post('module_id');
        if ($courseId <= 0 || $moduleId <= 0) {
            $this->error('Aula invalida.');
            $this->redirect('courses');
        }

        if (!$this->courses->lmsFeatureAvailable()) {
            $this->error('LMS modular nao habilitado no banco. Execute a migracao de trilha de aulas.');
            $this->redirect('courses/edit&id=' . $courseId);
        }

        $lessonId = $this->courses->createCourseLesson($courseId, $moduleId, [
            'title' => trim((string) post('title')),
            'description' => trim((string) post('description')),
            'video_url' => trim((string) post('video_url')),
            'duration_seconds' => (int) post('duration_seconds', 0),
            'min_progress_percent' => (int) post('min_progress_percent', 70),
            'display_order' => (int) post('display_order', 0),
            'is_required' => post('is_required') ? 1 : 0,
            'is_active' => post('is_active') ? 1 : 0,
        ], (int) current_user()['id']);

        if ($lessonId > 0) {
            $this->success('Aula criada com sucesso.');
        } else {
            $this->error('Nao foi possivel criar a aula. Verifique titulo e URL do video.');
        }

        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function updateLesson(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) post('course_id');
        $lessonId = (int) post('lesson_id');
        if ($courseId <= 0 || $lessonId <= 0) {
            $this->error('Aula invalida.');
            $this->redirect('courses');
        }

        $ok = $this->courses->updateCourseLesson($lessonId, [
            'title' => trim((string) post('title')),
            'description' => trim((string) post('description')),
            'video_url' => trim((string) post('video_url')),
            'duration_seconds' => (int) post('duration_seconds', 0),
            'min_progress_percent' => (int) post('min_progress_percent', 70),
            'display_order' => (int) post('display_order', 1),
            'is_required' => post('is_required') ? 1 : 0,
            'is_active' => post('is_active') ? 1 : 0,
        ]);

        if ($ok) {
            $this->success('Aula atualizada.');
        } else {
            $this->error('Nao foi possivel atualizar a aula.');
        }

        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function deleteLesson(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $courseId = (int) post('course_id');
        $lessonId = (int) post('lesson_id');
        if ($courseId <= 0 || $lessonId <= 0) {
            $this->error('Aula invalida.');
            $this->redirect('courses');
        }

        if ($this->courses->deleteCourseLesson($lessonId)) {
            $this->success('Aula removida.');
        } else {
            $this->error('Nao foi possivel remover a aula.');
        }

        $this->redirect('courses/edit&id=' . $courseId);
    }

    public function categories(): void
    {
        require_auth();
        require_permission('courses.category');

        $this->render('courses/categories', [
            'title' => 'Categorias de Cursos',
            'categories' => $this->courses->categories(),
        ]);
    }

    public function storeCategory(): void
    {
        require_auth();
        require_permission('courses.category');
        csrf_validate();

        $name = trim((string) post('name'));
        if ($name !== '') {
            $this->courses->createCategory($name, (int) current_user()['id']);
            $this->success('Categoria criada.');
        }

        $this->redirect('courses/categories');
    }

    public function deleteCategory(): void
    {
        require_auth();
        require_permission('courses.category');
        csrf_validate();

        $id = (int) post('id');
        if ($id > 0) {
            $this->courses->deleteCategory($id);
            $this->success('Categoria removida.');
        }

        $this->redirect('courses/categories');
    }

    public function enrollments(): void
    {
        require_auth();
        require_permission('courses.enrollment');

        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->courses->listEnrollments($filters, $perPage, $page);

        $allCourses = $this->courses->listCourses([], 1000, 1);
        $allStudents = $this->students->list([], 1000, 1);

        $this->render('courses/enrollments', [
            'title' => 'Matriculas',
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'courses' => $allCourses['rows'],
            'students' => $allStudents['rows'],
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function storeEnrollment(): void
    {
        require_auth();
        require_permission('courses.enrollment');
        csrf_validate();

        $data = [
            'student_id' => (int) post('student_id'),
            'course_id' => (int) post('course_id'),
            'status' => trim((string) post('status', 'active')),
            'started_at' => trim((string) post('started_at')),
            'completed_at' => trim((string) post('completed_at')),
        ];

        if ($data['student_id'] > 0 && $data['course_id'] > 0) {
            $this->courses->createEnrollment($data, (int) current_user()['id']);
            $this->success('Matricula criada.');
        } else {
            $this->error('Aluno e curso sao obrigatorios.');
        }

        $this->redirect('courses/enrollments');
    }

    public function trialAccess(): void
    {
        require_auth();
        require_permission('courses.enrollment');

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $featureAvailable = $this->courses->trialAccessFeatureAvailable();
        $trialResult = $featureAvailable
            ? $this->courses->listTrialAccesses($perPage, $page)
            : ['rows' => [], 'meta' => pagination_meta(0, $perPage, $page)];

        $publishedCourses = $this->courses->listCourses(['status' => 'published'], 1000, 1);

        $this->render('courses/trial_access', [
            'title' => 'Acesso de Degustacao',
            'featureAvailable' => $featureAvailable,
            'rows' => $trialResult['rows'],
            'meta' => $trialResult['meta'],
            'courses' => $publishedCourses['rows'],
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function storeTrialAccess(): void
    {
        require_auth();
        require_permission('courses.enrollment');
        csrf_validate();

        if (!$this->courses->trialAccessFeatureAvailable()) {
            $this->error('Funcionalidade de degustacao indisponivel no banco. Execute a migracao correspondente.');
            $this->redirect('courses/trial-access');
        }

        try {
            $created = $this->courses->createTrialAccess([
                'student_name' => trim((string) post('student_name')),
                'student_email' => trim((string) post('student_email')),
                'student_phone' => trim((string) post('student_phone')),
                'course_id' => (int) post('course_id'),
                'access_date' => trim((string) post('access_date')),
            ], (int) current_user()['id']);

            $this->audit->log([
                'module' => 'cursos.degustacao',
                'action' => 'create',
                'entity_type' => 'course_trial_access',
                'entity_id' => (int) ($created['id'] ?? 0),
                'entity_label' => (string) ($created['student_name'] ?? 'Aluno degustacao'),
                'description' => 'Acesso de degustacao criado para curso EAD.',
                'before' => [],
                'after' => [
                    'student_id' => (int) ($created['student_id'] ?? 0),
                    'student_name' => (string) ($created['student_name'] ?? ''),
                    'course_id' => (int) ($created['course_id'] ?? 0),
                    'course_name' => (string) ($created['course_name'] ?? ''),
                    'access_date' => (string) ($created['access_date'] ?? ''),
                    'portal_login' => (string) ($created['portal_login'] ?? ''),
                    'status' => 'active',
                ],
                'metadata' => [
                    'access_scope' => 'live_only',
                ],
            ]);

            $accessDate = trim((string) ($created['access_date'] ?? ''));
            $formattedDate = $accessDate !== '' ? date('d/m/Y', strtotime($accessDate)) : '-';

            $this->success(
                'Acesso de degustacao criado com sucesso. Login: '
                . (string) ($created['portal_login'] ?? '')
                . ' | Senha: '
                . (string) ($created['portal_password'] ?? '')
                . ' | Data liberada: '
                . $formattedDate
                . '.'
            );
        } catch (Throwable $e) {
            $this->error('Falha ao criar acesso de degustacao: ' . $e->getMessage());
        }

        $this->redirect('courses/trial-access');
    }

    public function revokeTrialAccess(): void
    {
        require_auth();
        require_permission('courses.enrollment');
        csrf_validate();

        if (!$this->courses->trialAccessFeatureAvailable()) {
            $this->error('Funcionalidade de degustacao indisponivel no banco.');
            $this->redirect('courses/trial-access');
        }

        $trialAccessId = (int) post('id');
        if ($trialAccessId <= 0) {
            $this->error('Acesso de degustacao invalido.');
            $this->redirect('courses/trial-access');
        }

        $before = $this->courses->findTrialAccess($trialAccessId);
        if (!$before) {
            $this->error('Acesso de degustacao nao encontrado.');
            $this->redirect('courses/trial-access');
        }

        if (!$this->courses->revokeTrialAccess($trialAccessId)) {
            $this->error('Nao foi possivel revogar o acesso de degustacao.');
            $this->redirect('courses/trial-access');
        }

        $after = $before;
        $after['status'] = 'revoked';

        $this->audit->log([
            'module' => 'cursos.degustacao',
            'action' => 'revoke',
            'entity_type' => 'course_trial_access',
            'entity_id' => $trialAccessId,
            'entity_label' => (string) ($before['student_name'] ?? 'Aluno degustacao'),
            'description' => 'Acesso de degustacao revogado.',
            'before' => [
                'status' => (string) ($before['status'] ?? 'active'),
            ],
            'after' => [
                'status' => 'revoked',
            ],
            'metadata' => [
                'course_id' => (int) ($before['course_id'] ?? 0),
                'course_name' => (string) ($before['course_name'] ?? ''),
                'access_date' => (string) ($before['access_date'] ?? ''),
                'portal_login' => (string) ($before['portal_login'] ?? ''),
            ],
        ]);

        $this->success('Acesso de degustacao revogado.');
        $this->redirect('courses/trial-access');
    }

    public function exams(): void
    {
        require_auth();
        require_permission('courses.exam');

        $filters = [
            'q' => trim((string) request('q', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->courses->listExams($filters, $perPage, $page);
        $courses = $this->courses->listCourses([], 1000, 1);
        $students = $this->students->list([], 1000, 1);
        $examScheduleEnabled = $this->courses->examScheduleFeatureAvailable();
        $externalExamFeatureAvailable = $this->courses->externalExamFeatureAvailable();
        $internalExamAudienceFeatureAvailable = $this->courses->internalExamAudienceFeatureAvailable();
        $upcomingExams = $this->courses->upcomingExamCalendar(90, 14);
        $externalLinks = $externalExamFeatureAvailable
            ? $this->courses->listExternalExamLinks(300)
            : [];

        $this->render('courses/exams', [
            'title' => 'Exames',
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'courses' => $courses['rows'],
            'students' => $students['rows'],
            'upcomingExams' => $upcomingExams,
            'examScheduleEnabled' => $examScheduleEnabled,
            'externalExamFeatureAvailable' => $externalExamFeatureAvailable,
            'internalExamAudienceFeatureAvailable' => $internalExamAudienceFeatureAvailable,
            'externalLinks' => $externalLinks,
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function storeExam(): void
    {
        require_auth();
        require_permission('courses.exam');
        csrf_validate();

        $data = [
            'course_id' => (int) post('course_id'),
            'title' => trim((string) post('title')),
            'description' => trim((string) post('description')),
            'passing_score' => parse_decimal((string) post('passing_score', '7')),
            'scheduled_at' => $this->normalizeDateTime((string) post('scheduled_at')),
        ];
        $deliveryScope = trim((string) post('delivery_scope_internal', 'course'));
        $targetStudentId = (int) post('target_student_id');
        $questionsPayload = $this->normalizeExamQuestionsFromPost();

        if ($data['course_id'] <= 0 || $data['title'] === '') {
            $this->error('Curso e titulo sao obrigatorios.');
            $this->redirect('courses/exams');
        }

        if (!in_array($deliveryScope, ['course', 'student'], true)) {
            $deliveryScope = 'course';
        }

        if ($deliveryScope === 'student') {
            if (!$this->courses->internalExamAudienceFeatureAvailable()) {
                $this->error('Direcionamento individual de prova interna indisponivel nesta base. Execute a migracao de publico interno de provas.');
                $this->redirect('courses/exams');
            }

            if ($targetStudentId <= 0) {
                $this->error('Selecione o aluno para o envio individual da prova interna.');
                $this->redirect('courses/exams');
            }

            if (!$this->courses->isStudentEnrolledInCourse($targetStudentId, $data['course_id'])) {
                $this->error('O aluno selecionado nao esta matriculado (ativo/concluido) no curso escolhido.');
                $this->redirect('courses/exams');
            }
        }

        if ($questionsPayload['rows'] === []) {
            $this->error('Adicione pelo menos uma questao para criar a prova interna.');
            $this->redirect('courses/exams');
        }

        if ($questionsPayload['errors'] !== []) {
            $this->error($questionsPayload['errors'][0]);
            $this->redirect('courses/exams');
        }

        $examId = $this->courses->createExam($data, (int) current_user()['id']);
        if ($examId <= 0) {
            $this->error('Curso invalido para esta empresa.');
            $this->redirect('courses/exams');
        }

        foreach ($questionsPayload['rows'] as $questionRow) {
            $this->courses->createQuestion(
                $examId,
                (string) $questionRow['question_type'],
                (string) $questionRow['question_text'],
                $questionRow['options_json'] !== null ? (string) $questionRow['options_json'] : null,
                $questionRow['correct_answer'] !== null ? (string) $questionRow['correct_answer'] : null,
                (int) current_user()['id']
            );
        }

        $audienceMessage = 'Prova interna criada.';
        if ($deliveryScope === 'student' && $targetStudentId > 0) {
            $linked = $this->courses->upsertInternalExamAudienceLink([
                'exam_id' => $examId,
                'student_id' => $targetStudentId,
            ], (int) current_user()['id']);
            $audienceMessage = $linked
                ? 'Prova interna criada e enviada para 1 aluno.'
                : 'Prova interna criada. Nao foi possivel aplicar o direcionamento individual.';
        } elseif ($this->courses->internalExamAudienceFeatureAvailable()) {
            $result = $this->courses->upsertInternalExamAudienceForExamCourse($examId, (int) current_user()['id']);
            $eligibleTotal = (int) ($result['eligible_total'] ?? 0);
            $linkedTotal = (int) ($result['linked_total'] ?? 0);
            if ($eligibleTotal > 0) {
                $audienceMessage = "Prova interna criada e enviada para {$linkedTotal} aluno(s) matriculado(s) no curso.";
            } else {
                $audienceMessage = 'Prova interna criada. Ainda nao ha alunos ativos/concluidos matriculados neste curso.';
            }
        }

        $this->success($audienceMessage);
        $this->redirect('courses/exams');
    }

    public function storeExamResult(): void
    {
        require_auth();
        require_permission('courses.exam');
        csrf_validate();

        $data = [
            'exam_id' => (int) post('exam_id'),
            'student_id' => (int) post('student_id'),
            'score' => parse_decimal((string) post('score', '0')),
            'passing_score' => parse_decimal((string) post('passing_score', '7')),
            'submitted_at' => trim((string) post('submitted_at')),
        ];

        if ($data['exam_id'] <= 0 || $data['student_id'] <= 0) {
            $this->error('Exame e aluno sao obrigatorios.');
            $this->redirect('courses/exams');
        }

        $this->courses->registerExamResult($data, (int) current_user()['id']);
        $this->success('Resultado registrado.');
        $this->redirect('courses/exams');
    }

    public function storeExternalExamLink(): void
    {
        require_auth();
        require_permission('courses.exam');
        csrf_validate();

        if (!$this->courses->externalExamFeatureAvailable()) {
            $this->error('Prova externa nao habilitada no banco. Execute a migracao correspondente.');
            $this->redirect('courses/exams');
        }

        $examId = (int) post('exam_id');
        $studentId = (int) post('student_id');
        $deliveryScope = trim((string) post('delivery_scope', 'student'));
        $externalUrl = trim((string) post('external_url'));
        $dueAt = $this->normalizeDateTime((string) post('due_at'));

        if ($examId <= 0 || $externalUrl === '') {
            $this->error('Exame e URL externa sao obrigatorios.');
            $this->redirect('courses/exams');
        }

        if (!$this->isHttpUrl($externalUrl)) {
            $this->error('Informe uma URL valida (http/https) para a prova externa.');
            $this->redirect('courses/exams');
        }

        $payload = [
            'exam_id' => $examId,
            'external_url' => $externalUrl,
            'instructions' => trim((string) post('instructions')),
            'due_at' => $dueAt,
        ];

        if ($deliveryScope === 'course') {
            $result = $this->courses->upsertExternalExamLinksForExamCourse($payload, (int) current_user()['id']);
            if (!$result['ok']) {
                $this->error('Nao foi possivel vincular a prova externa em massa. Verifique o exame e as matriculas do curso.');
                $this->redirect('courses/exams');
            }

            $eligibleTotal = (int) ($result['eligible_total'] ?? 0);
            $linkedTotal = (int) ($result['linked_total'] ?? 0);

            if ($eligibleTotal <= 0) {
                $this->error('Nenhum aluno ativo/concluido matriculado no curso desta prova.');
                $this->redirect('courses/exams');
            }

            $this->success("Prova externa vinculada para {$linkedTotal} aluno(s) do curso.");
            $this->redirect('courses/exams');
        }

        if ($studentId <= 0) {
            $this->error('Selecione um aluno ou escolha o envio para todos do curso.');
            $this->redirect('courses/exams');
        }

        $ok = $this->courses->upsertExternalExamLink($payload + [
            'student_id' => $studentId,
        ], (int) current_user()['id']);

        if ($ok) {
            $this->success('Vinculo de prova externa salvo para o aluno.');
        } else {
            $this->error('Nao foi possivel salvar o vinculo. Verifique se o aluno esta matriculado no curso da prova.');
        }

        $this->redirect('courses/exams');
    }

    public function deactivateExternalExamLink(): void
    {
        require_auth();
        require_permission('courses.exam');
        csrf_validate();

        if (!$this->courses->externalExamFeatureAvailable()) {
            $this->error('Prova externa nao habilitada no banco.');
            $this->redirect('courses/exams');
        }

        $linkId = (int) post('id');
        if ($linkId <= 0) {
            $this->error('Vinculo de prova externa invalido.');
            $this->redirect('courses/exams');
        }

        if ($this->courses->deactivateExternalExamLink($linkId)) {
            $this->success('Vinculo de prova externa desativado.');
        } else {
            $this->error('Nao foi possivel desativar o vinculo.');
        }

        $this->redirect('courses/exams');
    }

    public function comments(): void
    {
        require_auth();
        require_permission('courses.comment');

        $rows = $this->courses->listComments(200);

        $courses = $this->courses->listCourses([], 1000, 1);

        $this->render('courses/comments', [
            'title' => 'Gerenciar Comentarios',
            'rows' => $rows,
            'courses' => $courses['rows'],
        ]);
    }

    public function storeComment(): void
    {
        require_auth();
        require_permission('courses.comment');
        csrf_validate();

        $courseId = (int) post('course_id');
        $comment = trim((string) post('comment'));

        if ($courseId > 0 && $comment !== '') {
            $ok = $this->courses->createComment($courseId, $comment, (int) current_user()['id']);
            if ($ok) {
                $this->success('Comentario registrado.');
            } else {
                $this->error('Curso invalido para esta empresa.');
            }
        } else {
            $this->error('Curso e comentario sao obrigatorios.');
        }

        $this->redirect('courses/comments');
    }

    public function calendar(): void
    {
        require_auth();
        require_permission('courses');

        $fromDate = $this->normalizeDate((string) request('from'), date('Y-m-01'));
        $toDate = $this->normalizeDate((string) request('to'), date('Y-m-d', strtotime('+45 days')));
        $fromDateTime = $fromDate . ' 00:00:00';
        $toDateTime = $toDate . ' 23:59:59';
        $companyId = (int) (current_company_id() ?? 0);

        $automation = $this->calendar->processAutomaticReminders(45, $companyId);
        $events = $this->calendar->adminUnifiedEvents($fromDateTime, $toDateTime, $companyId);
        $activities = $this->calendar->listActivities($fromDateTime, $toDateTime, 120, $companyId);
        $recentReminders = $this->calendar->adminRecentReminders(25, $companyId);
        $courses = $this->calendar->listCoursesForActivities($companyId);

        $this->render('courses/calendar', [
            'title' => 'Agenda Academica',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'events' => $events,
            'activities' => $activities,
            'recentReminders' => $recentReminders,
            'courses' => $courses,
            'calendarFeatureAvailable' => $this->calendar->featureAvailable(),
            'automationSummary' => $automation,
        ]);
    }

    public function storeActivity(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        if (!$this->calendar->featureAvailable()) {
            $this->error('Agenda academica nao habilitada no banco. Execute a migracao correspondente.');
            $this->redirect('courses/calendar');
        }

        $data = [
            'course_id' => (int) post('course_id'),
            'title' => trim((string) post('title')),
            'description' => trim((string) post('description')),
            'due_datetime' => $this->normalizeDateTime((string) post('due_datetime')),
            'reminder_hours_before' => (int) post('reminder_hours_before', 24),
            'is_active' => 1,
        ];

        if ($data['course_id'] <= 0 || $data['title'] === '' || !$data['due_datetime']) {
            $this->error('Curso, titulo e prazo da atividade sao obrigatorios.');
            $this->redirect('courses/calendar');
        }

        if ($data['reminder_hours_before'] <= 0) {
            $data['reminder_hours_before'] = 24;
        }

        $companyId = (int) (current_company_id() ?? 0);
        $activityId = $this->calendar->createActivity($data, (int) current_user()['id'], $companyId);
        if ($activityId > 0) {
            $this->calendar->processAutomaticReminders(45, $companyId);
            $this->success('Atividade cadastrada e calendario atualizado.');
        } else {
            $this->error('Nao foi possivel cadastrar a atividade.');
        }

        $this->redirect('courses/calendar');
    }

    public function deleteActivity(): void
    {
        require_auth();
        require_permission('courses.edit');
        csrf_validate();

        $activityId = (int) post('activity_id');
        if ($activityId > 0) {
            $this->calendar->deleteActivity($activityId, (int) (current_company_id() ?? 0));
            $this->success('Atividade removida.');
        } else {
            $this->error('Atividade invalida.');
        }

        $this->redirect('courses/calendar');
    }

    private function normalizeDate(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $fallback;
        }

        return date('Y-m-d', $ts);
    }

    private function normalizeExamQuestionsFromPost(): array
    {
        $types = post('question_type');
        $texts = post('question_text');
        $optionsTexts = post('options_text');
        $answers = post('correct_answer');

        if (!is_array($types) || !is_array($texts)) {
            $legacyQuestion = trim((string) post('question_text'));
            if ($legacyQuestion === '') {
                return ['rows' => [], 'errors' => []];
            }

            $legacyType = trim((string) post('question_type', 'objective'));
            if (!in_array($legacyType, ['objective', 'essay'], true)) {
                $legacyType = 'objective';
            }

            $legacyOptionsText = trim((string) post('options_text'));
            $legacyCorrectAnswer = trim((string) post('correct_answer'));
            $legacyOptions = [];
            if ($legacyOptionsText !== '') {
                $lines = preg_split('/\r\n|\r|\n/', $legacyOptionsText) ?: [];
                $legacyOptions = array_values(array_filter(array_map('trim', $lines), fn ($item) => $item !== ''));
            }

            if ($legacyType === 'objective') {
                if (count($legacyOptions) < 2) {
                    return ['rows' => [], 'errors' => ['Cada questao objetiva precisa de pelo menos duas opcoes.']];
                }
                if ($legacyCorrectAnswer === '') {
                    return ['rows' => [], 'errors' => ['Informe a resposta correta de cada questao objetiva.']];
                }
            }

            return [
                'rows' => [[
                    'question_type' => $legacyType,
                    'question_text' => $legacyQuestion,
                    'options_json' => $legacyType === 'objective' ? json_encode($legacyOptions, JSON_UNESCAPED_UNICODE) : null,
                    'correct_answer' => $legacyCorrectAnswer !== '' ? $legacyCorrectAnswer : null,
                ]],
                'errors' => [],
            ];
        }

        $rows = [];
        $errors = [];

        foreach ($texts as $index => $rawText) {
            $questionText = trim((string) $rawText);
            if ($questionText === '') {
                continue;
            }

            $questionType = trim((string) ($types[$index] ?? 'objective'));
            if (!in_array($questionType, ['objective', 'essay'], true)) {
                $questionType = 'objective';
            }

            $optionsText = trim((string) ($optionsTexts[$index] ?? ''));
            $correctAnswer = trim((string) ($answers[$index] ?? ''));
            $options = [];
            if ($optionsText !== '') {
                $lines = preg_split('/\r\n|\r|\n/', $optionsText) ?: [];
                $options = array_values(array_filter(array_map('trim', $lines), fn ($item) => $item !== ''));
            }

            if ($questionType === 'objective') {
                if (count($options) < 2) {
                    $errors[] = 'Cada questao objetiva precisa de pelo menos duas opcoes.';
                    continue;
                }
                if ($correctAnswer === '') {
                    $errors[] = 'Informe a resposta correta de cada questao objetiva.';
                    continue;
                }
            }

            $rows[] = [
                'question_type' => $questionType,
                'question_text' => $questionText,
                'options_json' => $questionType === 'objective'
                    ? json_encode($options, JSON_UNESCAPED_UNICODE)
                    : null,
                'correct_answer' => $correctAnswer !== '' ? $correctAnswer : null,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            $normalized .= ':00';
        }

        $ts = strtotime($normalized);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function isHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function collectFormData(): array
    {
        $cover = trim((string) post('cover_image'));

        if (!empty($_FILES['cover_file']['name']) && ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string) $_FILES['cover_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $targetDir = __DIR__ . '/../uploads/courses';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }
                $fileName = 'course_' . date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $_FILES['cover_file']['name']);
                if (move_uploaded_file((string) $_FILES['cover_file']['tmp_name'], $targetDir . '/' . $fileName)) {
                    $cover = 'uploads/courses/' . $fileName;
                }
            }
        }

        return [
            'name' => trim((string) post('name')),
            'description' => trim((string) post('description')),
            'category_id' => post('category_id'),
            'cover_image' => $cover,
            'status' => trim((string) post('status', 'draft')),
            'workload_hours' => trim((string) post('workload_hours')),
            'curriculum' => trim((string) post('curriculum')),
            'materials' => trim((string) post('materials')),
            'live_link' => trim((string) post('live_link')),
            'live_password' => trim((string) post('live_password')),
            'live_meeting_id' => trim((string) post('live_meeting_id')),
            'live_datetime' => trim((string) post('live_datetime')),
        ];
    }

    private function handleMaterialUpload(int $courseId, $fileBag): int
    {
        if (!$fileBag || !isset($fileBag['name'])) {
            return 0;
        }

        $targetDir = __DIR__ . '/../uploads/course_materials';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'mp4'];
        $uploaded = 0;

        $isMulti = is_array($fileBag['name']);
        $names = $isMulti ? $fileBag['name'] : [$fileBag['name']];
        $tmpNames = $isMulti ? $fileBag['tmp_name'] : [$fileBag['tmp_name']];
        $errors = $isMulti ? $fileBag['error'] : [$fileBag['error']];
        $sizes = $isMulti ? $fileBag['size'] : [$fileBag['size']];

        foreach ($names as $idx => $name) {
            $name = (string) $name;
            if ($name === '') {
                continue;
            }

            $errorCode = (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true)) {
                continue;
            }

            $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $fileName = 'course_material_' . $courseId . '_' . date('YmdHis') . '_' . $idx . '_' . $safeOriginal;
            $finalPath = $targetDir . '/' . $fileName;

            if (!move_uploaded_file((string) ($tmpNames[$idx] ?? ''), $finalPath)) {
                continue;
            }

            $this->courses->addCourseMaterial(
                $courseId,
                $name,
                'uploads/course_materials/' . $fileName,
                $extension,
                (int) ($sizes[$idx] ?? 0),
                (int) current_user()['id']
            );

            $uploaded++;
        }

        return $uploaded;
    }

    private function safeRemoveCourseMaterialFile(string $relativePath): void
    {
        $uploadsBase = realpath(__DIR__ . '/../uploads');
        if (!$uploadsBase) {
            return;
        }

        $fullPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
        if (!$fullPath) {
            return;
        }

        if (!str_starts_with($fullPath, $uploadsBase)) {
            return;
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
