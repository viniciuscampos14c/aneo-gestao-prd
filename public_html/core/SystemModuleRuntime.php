<?php

class SystemModuleRuntime
{
    private SystemModuleModel $modules;
    private array $activeModules = [];
    private bool $loaded = false;

    public function __construct(?SystemModuleModel $modules = null)
    {
        $this->modules = $modules ?? new SystemModuleModel();
    }

    public function activeMenuItems(string $area = 'main'): array
    {
        $items = [];
        foreach ($this->activeModules() as $module) {
            $moduleKey = (string) ($module['module_key'] ?? '');
            foreach ($this->modules->decodeJson($module['menu_json'] ?? null) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $route = trim((string) ($item['route'] ?? ''));
                $itemArea = strtolower(trim((string) ($item['area'] ?? 'main')));
                if ($route === '' || $itemArea !== $area || !$this->routeBelongsToModule($route, $moduleKey)) {
                    continue;
                }

                $permission = trim((string) ($item['permission'] ?? ''));
                $items[] = [
                    'module' => $permission !== '' ? $permission : $moduleKey,
                    'label' => (string) ($item['label'] ?? $module['title'] ?? $moduleKey),
                    'icon' => (string) ($item['icon'] ?? 'archive-box'),
                    'route' => $route,
                    'module_key' => $moduleKey,
                    'module_title' => (string) ($module['title'] ?? $moduleKey),
                ];
            }
        }

        return $items;
    }

    public function registerRoutes(Router $router): void
    {
        foreach ($this->activeModules() as $module) {
            try {
                $this->registerModuleRoutes($router, $module);
            } catch (Throwable $e) {
                $this->modules->log(
                    (int) ($module['id'] ?? 0) ?: null,
                    (string) ($module['module_key'] ?? ''),
                    'route_loader_failed',
                    'error',
                    $e->getMessage()
                );
            }
        }
    }

    private function registerModuleRoutes(Router $router, array $module): void
    {
        $moduleKey = (string) ($module['module_key'] ?? '');
        $modulePath = $this->modulePath($module);
        if ($moduleKey === '' || $modulePath === null) {
            return;
        }

        $routesFile = $modulePath . DIRECTORY_SEPARATOR . 'routes.php';
        if (!is_file($routesFile)) {
            return;
        }

        $this->loadModulePhpFiles($modulePath . DIRECTORY_SEPARATOR . 'models');
        $this->loadModulePhpFiles($modulePath . DIRECTORY_SEPARATOR . 'controllers');

        $routes = require $routesFile;
        if (!is_array($routes)) {
            throw new RuntimeException('routes.php precisa retornar um array.');
        }

        foreach ($routes as $routeDef) {
            if (!is_array($routeDef)) {
                continue;
            }

            $method = strtoupper(trim((string) ($routeDef['method'] ?? 'GET')));
            $path = trim((string) ($routeDef['route'] ?? $routeDef['path'] ?? ''), '/');
            if (!in_array($method, ['GET', 'POST'], true) || $path === '') {
                continue;
            }

            if (!$this->routeBelongsToModule($path, $moduleKey)) {
                throw new RuntimeException('Rota fora do prefixo permitido: ' . $path);
            }

            $handler = fn () => $this->dispatchModuleRoute($module, $routeDef, $modulePath);
            if ($method === 'POST') {
                $router->post($path, $handler);
            } else {
                $router->get($path, $handler);
            }
        }
    }

    private function dispatchModuleRoute(array $module, array $routeDef, string $modulePath): void
    {
        require_auth();

        $permission = trim((string) ($routeDef['permission'] ?? ''));
        if ($permission !== '') {
            require_permission($permission);
        }

        if (!empty($routeDef['controller']) && !empty($routeDef['action'])) {
            $this->dispatchControllerRoute($routeDef);
            return;
        }

        $view = trim((string) ($routeDef['view'] ?? ''));
        if ($view !== '') {
            $this->renderModuleView($module, $routeDef, $modulePath, $view);
            return;
        }

        throw new RuntimeException('Rota de módulo sem controller/action ou view.');
    }

    private function dispatchControllerRoute(array $routeDef): void
    {
        $controllerClass = trim((string) ($routeDef['controller'] ?? ''));
        $action = trim((string) ($routeDef['action'] ?? ''));

        if (!$this->isSafeClassName($controllerClass) || !$this->isSafeMethodName($action)) {
            throw new RuntimeException('Controller ou action inválido no módulo.');
        }

        if (!class_exists($controllerClass)) {
            throw new RuntimeException('Controller do módulo não encontrado: ' . $controllerClass);
        }

        $controller = new $controllerClass();
        if (!method_exists($controller, $action)) {
            throw new RuntimeException('Action do módulo não encontrada: ' . $controllerClass . '::' . $action);
        }

        $controller->$action();
    }

    private function renderModuleView(array $module, array $routeDef, string $modulePath, string $view): void
    {
        $view = str_replace('\\', '/', $view);
        if (!$this->isSafeRelativePath($view)) {
            throw new RuntimeException('View inválida no módulo.');
        }

        $viewPath = $modulePath . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($viewPath)) {
            throw new RuntimeException('View do módulo não encontrada: ' . $view);
        }
        $this->assertInside($viewPath, $modulePath . DIRECTORY_SEPARATOR . 'views');

        $title = (string) ($routeDef['title'] ?? $module['title'] ?? 'Módulo');
        $moduleInfo = $module;
        $moduleRoute = $routeDef;
        $moduleKey = (string) ($module['module_key'] ?? '');

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.php';
    }

    private function activeModules(): array
    {
        if ($this->loaded) {
            return $this->activeModules;
        }

        try {
            $this->activeModules = $this->modules->listActiveModules();
        } catch (Throwable $e) {
            $this->activeModules = [];
        }

        $this->loaded = true;
        return $this->activeModules;
    }

    private function modulePath(array $module): ?string
    {
        $installPath = trim((string) ($module['install_path'] ?? ''));
        $moduleKey = trim((string) ($module['module_key'] ?? ''));
        if ($installPath === '' || $moduleKey === '') {
            return null;
        }

        $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules');
        $path = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $installPath));
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (basename($path) !== $moduleKey) {
            return null;
        }

        return $path;
    }

    private function loadModulePhpFiles(string $path): void
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_dir($realPath)) {
            return;
        }

        foreach (glob($realPath . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $this->assertInside($file, $realPath);
            require_once $file;
        }
    }

    private function routeBelongsToModule(string $route, string $moduleKey): bool
    {
        $route = trim($route, '/');
        return $moduleKey !== ''
            && ($route === 'modules/' . $moduleKey || str_starts_with($route, 'modules/' . $moduleKey . '/'));
    }

    private function assertInside(string $path, string $base): void
    {
        $realBase = realpath($base);
        $realPath = realpath($path);
        if ($realBase === false || $realPath === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Caminho fora da pasta permitida do módulo.');
        }
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

    private function isSafeClassName(string $class): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class);
    }

    private function isSafeMethodName(string $method): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $method);
    }
}
