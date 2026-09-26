<?php

$projectRoot = realpath(__DIR__ . '/../..');
$stateDir = $projectRoot . '/.claude/hooks/.state';

$input = json_decode(file_get_contents('php://stdin'), true);
if (!is_array($input)) {
    exit(0);
}

$sessionId = $input['session_id'] ?? 'unknown';
$toolInput = $input['tool_input'] ?? [];
$filePath = $toolInput['file_path'] ?? null;

function allow(): void
{
    exit(0);
}

function deny(string $reason): void
{
    echo json_encode([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'deny',
            'permissionDecisionReason' => $reason,
        ],
    ]);
    exit(0);
}

if (!is_string($filePath) || $filePath === '') {
    allow();
}

$normalized = str_replace('\\', '/', $filePath);
if (preg_match('/[^\/]+Pattern\.md$/i', $normalized)) {
    allow();
}

$targetDir = is_dir($filePath) ? $filePath : dirname($filePath);
$targetDirReal = realpath($targetDir);

if ($targetDirReal === false || $projectRoot === false) {
    allow();
}

$rootNormalized = str_replace('\\', '/', $projectRoot);
$dir = str_replace('\\', '/', $targetDirReal);

if (!str_starts_with($dir, $rootNormalized)) {
    allow();
}

$governingDocs = [];

while (true) {
    $entries = @scandir($dir);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (preg_match('/Pattern\.md$/i', $entry)) {
                $full = realpath($dir . '/' . $entry);
                if ($full !== false) {
                    $governingDocs[] = $full;
                }
            }
        }
    }

    if (!empty($governingDocs)) {
        break;
    }

    if ($dir === $rootNormalized || strlen($dir) <= strlen($rootNormalized)) {
        break;
    }

    $parent = dirname($dir);
    $parent = str_replace('\\', '/', $parent);
    if ($parent === $dir) {
        break;
    }
    $dir = $parent;
}

if (empty($governingDocs)) {
    allow();
}

$markerFile = $stateDir . '/pattern_reads_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId) . '.json';
$read = [];
if (is_file($markerFile)) {
    $decoded = json_decode((string) @file_get_contents($markerFile), true);
    if (is_array($decoded)) {
        $read = $decoded;
    }
}

$required = [];
foreach ($governingDocs as $doc) {
    $required[$doc] = 'pattern doc';
}

$matches = null;
if (preg_match('#livewire/dashboard/(?:tab/([^/]+)|([^/]+)(?:/tab/([^/]+))?)/#i', $normalized, $matches)) {
    $kebab = fn (string $s): string => strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $s));
    $legendCandidates = [];
    if (!empty($matches[1])) {
        $slug = $kebab($matches[1]);
        foreach (array_unique([$slug, $slug . 's', rtrim($slug, 's'), str_replace('-', '', $slug)]) as $cand) {
            $legendCandidates[] = $projectRoot . '/resources/views/livewire/dashboard/tab/' . $cand . '/legend.blade.php';
        }
    } else {
        $slug = $kebab($matches[2]);
        $moduleSlugs = array_unique([$slug, $slug . 's', rtrim($slug, 's'), str_replace('-', '', $slug)]);
        foreach ($moduleSlugs as $cand) {
            $legendCandidates[] = $projectRoot . '/resources/views/livewire/dashboard/' . $cand . '/legend.blade.php';
        }
        if (isset($matches[3])) {
            $tslug = $kebab($matches[3]);
            foreach ($moduleSlugs as $cand) {
                $legendCandidates[] = $projectRoot . '/resources/views/livewire/dashboard/' . $cand . '/tab/' . $tslug . '/legend.blade.php';
            }
        }
    }
    foreach ($legendCandidates as $legend) {
        $legendReal = realpath($legend);
        if ($legendReal !== false) {
            $required[$legendReal] = 'module legend';
        }
    }
}
if (preg_match('#app/Filament/Resources/([A-Za-z0-9]+)Resource#i', $normalized, $matches)) {
    $snake = ltrim(strtolower(preg_replace('/([A-Z]+)/', '_$1', $matches[1])), '_');
    $guideReal = realpath($projectRoot . '/resources/views/filament/resources/' . $snake . '/guide/overview.blade.php');
    if ($guideReal !== false) {
        $required[$guideReal] = 'admin guide (overview tab)';
    }
} elseif (preg_match('#/filament/resources/([a-z0-9_]+)/#i', $normalized, $matches) && !str_contains($normalized, '/guide/')) {
    $guideReal = realpath($projectRoot . '/resources/views/filament/resources/' . $matches[1] . '/guide/overview.blade.php');
    if ($guideReal !== false) {
        $required[$guideReal] = 'admin guide (overview tab)';
    }
}

$missing = [];
foreach ($required as $doc => $kind) {
    if (!in_array($doc, $read, true)) {
        $missing[] = "['{$kind}'] " . ltrim(str_replace($rootNormalized, '', str_replace('\\', '/', $doc)), '/');
    }
}

if (empty($missing)) {
    allow();
}

deny(
    "Unread governing docs for this edit: " .
    implode(', ', $missing) .
    ". Read each first (they document the conventions, semantics, and UI copy of exactly what you are editing), then retry."
);
