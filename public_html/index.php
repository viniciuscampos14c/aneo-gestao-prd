<?php

if (ob_get_level() === 0) {
    ob_start();
}

require __DIR__ . '/core/bootstrap.php';

$router = new Router();

$auth = new AuthController();
$dashboard = new DashboardController();
$students = new StudentController();
$kanban = new KanbanController();
$leads = new LeadController();
$finance = new FinanceController();
$courses = new CourseController();
$generic = new GenericModuleController();
$requests = new RequestsController();
$search = new SearchController();
$users = new UserController();
$companies = new CompanyController();
$license = new LicenseController();
$systemLogs = new SystemLogController();
$chatwoot = new ChatwootController();
$chatwootWebhook = new ChatwootWebhookController();
$signatures = new SignatureController();
$automationWebhook = new AutomationWebhookController();
$financeAutomation = new FinanceAutomationController();
$adminAi = new AdminAiController();
$studentAuth = new StudentAuthController();
$studentPortal = new StudentPortalController();
$arsenal = new ArsenalController();
$apiMgmt = new ApiManagementController();
$cron    = new CronController();

$router->get('', fn () => redirect('dashboard'));
$router->get('login', fn () => $auth->showLogin());
$router->post('login', fn () => $auth->login());
$router->get('select-company', fn () => $auth->selectCompany());
$router->post('set-company', fn () => $auth->setCompany());
$router->get('logout', fn () => $auth->logout());

$router->get('student', fn () => $studentPortal->home());
$router->get('student/login', fn () => $studentAuth->showLogin());
$router->post('student/login', fn () => $studentAuth->login());
$router->get('student/logout', fn () => $studentAuth->logout());
$router->get('student/dashboard', fn () => $studentPortal->dashboard());
$router->get('student/courses', fn () => $studentPortal->courses());
$router->get('student/course', fn () => $studentPortal->course());
$router->get('student/calendar', fn () => $studentPortal->calendar());
$router->get('student/live', fn () => $studentPortal->live());
$router->get('student/materials', fn () => $studentPortal->materials());
$router->get('student/arsenal', fn () => $studentPortal->arsenal());
$router->get('student/arsenal/open', fn () => $studentPortal->arsenalOpen());
$router->get('student/requests', fn () => $studentPortal->requests());
$router->post('student/requests/store', fn () => $studentPortal->storeRequest());
$router->get('student/progress', fn () => $studentPortal->progress());
$router->post('student/course/progress', fn () => $studentPortal->lessonProgress());
$router->get('student/exams', fn () => $studentPortal->exams());
$router->get('student/academic-history', fn () => $studentPortal->academicHistory());
$router->get('student/finances', fn () => $studentPortal->finances());
$router->get('student/exams/external', fn () => $studentPortal->openExternalExam());
$router->get('student/exams/take', fn () => $studentPortal->takeExam());
$router->post('student/exams/submit', fn () => $studentPortal->submitExam());

$router->get('dashboard', fn () => $dashboard->index());
$router->get('search', fn () => $search->index());

$router->get('users', fn () => $users->index());
$router->get('users/create', fn () => $users->create());
$router->post('users/store', fn () => $users->store());
$router->get('users/edit', fn () => $users->edit());
$router->post('users/update', fn () => $users->update());
$router->post('users/toggle', fn () => $users->toggle());
$router->post('users/delete', fn () => $users->delete());

$router->get('companies', fn () => $companies->index());
$router->get('companies/smtp', fn () => $companies->smtp());
$router->get('companies/license', fn () => $license->index());
$router->post('companies/store', fn () => $companies->store());
$router->post('companies/update', fn () => $companies->update());
$router->post('companies/toggle', fn () => $companies->toggle());
$router->post('companies/integrations/update', fn () => $companies->updateIntegrations());
$router->post('companies/smtp/save', fn () => $companies->saveSmtp());
$router->post('companies/smtp/test', fn () => $companies->testSmtp());
$router->post('companies/license/activate', fn () => $license->activate());
$router->get('system/logs', fn () => $systemLogs->index());

$router->get('ai-chat', fn () => $adminAi->index());
$router->post('ai-chat/session', fn () => $adminAi->createSession());
$router->post('ai-chat/ask', fn () => $adminAi->ask());

$router->get('students', fn () => $students->index());
$router->get('students/create', fn () => $students->create());
$router->post('students/store', fn () => $students->store());
$router->get('students/show', fn () => $students->show());
$router->get('students/edit', fn () => $students->edit());
$router->post('students/update', fn () => $students->update());
$router->post('students/delete', fn () => $students->delete());
$router->post('students/toggle', fn () => $students->toggle());
$router->post('students/bulk', fn () => $students->bulk());
$router->post('students/import', fn () => $students->importCsv());
$router->get('students/export', fn () => $students->exportCsv());
$router->post('students/upload-document', fn () => $students->uploadDocument());

