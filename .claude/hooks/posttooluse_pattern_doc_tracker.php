<?php

$projectRoot = realpath(__DIR__ . '/../..');
$stateDir = $projectRoot . '/.claude/hooks/.state';
if (!is_dir($stateDir)) {
    @mkdir($stateDir, 0777, true);
}

$input = json_decode(file_get_contents('php://stdin'), true);
if (!is_array($input)) {
    exit(0);
}

$sessionId = $input['session_id'] ?? 'unknown';
$toolInput = $input['tool_input'] ?? [];
$filePath = $toolInput['file_path'] ?? null;

if (!is_string($filePath)) {
    exit(0);
}
$isPatternDoc = preg_match('/[^\/\\\\]+Pattern\.md$/i', $filePath) === 1;
$isUserLegend = preg_match('/[\/\\\\]dashboard[\/\\\\](tab[\/\\\\])?[^\/\\\\]+[\/\\\\]legend\.blade\.php$/i', $filePath) === 1;
$isAdminGuide = preg_match('/[\/\\\\]filament[\/\\\\]resources[\/\\\\][^\/\\\\]+[\/\\\\]guide[\/\\\\][^\/\\\\]+\.blade\.php$/i', $filePath) === 1;
if (!$isPatternDoc && !$isUserLegend && !$isAdminGuide) {
    exit(0);
}

$real = realpath($filePath);
if ($real === false) {
    exit(0);
}

$markerFile = $stateDir . '/pattern_reads_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId) . '.json';

$read = [];
if (is_file($markerFile)) {
    $decoded = json_decode((string) @file_get_contents($markerFile), true);
    if (is_array($decoded)) {
        $read = $decoded;
    }
}

$read = array_values(array_filter($read, fn($v) => $v !== $real));
$read[] = $real;
if (count($read) > 200) {
    $read = array_slice($read, -200);
}
@file_put_contents($markerFile, json_encode($read), LOCK_EX);

exit(0);
