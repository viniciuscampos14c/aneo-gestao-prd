<?php

class SystemModuleController extends BaseController
{
    private SystemModuleModel $modules;

    public function __construct()
    {
        $this->modules = new SystemModuleModel();
    }

    public function index(): void
    {
        require_admin();
        $this->modules->ensureSchema();
        $this->ensureRuntimeDirectories();

        $selectedId = (int) request('id', 0);
        $selectedModule = $selectedId > 0 ? $this->modules->findModule($selectedId) : null;

        $this->render('system_modules/index', [
            'title' => 'Modulos do Sistema',
            'modules' => $this->modules->listModules(),
            'selectedModule' => $selectedModule,
            'stats' => $this->modules->stats(),
            'logs' => $this->modules->listLogs($selectedModule ? (int) $selectedModule['id'] : null, 60),
            'migrations' => $this->modules->listMigrations($selectedModule ? (int) $selectedModule['id'] : null),
            'zipAvailable' => class_exists('ZipArchive'),
            'modulesPath' => 'public_html/modules',
            'packagesPath' => 'public_html/uploads/module_packages',
            'coreVersion' => $this->coreVersion(),
        ]);
    }

    public function upload(): void
    {
        require_admin();
        require_permission('system_modules.install');
        csrf_validate();
        $this->modules->ensureSchema();
        $this->ensureRuntimeDirectories();

        if (!class_exists('ZipArchive')) {
            $this->error('A extensao ZIP do PHP nao esta habilitada neste servidor.');
            $this->redirect('system-modules');
        }

        $file = $_FILES['module_zip'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->error('Selecione um pacote ZIP valido para instalar.');
            $this->redirect('system-modules');
        }

        $originalName = (string) ($file['name'] ?? 'modulo.zip');
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            $this->error('O instalador deve ser enviado no formato ZIP.');
            $this->redirect('system-modules');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $this->error('Nao foi possivel validar o arquivo enviado.');
            $this->redirect('system-modules');
        }

        $hash = hash_file('sha256', $tmpPath);
        $packageName = date('Ymd_His') . '_' . $this->safeFilename($originalName);
        $packagePath = $this->packagesRoot() . DIRECTORY_SEPARATOR . $packageName;

        if (!move_uploaded_file($tmpPath, $packagePath)) {
            $this->error('Nao foi possivel salvar o pacote enviado.');
            $this->redirect('system-modules');
        }

