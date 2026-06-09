<?php

$files = [
    'resources/shipment/strings.php',
];

$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';

function array_merge_recursive_distinct(array &$array1, array &$array2) {
    $merged = $array1;
    foreach ($array2 as $key => &$value) {
        if ($key === 'container_types_with_opt') {
            // Do not merge this key deeply. Keep the existing structure since keys are translated!
            if (!isset($merged[$key])) {
                $merged[$key] = $value;
            }
            continue;
        }

        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
        } else {
            if (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }
    }
    return $merged;
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
                $value = str_replace("'", "\'", str_replace("\\", "\\\\", $value));
                $code .= $spaces . $exportKey . " => '" . $value . "',\n";
            } else {
                $code .= $spaces . $exportKey . " => " . var_export($value, true) . ",\n";
            }
        }
    }
    return $code;
}

foreach ($files as $file) {
    $allKeys = [];
    foreach ($langs as $lang) {
        $path = "$langDir/$lang/$file";
        if (file_exists($path)) {
            $data = include $path;
            $allKeys = array_merge_recursive_distinct($allKeys, $data);
        }
    }

    foreach ($langs as $lang) {
        $path = "$langDir/$lang/$file";
        $current = [];
        if (file_exists($path)) {
            $current = include $path;
        }
        $merged = array_merge_recursive_distinct($current, $allKeys);
        dump_array_to_php_file($path, $merged);
    }
}
