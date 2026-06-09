<?php

$translations = json_decode(file_get_contents('translations_missing.json'), true);
$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';

function array_set(&$array, $key, $value) {
    if (is_null($key)) {
        return $array = $value;
    }
    $keys = explode('.', $key);
    while (count($keys) > 1) {
        $key = array_shift($keys);
        if (!isset($array[$key]) || !is_array($array[$key])) {
            $array[$key] = [];
        }
        $array = &$array[$key];
    }
    $array[array_shift($keys)] = $value;
    return $array;
}

function dump_array_to_php_file($file, $array) {
    $code = "<?php\n\nreturn [\n";
    $code .= dump_array_content($array, 1);
    $code .= "];\n";
    file_put_contents($file, $code);
}

function dump_array_content($array, $indent) {
    $code = '';
    $spaces = str_repeat('    ', $indent);
    foreach ($array as $key => $value) {
        $exportKey = var_export($key, true);
        if (is_array($value)) {
            $code .= $spaces . $exportKey . " => [\n" . dump_array_content($value, $indent + 1) . $spaces . "],\n";
        } else {
            if (is_string($value)) {
                $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
                $code .= $spaces . $exportKey . " => '" . $escaped . "',\n";
            } else {
                $code .= $spaces . $exportKey . " => " . var_export($value, true) . ",\n";
            }
        }
    }
    return $code;
}

foreach ($translations as $file => $keysMap) {
    foreach ($langs as $lang) {
        $path = "$langDir/$lang/$file";
        if (file_exists($path)) {
            $data = include $path;
        } else {
            $data = [];
            if (file_exists("$langDir/en/$file")) {
                $data = include "$langDir/en/$file";
            }
        }

        foreach ($keysMap as $dotKey => $langVals) {
            if (isset($langVals[$lang])) {
                array_set($data, $dotKey, $langVals[$lang]);
            }
        }

        dump_array_to_php_file($path, $data);
    }
}
