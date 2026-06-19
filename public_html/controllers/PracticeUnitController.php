<?php

class PracticeUnitController extends BaseController
{
    private StudentScheduleModel $units;

    public function __construct()
    {
        $this->units = new StudentScheduleModel();
    }

    public function index(): void
    {
        $this->requireManageAccess();

        $editingId = (int) request('edit', 0);
        $editing = $editingId > 0 ? $this->units->findUnit($editingId) : null;

        if ($editingId > 0 && !$editing) {
            $this->error('Unidade não encontrada.');
            $this->redirect('practice-units');
            return;
        }

        $this->render('practice_units/index', [
            'title' => 'Unidades / Hospitais',
            'featureAvailable' => $this->units->unitsFeatureAvailable(),
            'rows' => $this->units->unitsFeatureAvailable() ? $this->units->listUnits() : [],
            'editing' => $editing,
        ]);
    }

    public function store(): void
    {
        $this->requireManageAccess();
        csrf_validate();

        $this->ensureUnitsAvailable();
        $payload = $this->collectPayload();
        if ($payload['error'] !== null) {
            $this->error($payload['error']);
            $this->redirect('practice-units');
        }

        $this->units->createUnit($payload['data'], (int) current_user()['id']);
        $this->success('Unidade cadastrada.');
        $this->redirect('practice-units');
    }

    public function update(): void
    {
        $this->requireManageAccess();
        csrf_validate();

        $this->ensureUnitsAvailable();

        $id = (int) post('id');
        if ($id <= 0 || !$this->units->findUnit($id)) {
            $this->error('Unidade não encontrada.');
            $this->redirect('practice-units');
        }

        $payload = $this->collectPayload();
        if ($payload['error'] !== null) {
            $this->error($payload['error']);
            $this->redirect('practice-units&edit=' . $id);
        }

        $this->units->updateUnit($id, $payload['data']);
        $this->success('Unidade atualizada.');
        $this->redirect('practice-units');
    }

    public function toggle(): void
    {
        $this->requireManageAccess();
        csrf_validate();

        $this->ensureUnitsAvailable();

        $id = (int) post('id');
        $unit = $this->units->findUnit($id);
        if ($id <= 0 || !$unit) {
            $this->error('Unidade não encontrada.');
            $this->redirect('practice-units');
        }

        $this->units->toggleUnit($id, (int) post('active', 1));
        $this->success('Status da unidade atualizado.');
        $this->redirect('practice-units');
    }

    private function requireManageAccess(): void
    {
        require_auth();
        require_permission('student_schedule.manage');

        if (is_professor()) {
            $this->error('Perfil professor possui acesso somente de visualizacao nesta area.');
            $this->redirect('escala-aluno');
        }
    }

    private function ensureUnitsAvailable(): void
    {
        if ($this->units->unitsFeatureAvailable()) {
            return;
        }

        $this->error('Módulo de unidades/hospitais indisponivel no banco. Execute a migration 20260505_student_duty_schedule.sql.');
        $this->redirect('practice-units');
    }

    private function collectPayload(): array
    {
        $data = [
            'name' => trim((string) post('name')),
            'city' => trim((string) post('city')),
            'state' => trim((string) post('state')),
        ];

        if ($data['name'] === '') {
            return [
                'error' => 'Informe o nome da unidade/hospital.',
                'data' => $data,
            ];
        }

        return [
            'error' => null,
            'data' => $data,
        ];
    }
}
