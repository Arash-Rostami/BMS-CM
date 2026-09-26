<?php

$THRESHOLD = 93;
$OLLAMA_URL = 'http://localhost:11434/api/chat';
$chosen = trim(getenv('FATEH_LEAD_MODEL') ?: '');
if ($chosen === '') $chosen = trim(getenv('ANTHROPIC_MODEL') ?: '');
$OLLAMA_MODEL = $chosen !== '' ? $chosen : 'glm-5.2:cloud';
$FALLBACK_CHAIN = [];
foreach (['FATEH_FALLBACK_REVIEWER', 'FATEH_FALLBACK_MODEL', 'FATEH_FALLBACK_MODEL_2'] as $fbEnv) {
    $fbVal = trim((string) getenv($fbEnv));
    if ($fbVal !== '') $FALLBACK_CHAIN[] = $fbVal;
}
foreach (['glm-5.2:cloud', 'glm-5.1:cloud'] as $fbVal) {
    if (!in_array($fbVal, $FALLBACK_CHAIN, true)) $FALLBACK_CHAIN[] = $fbVal;
}
$aId = trim(getenv('FATEH_REVIEWER_MODEL_A') ?: '');
$REVIEWER_A = $aId !== '' ? $aId : $OLLAMA_MODEL;
$bId = trim(getenv('FATEH_REVIEWER_MODEL_B') ?: '');
$REVIEWER_B = $bId !== '' ? $bId : $OLLAMA_MODEL;
$QUICK_REVIEWER = trim(getenv('FATEH_QUICK_REVIEWER') ?: '');
if ($QUICK_REVIEWER === '') $QUICK_REVIEWER = $REVIEWER_A;
$GATE_REVIEWER = trim(getenv('FATEH_GATE_REVIEWER') ?: '');
if ($GATE_REVIEWER === '') $GATE_REVIEWER = $REVIEWER_A;
$sId = trim(getenv('FATEH_SENSITIVE_REVIEWER') ?: '');
$SENSITIVE_REVIEWER = $sId !== '' ? $sId : $REVIEWER_B;
$MICRO_LINES = (int)((trim(getenv('FATEH_MICRO_LINES') ?: '') ?: '25'));
$BIG_LINES = (int)((trim(getenv('FATEH_BIG_LINES') ?: '') ?: '150'));
$SENSITIVE_PATTERN = trim(getenv('FATEH_SENSITIVE_PATHS') ?: '') ?: 'migration|\.env|(^|[/_\.-])auth(?![a-z])|polic(?:y|ies)|middleware|scope|config|secret|credential';
$LOG = __DIR__ . '/../review.log';

function classifyTier(array $input, string $tool, string $file): array
{
    global $MICRO_LINES, $BIG_LINES, $SENSITIVE_PATTERN;
    if (preg_match("#({$SENSITIVE_PATTERN})#i", $file)) return ['sensitive', 'sensitive-path'];
    $content = $tool === 'Write' ? ($input['content'] ?? '') : ($input['new_string'] ?? '');
    $lines = ($content === '') ? 1 : substr_count($content, "\n") + 1;
    if ($lines <= $MICRO_LINES) return ['skip', 'micro-diff'];
    if ($lines > $BIG_LINES) return ['deep', 'large-diff'];
    return ['quick', 'normal-diff'];
}

function logLine(string $line): void
{
    global $LOG;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($LOG, "[$ts] $line\n", FILE_APPEND);
}

function fileContext(string $file): string
{
    $real = realpath($file);
    if ($real === false || !is_file($real)) return '';
    $contents = @file_get_contents($real);
    if ($contents === false || $contents === '') return '';
    $lines = explode("\n", $contents);
    if (count($lines) > 500) {
        $contents = implode("\n", array_slice($lines, 0, 500)) . "\n... (truncated, " . count($lines) . " lines total)";
    }
    return "\n--- full file (post-edit, for context only) ---\n" . $contents;
}

