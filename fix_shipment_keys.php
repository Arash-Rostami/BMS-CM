<?php

$files = [
    'resources/shipment/strings.php',
];

$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';

// Standardize `container_types_with_opt` keys to use english as the base keys.
// English: 'Standard', 'Refrigerated', 'Open Top', 'Flat Rack', 'Other'

$standard_keys = [
    'Standard' => [
        '20ft Standard' => '20ft Standard',
        '40ft Standard' => '40ft Standard',
        '40ft High Cube' => '40ft High Cube',
    ],
    'Refrigerated' => [
        '20ft Refrigerated' => '20ft Refrigerated',
        '40ft Refrigerated' => '40ft Refrigerated',
    ],
    'Open Top' => [
        '20ft Open Top' => '20ft Open Top',
        '40ft Open Top' => '40ft Open Top',
    ],
    'Flat Rack' => [
        '20ft Flat Rack' => '20ft Flat Rack',
        '40ft Flat Rack' => '40ft Flat Rack',
    ],
    'Other' => [
        'LCL' => 'LCL',
        'Bulk' => 'Bulk',
    ]
];

$fa_keys = [
    'Standard' => [ // replaced 'استاندارد'
        '20ft Standard' => '۲۰ فوت استاندارد',
        '40ft Standard' => '۴۰ فوت استاندارد',
        '40ft High Cube' => '۴۰ فوت های‌کیوب',
    ],
    'Refrigerated' => [ // replaced 'یخچالی'
        '20ft Refrigerated' => '۲۰ فوت یخچالی',
        '40ft Refrigerated' => '۴۰ فوت یخچالی',
    ],
    'Open Top' => [ // replaced 'روباز'
        '20ft Open Top' => '۲۰ فوت روباز',
        '40ft Open Top' => '۴۰ فوت روباز',
    ],
    'Flat Rack' => [ // replaced 'فلت رک'
        '20ft Flat Rack' => '۲۰ فوت فلت رک',
        '40ft Flat Rack' => '۴۰ فوت فلت رک',
    ],
    'Other' => [ // replaced 'سایر'
        'LCL' => 'خرده‌بار (LCL)',
        'Bulk' => 'فله (Bulk)',
    ]
];

$fr_keys = [
    'Standard' => [
        '20ft Standard' => '20ft Standard',
        '40ft Standard' => '40ft Standard',
        '40ft High Cube' => '40ft High Cube',
    ],
    'Refrigerated' => [ // replaced 'Frigorifique'
        '20ft Refrigerated' => '20ft Frigorifique',
        '40ft Refrigerated' => '40ft Frigorifique',
    ],
    'Open Top' => [
        '20ft Open Top' => '20ft Open Top',
        '40ft Open Top' => '40ft Open Top',
    ],
    'Flat Rack' => [
        '20ft Flat Rack' => '20ft Flat Rack',
        '40ft Flat Rack' => '40ft Flat Rack',
    ],
    'Other' => [ // replaced 'Autres'
        'LCL' => 'LCL',
        'Bulk' => 'Vrac',
    ]
];

$replacements = [
    'en' => $standard_keys,
    'fa' => $fa_keys,
    'fr' => $fr_keys
];

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

foreach ($langs as $lang) {
    $path = "$langDir/$lang/resources/shipment/strings.php";
    $data = include $path;

    if (isset($data['form']['container_types_with_opt'])) {
        // remove the old translated keys
        unset($data['form']['container_types_with_opt']);
    }
    $data['form']['container_types_with_opt'] = $replacements[$lang];

    dump_array_to_php_file($path, $data);
}
