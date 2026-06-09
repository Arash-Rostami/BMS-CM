<?php

function get_array_from_file($file) {
    if (file_exists($file)) {
        return include $file;
    }
    return [];
}

function write_array_to_file($file, $array) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $code = "<?php\n\nreturn " . var_export($array, true) . ";\n";
    // Replace the default array syntax to short array syntax
    $code = preg_replace('/array \(/', '[', $code);
    $code = preg_replace('/\),/', '],', $code);
    $code = preg_replace('/\)\]/', ']]', $code);
    $code = preg_replace('/\)$/', ']', $code);

    // Better var_export replacing to preserve string escaping
    file_put_contents($file, $code);
}

// Read missing keys report and add keys to respective files
// We will do this manually or by a better script.
