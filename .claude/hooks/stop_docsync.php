<?php

$projectRoot = realpath(__DIR__ . '/../..');
$stateDir = $projectRoot . '/.claude/hooks/.state';
if ($projectRoot === false || !is_dir($stateDir)) {
    exit(0);
}
$config = @include __DIR__ . '/pipeline_config.php';
if (!is_array($config)) {
    $config = ['code_roots' => ['app', 'tests', 'src', 'lib', 'components', 'config', 'database', 'routes', 'resources', 'pages', 'server'], 'serious_dirs' => '#(Service|Policy|Middleware|Job|Model)#i'];
}

$input = json_decode(file_get_contents('php://stdin'), true);
if (!is_array($input)) {
    exit(0);
}
if (!empty($input['stop_hook_active'])) {
    exit(0);
}
$sid = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($input['session_id'] ?? 'unknown'));
$editsFile = $stateDir . '/edits_' . $sid . '.json';
$edits = [];
if (is_file($editsFile)) {
    $decoded = json_decode((string)@file_get_contents($editsFile), true);
    if (is_array($decoded)) $edits = $decoded;
}
if (empty($edits)) {
    exit(0);
}

$rootsPattern = '#/(' . implode('|', (array)($config['code_roots'] ?? [])) . ')/#i';
$codePaths = [];
$tmpLeftovers = [];
foreach (array_keys($edits) as $p) {
    $n = str_replace('\\', '/', $p);
    if (preg_match($rootsPattern, $n) && !preg_match('/\.(md|log|json)$/i', $n)) {
        $codePaths[] = $n;
    }
    if (preg_match('/Tmp[A-Z]|tmp_|_tmp|Probe[A-Z]/i', $n)) {
        $tmpLeftovers[] = $n;
    }
}
if (empty($codePaths) && empty($tmpLeftovers)) {
    exit(0);
}

$docless = [];
$seenDirs = [];
$rootPrefix = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/';
$seriousDirs = (string)($config['serious_dirs'] ?? '');
foreach ($codePaths as $n) {
    $pos = stripos($n, $rootPrefix);
    $dirN = $pos !== false ? substr($n, $pos + strlen($rootPrefix)) : $n;
    $dirN = preg_replace('#/[^/]+$#', '', $dirN);
    if (isset($seenDirs[$dirN]) || $dirN === '') {
        continue;
    }
    $seenDirs[$dirN] = true;
    if (trim($seriousDirs) === '' || !preg_match($seriousDirs, $n)) {
        continue;
    }
    $walk = $dirN;
    $found = false;
    while (true) {
        foreach ((array) @scandir($projectRoot . '/' . $walk) ?: [] as $entry) {
            if (preg_match('/Pattern\.md$/i', $entry)) {
                $found = true;
                break 2;
            }
        }
        if (!str_contains($walk, '/')) {
            break;
        }
        $walk = preg_replace('#/[^/]+$#', '', $walk);
    }
    if (!$found) {
        $docless[] = $dirN;
    }
}

$items = [];
if (!empty($tmpLeftovers)) {
    $items[] = "Delete leftover temp/probe files before finishing: " . implode(', ', array_map('basename', $tmpLeftovers)) . ".";
}
if ($docless !== []) {
    $items[] = "Docless serious folders touched this turn — create a concise `<dir>Pattern.md` in the established style, or state in one line why the tree's existing docs already cover it: " . implode('; ', $docless) . ".";
}
$items[] = "End-stage doc sweep: one consolidated pass, now — governing *Pattern.md docs, guides/legends (only if this project has them), and tests for everything this turn changed. Do not update docs earlier than stage end.";

@unlink($editsFile);

echo json_encode([
    'decision' => 'block',
    'reason' => "Stage-end documentation gate — finish these before declaring done:\n- " . implode("\n- ", $items),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit(0);