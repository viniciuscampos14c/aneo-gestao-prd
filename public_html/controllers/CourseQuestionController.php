<?php

class CourseQuestionController extends BaseController
{
    private CourseQuestionModel $questions;
    private StudentPortalModel $portal;

    public function __construct()
    {
        $this->questions = new CourseQuestionModel();
        $this->portal = new StudentPortalModel();
    }

    public function studentIndex(): void
    {
        require_student_auth();
        $student = current_student();
        $rows = $this->questions->listForStudent((int) ($student['id'] ?? 0));

        $this->render('student_portal/questions', [
            'title' => 'Minhas Dúvidas',
            'student' => $student,
            'rows' => $rows,
            'featureAvailable' => $this->questions->featureAvailable(),
        ], 'layouts/student');
    }

    public function studentStore(): void
    {
        require_student_auth();
        csrf_validate();

        $student = current_student();
        try {
            $questionId = $this->questions->createFromStudent(
                (int) ($student['company_id'] ?? 0),
                (int) ($student['id'] ?? 0),
                (int) post('course_id'),
                (int) post('lesson_id') > 0 ? (int) post('lesson_id') : null,
                (string) post('subject'),
                (string) post('message')
            );
            $this->success('Dúvida enviada ao professor. Protocolo #' . $questionId . '.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        $returnCourseId = (int) post('course_id');
        if ($returnCourseId > 0) {
            $this->redirect('student/course&course_id=' . $returnCourseId);
        }
        $this->redirect('student/questions');
    }

    public function professorIndex(): void
    {
        $this->requireProfessor();
        $status = trim((string) request('status', ''));

        $this->render('courses/questions', [
            'title' => 'Dúvidas dos Alunos',
            'rows' => $this->questions->listForProfessor((int) current_company_id(), $status),
            'status' => $status,
            'featureAvailable' => $this->questions->featureAvailable(),
        ]);
    }

    public function professorReply(): void
    {
        $this->requireProfessor();
        csrf_validate();

        $questionId = (int) post('question_id');
        $reply = trim((string) post('message'));
        if ($questionId <= 0 || $reply === '') {
            $this->error('Informe uma resposta para a dúvida.');
            $this->redirect('courses/questions');
        }

        try {
            $question = $this->questions->replyAsProfessor(
                $questionId,
                (int) current_company_id(),
                (int) (current_user()['id'] ?? 0),
                $reply
            );
            if (!$question) {
                throw new RuntimeException('Dúvida não encontrada para esta empresa.');
            }

            $this->portal->createPortalNotification([
                'company_id' => (int) ($question['company_id'] ?? 0),
                'student_id' => (int) ($question['student_id'] ?? 0),
                'notification_type' => 'course_question_answered',
                'title' => 'Sua dúvida foi respondida',
                'message' => 'O professor respondeu "' . (string) ($question['subject'] ?? 'sua dúvida') . '": ' . (string) ($question['reply_excerpt'] ?? ''),
                'link_url' => route('student/questions'),
                'meta' => ['question_id' => $questionId],
            ]);
            $this->success('Resposta enviada e aluno notificado no portal.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        $this->redirect('courses/questions');
    }

    public function professorResolve(): void
    {
        $this->requireProfessor();
        csrf_validate();

        if ($this->questions->resolve((int) post('question_id'), (int) current_company_id())) {
            $this->success('Dúvida marcada como resolvida.');
        } else {
            $this->error('Não foi possível resolver a dúvida.');
        }
        $this->redirect('courses/questions');
    }

    private function requireProfessor(): void
    {
        require_auth();
        if (!is_professor()) {
            $this->error('Esta area e exclusiva do perfil professor.');
            $this->redirect(default_admin_route());
        }
        require_permission('courses');
    }
}
