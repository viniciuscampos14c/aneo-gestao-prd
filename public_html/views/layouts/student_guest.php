<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Portal do Aluno') . ' | ' . config('app.name')); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/x-icon" href="favicon.ico?v=<?= e((string) (is_file(__DIR__ . '/../../favicon.ico') ? filemtime(__DIR__ . '/../../favicon.ico') : date('YmdHis'))); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png?v=<?= e((string) (is_file(__DIR__ . '/../../favicon-32x32.png') ? filemtime(__DIR__ . '/../../favicon-32x32.png') : date('YmdHis'))); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png?v=<?= e((string) (is_file(__DIR__ . '/../../favicon-16x16.png') ? filemtime(__DIR__ . '/../../favicon-16x16.png') : date('YmdHis'))); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png?v=<?= e((string) (is_file(__DIR__ . '/../../apple-touch-icon.png') ? filemtime(__DIR__ . '/../../apple-touch-icon.png') : date('YmdHis'))); ?>">
    <link rel="manifest" href="site.webmanifest?v=<?= e((string) (is_file(__DIR__ . '/../../site.webmanifest') ? filemtime(__DIR__ . '/../../site.webmanifest') : date('YmdHis'))); ?>">
    <meta name="theme-color" content="#0a1628">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12">
        <?= $content; ?>
    </main>
</body>
</html>