function buildDiff(string $tool, array $input): string
{
    $file = $input['file_path'] ?? '(unknown)';
    $ctx = fileContext($file);
    if ($tool === 'Write') {
        return "File: $file (Write/new)\n--- content ---\n" . ($input['content'] ?? '') . $ctx;
    }
    return "File: $file (Edit)\n--- old ---\n" . ($input['old_string'] ?? '') . "\n--- new ---\n" . ($input['new_string'] ?? '') . $ctx;
}

function extractJson(string $content): ?array
{
    $content = trim($content);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) return $decoded;
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) return $decoded;
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $slice = substr($content, $start, $end - $start + 1);
        $decoded = json_decode($slice, true);
        if (is_array($decoded)) return $decoded;
    }
    return null;
}

function ollamaCall(array $prompts, array $models = []): array
{
    global $OLLAMA_URL, $OLLAMA_MODEL;
    $schema = [
        'type' => 'object',
        'properties' => [
            'verdict' => ['type' => 'string', 'enum' => ['pass', 'fail']],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
            'confidence' => ['type' => 'integer'],
            'dry_run_notes' => ['type' => 'string'],
        ],
        'required' => ['verdict', 'confidence'],
    ];
    $mh = curl_multi_init();
    $handles = [];
    foreach (array_values($prompts) as $i => $prompt) {
        $model = $models[$i] ?? $OLLAMA_MODEL;
        $payload = json_encode([
            'model' => $model,
            'stream' => false,
            'format' => $schema,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'options' => ['temperature' => 0.2],
        ], JSON_UNESCAPED_SLASHES);
        $ch = curl_init($OLLAMA_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $active);
        if ($status !== CURLM_OK) break;
        if ($active) curl_multi_select($mh, 1.0);
    } while ($active && $status === CURLM_OK);
    $out = [];
    foreach ($handles as $i => $ch) {
        $raw = curl_multi_getcontent($ch);
        $out[$i] = null;
        if ($raw !== false && $raw !== null) {
            $data = json_decode($raw, true);
            $content = $data['message']['content'] ?? null;
            if (is_string($content)) {
                $out[$i] = extractJson($content);
            }
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

function ollamaCallFallback(string $prompt, string $primary): ?array
{
    global $FALLBACK_CHAIN;
    $r = ollamaCall([$prompt], [$primary])[0] ?? null;
    if (!empty($r)) return $r;
    foreach ($FALLBACK_CHAIN as $model) {
        if ($model === $primary) continue;
        $r = ollamaCall([$prompt], [$model])[0] ?? null;
        if (!empty($r)) return $r;
    }
    return null;
}

function emitBlock(string $reason): void
{
    echo json_encode(['decision' => 'block', 'reason' => $reason], JSON_UNESCAPED_SLASHES);
    exit(0);
}

function commonPreamble(string $diff): string
{
    $cfg = @include __DIR__ . '/pipeline_config.php';
    $stack = is_array($cfg) ? (string)($cfg['stack'] ?? 'laravel') : 'laravel';
    $stackPhrase = $stack === 'next' ? 'a Next.js (React) codebase' : ($stack === 'node' ? 'a Node.js/Express codebase' : 'a Laravel + Filament + Livewire codebase');
    return "You are reviewing a code change in {$stackPhrase}. Project rules: NO code comments are allowed (flag any comment introduced). Focus on the DIFF; the full file is attached as context only, not for judging unrelated code. Dry-run trace the changed code in your reasoning to verify control flow, edge cases, and integration.\n\n" . $diff . "\n\nOutput STRICT JSON only: {\"verdict\":\"pass|fail\",\"issues\":[\"concrete problem\"],\"confidence\":0-100,\"dry_run_notes\":\"trace summary\"}. verdict=pass only if no real issue is present. confidence = your confidence this change is safe to ship (0-100); a clean correct change should score 93 or higher, score below 93 only when you can name a concrete concern.";
}

function reviewerA(string $diff): string
{
    return "Your review lens: correctness, logic bugs, unhandled edge cases, and security (injection, XSS, missing auth check, data leakage). " . commonPreamble($diff);
}

function reviewerB(string $diff): string
{
    return "Your review lens: performance (queries inside loops, missing eager loads causing N+1, unbounded queries on large tables, repeated container resolution), pattern-consistency (Action/Validator/Presenter/Service classes where the project mandates them), minimality, and absence of code comments. " . commonPreamble($diff);
}

function reviewerQuick(string $diff): string
{
    return "Quick tripwire review: real correctness bugs, broken control flow, unhandled edge cases, and code comments introduced. Be fast and decisive; do not nitpick style or architecture. " . commonPreamble($diff);
}

function gateReview(array $tier, string $diff, string $file): void
{
    global $THRESHOLD, $QUICK_REVIEWER, $GATE_REVIEWER, $SENSITIVE_REVIEWER;
    if ($tier[0] === 'skip') {
        logLine("tier0 skip file=$file reason={$tier[1]}");
        exit(0);
    }
    $kind = $tier[0];
    $model = $kind === 'quick' ? $QUICK_REVIEWER : ($kind === 'deep' ? $GATE_REVIEWER : $SENSITIVE_REVIEWER);
    $lens = $kind === 'quick' ? 'quick tripwire' : ($kind === 'deep' ? 'perf/pattern-consistency' : 'correctness/security');
    $prompt = $kind === 'quick' ? reviewerQuick($diff) : ($kind === 'deep' ? reviewerB($diff) : reviewerA($diff));
    $r = ollamaCall([$prompt], [$model])[0] ?? null;
    if (!$r) $r = ollamaCallFallback($prompt, $model);
    if (!$r) {
        logLine("gate passthrough (infra-fail + fallback-fail) file=$file model=$model tier=$kind");
        exit(0);
    }
    $v = is_array($r) ? ($r['verdict'] ?? 'fail') : 'fail';
    $c = (int)($r['confidence'] ?? 0);
    if ($v === 'pass' && $c >= $THRESHOLD) {
        logLine("gate pass tier=$kind file=$file conf=$c model=$model lens=$lens");
        exit(0);
    }
    $issues = implode(' | ', (array)($r['issues'] ?? ['no issues stated']));
    logLine("gate block tier=$kind file=$file verdict=$v conf=$c model=$model lens=$lens");
    emitBlock("Post-tool review blocked ({$lens} gate, single round, model {$model}; verdict {$v}, confidence {$c}%). Issues: {$issues}. Dry-run: " . ($r['dry_run_notes'] ?? '-') . ". Required fixes: {$issues}");
}

$stdin = file_get_contents('php://stdin');
$payload = json_decode($stdin, true);
if (!is_array($payload)) {
    exit(0);
}
$tool = $payload['tool_name'] ?? '';
$input = $payload['tool_input'] ?? [];
$file = $input['file_path'] ?? '(unknown)';
$stateDir = __DIR__ . '/.state';
@mkdir($stateDir, 0777, true);
$editsFile = $stateDir . '/edits_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($payload['session_id'] ?? 'unknown')) . '.json';
$edits = [];
if (is_file($editsFile)) {
    $decoded = json_decode((string)@file_get_contents($editsFile), true);
    if (is_array($decoded)) $edits = $decoded;
}
$edits[$file] = time();
if (count($edits) > 300) {
    arsort($edits);
    $edits = array_slice($edits, 0, 300, true);
}
@file_put_contents($editsFile, json_encode($edits), LOCK_EX);
$diff = buildDiff($tool, $input);
$base = getenv('ANTHROPIC_BASE_URL');
$ollama = is_string($base) && strpos($base, '11434') !== false;
$subagent = getenv('FATEH_REVIEW_MODE') === 'subagent';
if ($ollama && !$subagent) {
    gateReview(classifyTier($input, $tool, $file), $diff, $file);
} elseif ($ollama && $subagent) {
    logLine("subagent mode: per-write gate inert file=$file (unit-review subagent owns review)");
}
exit(0);