<?php
$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';

function flatten_array($array, $prefix = '') {
    $result = [];
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $result = array_merge($result, flatten_array($value, $prefix . $key . '.'));
        } else {
            $result[$prefix . $key] = $value;
        }
    }
    return $result;
}

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

$missingKeys = [];
foreach ($allFiles as $file) {
    $keysByLang = [];
    $allKeys = [];

    foreach ($langs as $lang) {
        $path = "$langDir/$lang/$file";
        $keysByLang[$lang] = [];
        if (file_exists($path)) {
            $data = include $path;
            if (is_array($data)) {
                $flattened = flatten_array($data);
                $keysByLang[$lang] = $flattened;
                foreach (array_keys($flattened) as $key) {
                    $allKeys[$key] = true;
                }
            }
        }
    }

    foreach (array_keys($allKeys) as $key) {
        foreach ($langs as $lang) {
            if (!array_key_exists($key, $keysByLang[$lang])) {
                $missingKeys[] = "Missing in $lang: $file -> $key";
            }
        }
    }
}

foreach ($missingKeys as $m) {
    echo $m . "\n";
}
if (empty($missingKeys)) {
    echo "All language files are perfectly synced!\n";
}
