<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[1] ?? '';
if ($root === '' || !is_file($root . '/core/bootstrap.php')) {
    fwrite(STDERR, "Raiz HML invalida.\n");
    exit(1);
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = 'on';
require $root . '/core/bootstrap.php';

$db = $GLOBALS['db'];
$marker = 'QA-DUVIDA-' . date('YmdHis');
$questionId = 0;
$notificationId = 0;

try {
    $db->exec("DELETE FROM student_portal_notifications
        WHERE notification_type = 'course_question_answered'
          AND message LIKE 'QA-DUVIDA-%'");
    $db->exec("DELETE FROM course_questions WHERE subject LIKE 'QA-DUVIDA-%'");

    $enrollment = $db->query(
        "SELECT
            s.id AS student_id,
            s.company_id,
            s.full_name,
            c.id AS course_id,
            c.name AS course_name,
            (
                SELECT cl.id
                FROM course_lessons cl
                WHERE cl.course_id = c.id
                  AND cl.is_active = 1
                ORDER BY cl.display_order, cl.id
                LIMIT 1
            ) AS lesson_id
         FROM student_portal_accounts spa
         INNER JOIN students s ON s.id = spa.student_id
         INNER JOIN enrollments e ON e.student_id = s.id AND e.status IN ('active', 'completed')
         INNER JOIN courses c ON c.id = e.course_id AND c.company_id = s.company_id
         WHERE spa.login = 'qa.aluno.portal'
         ORDER BY e.id
         LIMIT 1"
    )->fetch();
    if (!$enrollment) {
        throw new RuntimeException('Aluno QA sem matricula ativa para o teste.');
    }

    $professorStmt = $db->prepare(
        "SELECT u.*
         FROM users u
         INNER JOIN user_companies uc ON uc.user_id = u.id
         WHERE u.role = 'professor'
           AND u.is_active = 1
           AND uc.company_id = :company_id
         ORDER BY u.id
         LIMIT 1"
    );
    $professorStmt->execute([':company_id' => (int) $enrollment['company_id']]);
    $professor = $professorStmt->fetch();
    if (!$professor) {
        throw new RuntimeException('Nenhum professor ativo vinculado a empresa do aluno QA.');
    }

    $model = new CourseQuestionModel();
    $questionId = $model->createFromStudent(
        (int) $enrollment['company_id'],
        (int) $enrollment['student_id'],
        (int) $enrollment['course_id'],
        !empty($enrollment['lesson_id']) ? (int) $enrollment['lesson_id'] : null,
        $marker,
        'Pergunta controlada para validar o fluxo de homologacao.'
    );
    if ($questionId <= 0 || $model->countOpenForCompany((int) $enrollment['company_id']) <= 0) {
        throw new RuntimeException('A pergunta nao entrou na fila do professor.');
    }

    $reply = $model->replyAsProfessor(
        $questionId,
        (int) $enrollment['company_id'],
        (int) $professor['id'],
        'Resposta controlada do professor no HML.'
    );
    if (!$reply) {
        throw new RuntimeException('A resposta do professor nao foi registrada.');
    }

    $portal = new StudentPortalModel();
    $notificationId = $portal->createPortalNotification([
        'company_id' => (int) $enrollment['company_id'],
        'student_id' => (int) $enrollment['student_id'],
        'notification_type' => 'course_question_answered',
        'title' => 'Sua duvida foi respondida',
        'message' => $marker . ' | resposta validada',
        'link_url' => route('student/questions'),
        'meta' => ['question_id' => $questionId, 'qa_marker' => $marker],
    ]);
    if ($notificationId <= 0) {
        throw new RuntimeException('A notificacao do portal nao foi criada.');
    }

    $studentRows = $model->listForStudent((int) $enrollment['student_id']);
    $professorRows = $model->listForProfessor((int) $enrollment['company_id']);
    if (!array_filter($studentRows, static fn (array $row): bool => (int) $row['id'] === $questionId)) {
        throw new RuntimeException('A pergunta nao apareceu no historico do aluno.');
    }
    if (!array_filter($professorRows, static fn (array $row): bool => (int) $row['id'] === $questionId)) {
        throw new RuntimeException('A pergunta nao apareceu na fila do professor.');
    }

    $liveModel = new CourseLiveSessionModel();
    $liveModel->list((int) $enrollment['company_id'], ['course_id' => 0, 'status' => ''], 20, 1);
    $liveModel->listCourseOptions((int) $enrollment['company_id']);
    $liveModel->getZoomCredentials((int) $enrollment['company_id']);

    echo 'question_flow=ok' . PHP_EOL;
    echo 'portal_notification=ok' . PHP_EOL;
    echo 'professor_queue=ok' . PHP_EOL;
    echo 'zoom_data=ok' . PHP_EOL;
    echo 'student=' . $enrollment['full_name'] . PHP_EOL;
    echo 'course=' . $enrollment['course_name'] . PHP_EOL;
} finally {
    if ($notificationId > 0) {
        $stmt = $db->prepare('DELETE FROM student_portal_notifications WHERE id = :id');
        $stmt->execute([':id' => $notificationId]);
    }
    if ($questionId > 0) {
        $stmt = $db->prepare('DELETE FROM course_questions WHERE id = :id');
        $stmt->execute([':id' => $questionId]);
    }
}
