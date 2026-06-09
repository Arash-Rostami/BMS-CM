<?php

$files = [
    'dashboard/strings.php',
    'resources/user/strings.php',
    'resources/registeredOrder/strings.php',
    'resources/purchaseRequest/strings.php',
    'resources/payment/strings.php',
    'resources/product/strings.php',
    'resources/proformaInvoice/strings.php',
    'resources/purchaseOrder/strings.php',
    // skipping shipment for now, as it has custom keys
];

$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';

function array_merge_recursive_distinct(array &$array1, array &$array2) {
    $merged = $array1;
    foreach ($array2 as $key => &$value) {
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
    // Generate valid PHP code string
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
            // handle strings properly escaping single quotes
            if (is_string($value)) {
                // simple escaping
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
        $merged = array_merge_recursive_distinct($current, $allKeys); // ensure missing keys are added
        dump_array_to_php_file($path, $merged);
    }
}