$router->get('kanban', fn () => $kanban->index());
$router->post('kanban/move', fn () => $kanban->move());
$router->get('kanban/settings', fn () => $kanban->settings());
$router->post('kanban/status/store', fn () => $kanban->storeStatus());
$router->post('kanban/status/update', fn () => $kanban->updateStatus());
$router->post('kanban/status/delete', fn () => $kanban->deleteStatus());

$router->get('leads', fn () => $leads->index());
$router->get('leads/create', fn () => $leads->create());
$router->post('leads/store', fn () => $leads->store());
$router->get('leads/edit', fn () => $leads->edit());
$router->post('leads/update', fn () => $leads->update());
$router->post('leads/delete', fn () => $leads->delete());
$router->post('leads/set-status', fn () => $leads->setStatus());
$router->post('leads/bulk', fn () => $leads->bulk());
$router->post('leads/history/store', fn () => $leads->addHistory());
$router->post('leads/convert', fn () => $leads->convert());
$router->get('leads/export', fn () => $leads->exportCsv());
$router->get('leads/settings', fn () => $leads->statusSettings());
$router->post('leads/status/store', fn () => $leads->storeStatus());
$router->post('leads/status/update', fn () => $leads->updateStatusConfig());
$router->post('leads/status/delete', fn () => $leads->deleteStatusConfig());

$router->get('finance/invoices', fn () => $finance->invoices());
$router->get('finance/invoices/create', fn () => $finance->createInvoice());
$router->post('finance/invoices/store', fn () => $finance->storeInvoice());
$router->post('finance/invoices/boleto-generate', fn () => $finance->generateBankSlip());
$router->post('finance/invoices/boleto-sync', fn () => $finance->syncBankSlip());
$router->post('finance/invoices/settle', fn () => $finance->settleInvoice());
$router->post('finance/invoices/fiscal-generate', fn () => $finance->generateFiscalInvoice());
$router->post('finance/invoices/delete', fn () => $finance->deleteInvoice());
$router->get('finance/invoices/export', fn () => $finance->exportInvoices());
$router->post('finance/invoices/recurring', fn () => $finance->generateRecurring());
$router->get('finance/reports', fn () => $finance->reports());
$router->get('finance/reports/export', fn () => $finance->exportReports());

$router->get('finance/payments', fn () => $finance->payments());
$router->post('finance/payments/store', fn () => $finance->storePayment());

$router->get('chatwoot', fn () => $chatwoot->index());
$router->post('chatwoot/open-student', fn () => $chatwoot->openStudent());
$router->post('chatwoot/open-lead', fn () => $chatwoot->openLead());
$router->post('chatwoot/open-phone', fn () => $chatwoot->openPhone());
$router->post('chatwoot/webhook', fn () => $chatwootWebhook->receive());

$router->get('signatures', fn () => $signatures->index());
$router->post('signatures/store', fn () => $signatures->store());
$router->post('signatures/send', fn () => $signatures->send());
$router->post('signatures/sync', fn () => $signatures->sync());
$router->post('signatures/delete', fn () => $signatures->delete());
$router->post('signatures/webhook', fn () => $signatures->webhook());
$router->post('automations/webhook/enrollment', fn () => $automationWebhook->enrollment());
$router->get('automations/webhook/finance-notifications', fn () => $financeAutomation->billingNotifications());
$router->post('automations/webhook/finance-notifications', fn () => $financeAutomation->billingNotifications());

$router->get('arsenal', fn () => $arsenal->index());
$router->post('arsenal/item/store', fn () => $arsenal->storeItem());
$router->post('arsenal/item/update', fn () => $arsenal->updateItem());
$router->post('arsenal/item/delete', fn () => $arsenal->deleteItem());
$router->post('arsenal/category/store', fn () => $arsenal->storeCategory());
$router->post('arsenal/category/update', fn () => $arsenal->updateCategory());
$router->post('arsenal/category/delete', fn () => $arsenal->deleteCategory());
$router->post('arsenal/bind/course', fn () => $arsenal->bindCourse());
$router->post('arsenal/unbind/course', fn () => $arsenal->unbindCourse());
$router->post('arsenal/bind/student', fn () => $arsenal->bindStudent());
$router->post('arsenal/unbind/student', fn () => $arsenal->unbindStudent());
$router->get('arsenal/download', fn () => $arsenal->download());

