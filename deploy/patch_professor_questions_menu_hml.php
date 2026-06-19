<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Layout nao encontrado.\n");
    exit(1);
}

$contents = (string) file_get_contents($file);
$menuLine = "    ['module' => 'courses', 'label' => 'Duvidas dos Alunos', 'icon' => 'question-mark-circle', 'route' => 'courses/questions', 'hidden' => !\$isProfessor],";
if (str_contains($contents, $menuLine)) {
    echo "menu=already_present\n";
    exit;
}

$anchor = "    ['module' => 'courses', 'label' => 'Cursos EAD', 'icon' => 'academic-cap', 'route' => 'courses'],";
if (!str_contains($contents, $anchor)) {
    fwrite(STDERR, "Ancora do menu nao encontrada.\n");
    exit(1);
}

$updated = str_replace($anchor, $anchor . PHP_EOL . $menuLine, $contents, $count);
if ($count !== 1 || file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Falha ao atualizar o menu.\n");
    exit(1);
}

echo "menu=patched\n";
