<?php

$langDir = __DIR__ . '/lang';
$langs = ['en', 'fa', 'fr'];

// Get all files
$allFiles = [];
foreach ($langs as $lang) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$langDir/$lang"));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = substr($file->getPathname(), strlen("$langDir/$lang/"));
            if (!in_array($relativePath, $allFiles)) {
                $allFiles[] = $relativePath;
            }
        }
    }
}

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

// Map used keys
$appFiles = getPhpFiles(__DIR__ . '/app');
$usedKeys = [];
foreach ($appFiles as $file) {
    $content = file_get_contents($file);
    preg_match_all("/__\(\s*'([^']+)'\s*\)/", $content, $matches1);
    preg_match_all("/trans\(\s*'([^']+)'\s*\)/", $content, $matches2);

    $keys = array_merge($matches1[1], $matches2[1]);
    foreach ($keys as $key) {
        $usedKeys[$key] = true;
    }
}

// Verify each used key is present in 'en' language file
$missingUsedKeys = [];
foreach (array_keys($usedKeys) as $fullKey) {
    $parts = explode('.', $fullKey);

    $foundFile = null;
    $foundPath = [];

    for ($i = count($parts) - 1; $i >= 1; $i--) {
        $possibleFile = implode('/', array_slice($parts, 0, $i)) . '.php';
        if (in_array($possibleFile, $allFiles)) {
            $foundFile = $possibleFile;
            $foundPath = array_slice($parts, $i);
            break;
        }
    }

    if ($foundFile) {
        $path = "$langDir/en/$foundFile";
        if (file_exists($path)) {
            $data = include $path;
            $curr = $data;
            foreach ($foundPath as $p) {
                if (isset($curr[$p])) {
                    $curr = $curr[$p];
                } else {
                    $missingUsedKeys[] = "Missing key '$fullKey' in '$path'";
                    break;
                }
            }
        }
    } else {
        // Some keys might not be in our files, e.g. 'pagination.next'
    }
}

foreach ($missingUsedKeys as $m) {
    echo $m . "\n";
}
if (empty($missingUsedKeys)) {
    echo "All used keys from app/ exist in language files!\n";
}
