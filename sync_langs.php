<?php

function getPhpFiles($dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';

$allLangFiles = [];
foreach ($langs as $lang) {
    $files = getPhpFiles("$langDir/$lang");
    foreach ($files as $file) {
        $relativePath = substr($file, strlen("$langDir/$lang/"));
        if (!in_array($relativePath, $allLangFiles)) {
            $allLangFiles[] = $relativePath;
        }
    }
}

// 1. Find all `__('key')` used in `app/`
$appFiles = getPhpFiles(__DIR__ . '/app');
$usedKeys = [];
foreach ($appFiles as $file) {
    $content = file_get_contents($file);
    // match __('some.key') or trans('some.key')
    preg_match_all("/__\(\s*'([^']+)'\s*\)/", $content, $matches1);
    preg_match_all("/trans\(\s*'([^']+)'\s*\)/", $content, $matches2);

    $keys = array_merge($matches1[1], $matches2[1]);
    foreach ($keys as $key) {
        $usedKeys[$key] = true;
    }
}

// Map used keys to files and paths
// e.g., 'resources/target/strings.metrics' => file 'resources/target/strings.php', array path 'metrics'
$requiredKeysByFile = [];
foreach (array_keys($usedKeys) as $fullKey) {
    $parts = explode('.', $fullKey);
    // In Laravel, the first part is the file name, or directory/file.
    // However, looking at the paths, they are like `resources/target/strings.metrics`
    // which means file is `resources/target/strings.php` and key is `metrics`
    // Let's check if the file exists.
    $foundFile = null;
    $foundPath = [];

    for ($i = count($parts) - 1; $i >= 1; $i--) {
        $possibleFile = implode('/', array_slice($parts, 0, $i)) . '.php';
        if (in_array($possibleFile, $allLangFiles)) {
            $foundFile = $possibleFile;
            $foundPath = array_slice($parts, $i);
            break;
        }
    }

    if ($foundFile) {
        if (!isset($requiredKeysByFile[$foundFile])) {
            $requiredKeysByFile[$foundFile] = [];
        }
        $requiredKeysByFile[$foundFile][] = $foundPath;
    }
}

print_r($requiredKeysByFile);
