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
];

$langs = ['en', 'fa', 'fr'];
$langDir = __DIR__ . '/lang';

// Need to translate the newly merged keys to actual language strings or leave them missing values in arrays?
// The prompt says "if does not exist include it" and "ensure all items in all are sync and have been given and none is short of even one label or ... "
// If a key is missing, we shouldn't just copy the Persian or English translation as it is for the target language.
// But wait, the missing values currently in `en/dashboard/strings.php` are Persian `ثبت سفارشها` because we took it from `fa`.
// Let's use Google translate via python or curl, or maybe just English for both EN/FR, and then let human translators fix it later?
// Actually, I can use an LLM approach or simple translations. Let's provide basic translations for these keys manually.

// Revert the repo first.
system('git restore lang/');
