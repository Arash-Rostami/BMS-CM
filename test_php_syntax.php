<?php

$langDir = __DIR__ . '/lang';
$langs = ['en', 'fa', 'fr'];

$hasErrors = false;
foreach ($langs as $lang) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$langDir/$lang"));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            exec("php -l " . escapeshellarg($path), $output, $returnVar);
            if ($returnVar !== 0) {
                echo "Syntax error in $path\n";
                $hasErrors = true;
            }
        }
    }
}

if (!$hasErrors) {
    echo "All language files have valid PHP syntax.\n";
}
