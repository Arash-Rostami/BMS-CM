<?php
$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';
$file = 'resources/shipment/strings.php';

$contents = [];
foreach ($langs as $lang) {
    $contents[$lang] = include "$langDir/$lang/$file";
}

// In shipment/strings.php, make sure the `container_types_with_opt` is synchronized.
// Actually we'll just define the structure correctly.
$en_opt = $contents['en']['form']['container_types_with_opt'] ?? [];
$fa_opt = $contents['fa']['form']['container_types_with_opt'] ?? [];
$fr_opt = $contents['fr']['form']['container_types_with_opt'] ?? [];

// Let's print out what they look like in EN
var_dump(array_keys($en_opt));
var_dump(array_keys($fa_opt));
var_dump(array_keys($fr_opt));