        try {
            $result = $this->installPackage($packagePath, $originalName, $hash);
            $this->success('Modulo instalado com sucesso. Ele ficou inativo ate voce ativar manualmente.');
            $this->redirect('system-modules&id=' . (int) $result['module_id']);
        } catch (Throwable $e) {
            $this->modules->log(null, null, 'install_failed', 'error', $e->getMessage(), [
                'package' => $originalName,
                'hash' => $hash,
            ]);
            $this->error('Falha ao instalar modulo: ' . $e->getMessage());
            $this->redirect('system-modules');
        }
    }

    public function activate(): void
    {
        require_admin();
        require_permission('system_modules.manage');
        csrf_validate();
        $this->modules->ensureSchema();

        $moduleId = (int) post('module_id', 0);
        $module = $this->modules->findModule($moduleId);
        if (!$module) {
            $this->error('Modulo nao encontrado.');
            $this->redirect('system-modules');
        }

        if ((string) ($module['status'] ?? '') === 'error') {
            $this->error('Este modulo esta com erro de instalacao. Reinstale uma versao corrigida antes de ativar.');
            $this->redirect('system-modules&id=' . $moduleId);
        }

        $this->modules->setStatus($moduleId, 'active');
        $this->modules->log($moduleId, (string) $module['module_key'], 'activated', 'info', 'Modulo ativado pelo administrador.');
        $this->success('Modulo ativado.');
        $this->redirect('system-modules&id=' . $moduleId);
    }

    public function deactivate(): void
    {
        require_admin();
        require_permission('system_modules.manage');
        csrf_validate();
        $this->modules->ensureSchema();

        $moduleId = (int) post('module_id', 0);
        $module = $this->modules->findModule($moduleId);
        if (!$module) {
            $this->error('Modulo nao encontrado.');
            $this->redirect('system-modules');
        }

        $this->modules->setStatus($moduleId, 'inactive');
        $this->modules->log($moduleId, (string) $module['module_key'], 'deactivated', 'info', 'Modulo desativado pelo administrador.');
        $this->success('Modulo desativado.');
        $this->redirect('system-modules&id=' . $moduleId);
    }

    private function installPackage(string $packagePath, string $originalName, string $hash): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($packagePath);
        if ($opened !== true) {
            throw new RuntimeException('Nao foi possivel abrir o ZIP enviado.');
        }

        try {
            $manifest = $this->readManifest($zip);
            $normalized = $this->normalizeManifest($manifest);
            $moduleKey = $normalized['key'];

            if ($this->modules->findByKey($moduleKey)) {
                throw new RuntimeException('Ja existe um modulo instalado com a chave "' . $moduleKey . '".');
            }

            $this->validateZipEntries($zip);

            $stagingPath = $this->packagesRoot() . DIRECTORY_SEPARATOR . 'staging_' . $moduleKey . '_' . substr($hash, 0, 12);
            $installPath = $this->modulesRoot() . DIRECTORY_SEPARATOR . $moduleKey;
            $installPathForDb = 'modules/' . $moduleKey;

            if (is_dir($installPath)) {
                throw new RuntimeException('Ja existe uma pasta instalada para este modulo.');
            }

            if (is_dir($stagingPath)) {
                $this->deleteDirectory($stagingPath, $this->packagesRoot());
            }

            if (!mkdir($stagingPath, 0775, true) && !is_dir($stagingPath)) {
                throw new RuntimeException('Nao foi possivel criar a pasta temporaria de instalacao.');
            }

            if (!$zip->extractTo($stagingPath)) {
                throw new RuntimeException('Nao foi possivel extrair o pacote ZIP.');
            }

            $this->writeModuleDenyFile($stagingPath);

            if (!rename($stagingPath, $installPath)) {
                $this->deleteDirectory($stagingPath, $this->packagesRoot());
                throw new RuntimeException('Nao foi possivel mover o modulo para a pasta final.');
            }

            $migrations = $this->resolveMigrations($installPath, $normalized['migrations']);

            $moduleId = $this->modules->createModule([
                'module_key' => $moduleKey,
                'title' => $normalized['title'],
                'version' => $normalized['version'],
                'min_core_version' => $normalized['min_core_version'],
                'description' => $normalized['description'],
                'author' => $normalized['author'],
                'status' => 'inactive',
                'installed_by' => (int) (current_user()['id'] ?? 0),
                'package_filename' => $originalName,
                'package_hash' => $hash,
                'install_path' => $installPathForDb,
                'manifest' => $manifest,
                'permissions' => $normalized['permissions'],
                'menu' => $normalized['menu'],
                'migrations' => $migrations,
            ]);

            $this->modules->replacePermissions($moduleId, $moduleKey, $normalized['permissions']);

            try {
                $this->runMigrations($moduleId, $moduleKey, $installPath, $migrations);
            } catch (Throwable $e) {
                $this->modules->setStatus($moduleId, 'error', $e->getMessage());
                $this->modules->log($moduleId, $moduleKey, 'migration_failed', 'error', $e->getMessage(), [
                    'package' => $originalName,
                ]);
                throw $e;
            }

            $this->modules->log($moduleId, $moduleKey, 'installed', 'info', 'Modulo instalado em modo inativo.', [
                'version' => $normalized['version'],
                'package_hash' => $hash,
                'migrations' => count($migrations),
            ]);

            return ['module_id' => $moduleId, 'module_key' => $moduleKey];
        } finally {
            $zip->close();
        }
    }

    private function readManifest(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('module.json');
        if ($raw === false) {
            throw new RuntimeException('O pacote precisa conter module.json na raiz.');
        }

        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }

        $manifest = json_decode(trim($raw), true);
        if (!is_array($manifest)) {
            throw new RuntimeException('O module.json nao e um JSON valido.');
        }

        return $manifest;
    }

    private function normalizeManifest(array $manifest): array
    {
        $key = strtolower(trim((string) ($manifest['key'] ?? $manifest['name'] ?? '')));
        if (!preg_match('/^[a-z][a-z0-9_-]{2,60}$/', $key)) {
            throw new RuntimeException('A chave do modulo deve usar apenas letras minusculas, numeros, hifen ou underline.');
        }

        $title = trim((string) ($manifest['title'] ?? $manifest['label'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Informe o titulo do modulo no module.json.');
        }

        $version = trim((string) ($manifest['version'] ?? '1.0.0'));
        if ($version === '') {
            throw new RuntimeException('Informe a versao do modulo.');
        }

        $minCoreVersion = trim((string) ($manifest['min_core_version'] ?? ''));
        if ($minCoreVersion !== '' && version_compare($this->coreVersion(), $minCoreVersion, '<')) {
            throw new RuntimeException('Este modulo exige core ' . $minCoreVersion . ' ou superior.');
        }

        return [
            'key' => $key,
            'title' => $title,
            'version' => $version,
            'min_core_version' => $minCoreVersion,
            'description' => trim((string) ($manifest['description'] ?? '')),
            'author' => trim((string) ($manifest['author'] ?? '')),
            'permissions' => $this->normalizePermissions($manifest['permissions'] ?? []),
            'menu' => $this->normalizeMenu($manifest['menu'] ?? [], $key),
            'migrations' => $this->normalizeMigrationList($manifest['migrations'] ?? []),
        ];
    }

    private function normalizePermissions($permissions): array
    {
        if (!is_array($permissions)) {
            return [];
        }

        $normalized = [];
        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $key = trim($permission);
                $label = $key;
            } elseif (is_array($permission)) {
                $key = trim((string) ($permission['key'] ?? ''));
                $label = trim((string) ($permission['label'] ?? $key));
            } else {
                continue;
            }

            if ($key === '') {
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_.:-]+$/', $key)) {
                throw new RuntimeException('Permissao invalida no manifesto: ' . $key);
            }

            $normalized[$key] = [
                'key' => $key,
                'label' => $label !== '' ? $label : $key,
            ];
        }

        return array_values($normalized);
    }

    private function normalizeMenu($menu, string $moduleKey): array
    {
        if (!is_array($menu)) {
            return [];
        }

        $items = $this->looksLikeList($menu) ? $menu : [$menu];
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $route = trim((string) ($item['route'] ?? ''));
            if ($label === '' || $route === '') {
                continue;
            }

            if (str_contains($route, '..') || str_starts_with($route, '/') || preg_match('/^[a-z]+:\/\//i', $route)) {
                throw new RuntimeException('Rota invalida no menu do manifesto: ' . $route);
            }

            if ($route !== 'modules/' . $moduleKey && !str_starts_with($route, 'modules/' . $moduleKey . '/')) {
                throw new RuntimeException('Rotas de menu devem iniciar com modules/' . $moduleKey . '.');
            }

            $area = strtolower(trim((string) ($item['area'] ?? 'main')));
            if (!in_array($area, ['main', 'cadastro'], true)) {
                $area = 'main';
            }

            $normalized[] = [
                'label' => $label,
                'route' => $route,
                'icon' => trim((string) ($item['icon'] ?? 'squares-2x2')),
                'permission' => trim((string) ($item['permission'] ?? '')),
                'area' => $area,
            ];
        }

        return $normalized;
    }

    private function normalizeMigrationList($migrations): array
    {
        if (!is_array($migrations)) {
            return [];
        }

        $normalized = [];
        foreach ($migrations as $migration) {
            $file = is_array($migration)
                ? trim((string) ($migration['file'] ?? ''))
                : trim((string) $migration);

            if ($file === '') {
                continue;
            }

            $file = str_replace('\\', '/', $file);
            if (!$this->isSafeRelativePath($file) || !str_ends_with(strtolower($file), '.sql')) {
                throw new RuntimeException('Migration invalida no manifesto: ' . $file);
            }

            $normalized[] = $file;
        }

        return array_values(array_unique($normalized));
    }

    private function validateZipEntries(ZipArchive $zip): void
    {
        $allowedRoots = [
            'module.json',
            'README.md',
            'readme.md',
            'routes.php',
            'menu.php',
            'permissions.php',
            'install.php',
            'controllers',
            'models',
            'views',
            'assets',
            'migrations',
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $normalized = $this->normalizeZipName($name);
            if ($normalized === null) {
                continue;
            }

            if (!$this->isSafeRelativePath($normalized)) {
                throw new RuntimeException('Caminho inseguro no ZIP: ' . $name);
            }

            $root = explode('/', $normalized, 2)[0] ?? '';
            if (!in_array($root, $allowedRoots, true)) {
                throw new RuntimeException('O pacote contem item fora da estrutura permitida: ' . $root);
            }
        }
    }

    private function resolveMigrations(string $installPath, array $manifestMigrations): array
    {
        $migrations = $manifestMigrations;
        if ($migrations === []) {
            $found = glob($installPath . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql');
            $migrations = array_map(
                fn (string $path): string => 'migrations/' . basename($path),
                is_array($found) ? $found : []
            );
            sort($migrations);
        }

        foreach ($migrations as $migration) {
            $path = $installPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $migration);
            if (!is_file($path)) {
                throw new RuntimeException('Migration informada nao foi encontrada: ' . $migration);
            }
            $this->assertInside($path, $installPath);
        }

        return $migrations;
    }

    private function runMigrations(int $moduleId, string $moduleKey, string $installPath, array $migrations): void
    {
        foreach ($migrations as $migration) {
            $path = $installPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $migration);
            $sql = (string) file_get_contents($path);
            $checksum = hash('sha256', $sql);

            try {
                $this->validateMigrationSql($sql, $migration);
                foreach ($this->splitSqlStatements($sql) as $statement) {
                    $this->modules->executeSql($statement);
                }
                $this->modules->recordMigration($moduleId, $moduleKey, $migration, $checksum, 'executed');
            } catch (Throwable $e) {
                $this->modules->recordMigration($moduleId, $moduleKey, $migration, $checksum, 'failed', $e->getMessage());
                throw new RuntimeException('Falha na migration ' . $migration . ': ' . $e->getMessage());
            }
        }
    }

    private function validateMigrationSql(string $sql, string $migration): void
    {
        if (preg_match('/\b(DROP|TRUNCATE|RENAME)\b/i', $sql)) {
            throw new RuntimeException('Comandos destrutivos nao sao permitidos na primeira fase (' . $migration . ').');
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            $statements[] = $statement;
        }

        return $statements;
    }

    private function normalizeZipName(string $name): ?string
    {
        $name = str_replace('\\', '/', trim($name));
        $name = rtrim($name, '/');
        return $name !== '' ? $name : null;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            return false;
        }

        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }

        return true;
    }

    private function assertInside(string $path, string $base): void
    {
        $realPath = realpath($path);
        $realBase = realpath($base);
        if ($realPath === false || $realBase === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Caminho fora da pasta permitida.');
        }
    }

    private function ensureRuntimeDirectories(): void
    {
        $this->ensureDirectory($this->modulesRoot());
        $this->ensureDirectory($this->packagesRoot());
        $this->writeDenyFile($this->packagesRoot());
        $this->writeDenyFile($this->modulesRoot());
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Nao foi possivel criar a pasta: ' . $path);
        }
    }

    private function writeDenyFile(string $path): void
    {
        $content = "Options -Indexes\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
        @file_put_contents($path . DIRECTORY_SEPARATOR . '.htaccess', $content);
    }

    private function writeModuleDenyFile(string $modulePath): void
    {
        $this->writeDenyFile($modulePath);
    }

    private function deleteDirectory(string $path, string $allowedBase): void
    {
        $realPath = realpath($path);
        $realBase = realpath($allowedBase);
        if ($realPath === false || $realBase === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Remocao bloqueada fora da pasta temporaria.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir((string) $item->getPathname()) : unlink((string) $item->getPathname());
        }

        rmdir($realPath);
    }

    private function safeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $filename) ?: 'modulo.zip';
        return trim($filename, '._') !== '' ? trim($filename, '._') : 'modulo.zip';
    }

    private function looksLikeList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function appRoot(): string
    {
        return dirname(__DIR__);
    }

    private function modulesRoot(): string
    {
        return $this->appRoot() . DIRECTORY_SEPARATOR . 'modules';
    }

    private function packagesRoot(): string
    {
        return $this->appRoot() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'module_packages';
    }

    private function coreVersion(): string
    {
        return (string) config('app.version', '1.0.0');
    }
}