$router->get('courses', fn () => $courses->index());
$router->get('courses/create', fn () => $courses->create());
$router->post('courses/store', fn () => $courses->store());
$router->get('courses/edit', fn () => $courses->edit());
$router->post('courses/update', fn () => $courses->update());
$router->post('courses/delete', fn () => $courses->delete());
$router->post('courses/materials/upload', fn () => $courses->uploadMaterial());
$router->post('courses/materials/delete', fn () => $courses->deleteMaterial());
$router->post('courses/modules/store', fn () => $courses->storeModule());
$router->post('courses/modules/update', fn () => $courses->updateModule());
$router->post('courses/modules/delete', fn () => $courses->deleteModule());
$router->post('courses/lessons/store', fn () => $courses->storeLesson());
$router->post('courses/lessons/update', fn () => $courses->updateLesson());
$router->post('courses/lessons/delete', fn () => $courses->deleteLesson());

$router->get('courses/categories', fn () => $courses->categories());
$router->post('courses/categories/store', fn () => $courses->storeCategory());
$router->post('courses/categories/delete', fn () => $courses->deleteCategory());

$router->get('courses/enrollments', fn () => $courses->enrollments());
$router->post('courses/enrollments/store', fn () => $courses->storeEnrollment());
$router->get('courses/trial-access', fn () => $courses->trialAccess());
$router->post('courses/trial-access/store', fn () => $courses->storeTrialAccess());
$router->post('courses/trial-access/revoke', fn () => $courses->revokeTrialAccess());

$router->get('courses/calendar', fn () => $courses->calendar());
$router->post('courses/activities/store', fn () => $courses->storeActivity());
$router->post('courses/activities/delete', fn () => $courses->deleteActivity());

$router->get('courses/exams', fn () => $courses->exams());
$router->post('courses/exams/store', fn () => $courses->storeExam());
$router->post('courses/exams/result', fn () => $courses->storeExamResult());
$router->post('courses/exams/external-link/store', fn () => $courses->storeExternalExamLink());
$router->post('courses/exams/external-link/deactivate', fn () => $courses->deactivateExternalExamLink());

$router->get('courses/comments', fn () => $courses->comments());
$router->post('courses/comments/store', fn () => $courses->storeComment());

// Modulos desativados por regra de negocio atual (nao aparecem no menu e nao devem abrir por URL direta).
// $router->get('projects', fn () => $generic->index('projects', 'projects', 'Projetos'));
// $router->post('projects/store', fn () => $generic->store('projects', 'projects', 'Projetos'));
// $router->post('projects/update', fn () => $generic->update('projects', 'projects'));
// $router->post('projects/delete', fn () => $generic->delete('projects', 'projects'));
//
// $router->get('tasks', fn () => $generic->index('tasks', 'tasks', 'Tarefas'));
// $router->post('tasks/store', fn () => $generic->store('tasks', 'tasks', 'Tarefas'));
// $router->post('tasks/update', fn () => $generic->update('tasks', 'tasks'));
// $router->post('tasks/delete', fn () => $generic->delete('tasks', 'tasks'));

$router->get('requests', fn () => $requests->index());
$router->post('requests/store', fn () => $requests->store());
$router->post('requests/comment', fn () => $requests->addComment());
$router->post('requests/status', fn () => $requests->updateStatus());
$router->post('requests/webhook', fn () => $requests->webhook());

$router->get('automations', fn () => $generic->index('automations', 'automations', 'Automações'));
$router->post('automations/store', fn () => $generic->store('automations', 'automations', 'Automações'));
$router->post('automations/update', fn () => $generic->update('automations', 'automations'));
$router->post('automations/delete', fn () => $generic->delete('automations', 'automations'));

$router->get('help', fn () => $generic->index('help', 'help', 'Chat IA Jully'));
$router->post('help/store', fn () => $generic->store('help', 'help', 'Chat IA Jully'));
$router->post('help/update', fn () => $generic->update('help', 'help'));
$router->post('help/delete', fn () => $generic->delete('help', 'help'));

// Cron Jobs (admin)
$router->get('cron',         fn () => $cron->index());
$router->post('cron/run',    fn () => $cron->runJob());
$router->get('cron/logs',    fn () => $cron->logs());
$router->post('cron/toggle', fn () => $cron->toggle());

// API Management (apenas admin)
$router->get('api-management',         fn () => $apiMgmt->index());
$router->get('api-management/create',  fn () => $apiMgmt->create());
$router->post('api-management/store',  fn () => $apiMgmt->store());
$router->get('api-management/edit',    fn () => $apiMgmt->edit((int) request('id')));
$router->post('api-management/update', fn () => $apiMgmt->update((int) request('id')));
$router->post('api-management/destroy',fn () => $apiMgmt->destroy((int) request('id')));
$router->get('api-management/manual',  fn () => $apiMgmt->manual());

$route = parse_route();
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $route);
