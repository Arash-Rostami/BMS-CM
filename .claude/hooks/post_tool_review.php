<?php

$THRESHOLD = 93;
$OLLAMA_URL = 'http://localhost:11434/api/chat';
$OLLAMA_MODEL = 'glm-5.2:cloud';
$FALLBACK_MODEL = 'glm-5.1:cloud';
$ANTHROPIC_MODEL = 'claude-sonnet-4-6';
$LOG = __DIR__ . '/../review.log';

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

function ollamaCall(array $prompts, ?string $model = null): array
{
    global $OLLAMA_URL, $OLLAMA_MODEL;
    $model = $model ?? $OLLAMA_MODEL;
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

function ollamaCallFallback(string $prompt): ?array
{
    global $OLLAMA_MODEL, $FALLBACK_MODEL;
    $r = ollamaCall([$prompt], $OLLAMA_MODEL)[0] ?? null;
    if (empty($r)) $r = ollamaCall([$prompt], $FALLBACK_MODEL)[0] ?? null;
    return $r;
}

function anthropicCall(string $prompt, ?string $key, ?string $base): ?string
{
    global $ANTHROPIC_MODEL;
    $url = rtrim($base ?? 'https://api.anthropic.com', '/') . '/v1/messages';
    $payload = json_encode([
        'model' => $ANTHROPIC_MODEL,
        'max_tokens' => 1024,
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ], JSON_UNESCAPED_SLASHES);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . ($key ?? ''),
        'anthropic-version: 2023-06-01',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false || $res === null) return null;
    $data = json_decode($res, true);
    return $data['content'][0]['text'] ?? null;
}

function emitBlock(string $reason): void
{
    echo json_encode(['decision' => 'block', 'reason' => $reason], JSON_UNESCAPED_SLASHES);
    exit(0);
}

function commonPreamble(string $diff): string
{
    return "You are reviewing a code change in a Laravel 12 + Filament v5 + Livewire codebase. Project rules: NO code comments are allowed (flag any comment introduced). Focus on the DIFF; the full file is attached as context only, not for judging unrelated code. Dry-run trace the changed code in your reasoning to verify control flow, edge cases, and integration.\n\n" . $diff . "\n\nOutput STRICT JSON only: {\"verdict\":\"pass|fail\",\"issues\":[\"concrete problem\"],\"confidence\":0-100,\"dry_run_notes\":\"trace summary\"}. verdict=pass only if no real issue is present. confidence = your confidence this change is safe to ship (0-100); a clean correct change should score 93 or higher, score below 93 only when you can name a concrete concern.";
}

function reviewerA(string $diff): string
{
    return "Your review lens: correctness, logic bugs, unhandled edge cases, and security (injection, XSS, missing auth check, data leakage). " . commonPreamble($diff);
}

function reviewerB(string $diff): string
{
    return "Your review lens: performance (queries inside loops, missing eager loads causing N+1, unbounded queries on large tables, repeated container resolution), pattern-consistency (Action/Validator/Presenter/Service classes where the project mandates them), minimality, and absence of code comments. " . commonPreamble($diff);
}

function ollamaReview(string $diff, string $file): void
{
    global $THRESHOLD;
    $r1 = ollamaCall([reviewerA($diff), reviewerB($diff)]);
    $a1 = $r1[0] ?? null;
    $b1 = $r1[1] ?? null;
    if (!$a1) $a1 = ollamaCallFallback(reviewerA($diff));
    if (!$b1) $b1 = ollamaCallFallback(reviewerB($diff));
    if (!$a1 || !$b1) {
        logLine("ollama passthrough (infra-fail + fallback-fail) file=$file a1=" . ($a1 ? 'ok' : 'null') . " b1=" . ($b1 ? 'ok' : 'null'));
        exit(0);
    }
    $a2prompt = reviewerA($diff) . "\n\nOther reviewer's round-1 verdict (correctness/security): " . json_encode($b1, JSON_UNESCAPED_SLASHES) . "\nRe-evaluate with this in view; output the same JSON shape.";
    $b2prompt = reviewerB($diff) . "\n\nOther reviewer's round-1 verdict (perf/pattern/comments): " . json_encode($a1, JSON_UNESCAPED_SLASHES) . "\nRe-evaluate with this in view; output the same JSON shape.";
    $r2 = ollamaCall([$a2prompt, $b2prompt]);
    $a = $r2[0] ?? null;
    $b = $r2[1] ?? null;
    if (!$a) $a = ollamaCallFallback($a2prompt);
    if (!$b) $b = ollamaCallFallback($b2prompt);
    if (!$a || !$b) {
        logLine("ollama passthrough (round2 infra-fail + fallback-fail) file=$file");
        exit(0);
    }
    $va = $a['verdict'] ?? 'fail';
    $vb = $b['verdict'] ?? 'fail';
    $ca = (int)($a['confidence'] ?? 0);
    $cb = (int)($b['confidence'] ?? 0);
    $conf = min($ca, $cb);
    $issues = array_merge((array)($a['issues'] ?? []), (array)($b['issues'] ?? []));
    if ($va === 'pass' && $vb === 'pass' && $conf >= $THRESHOLD) {
        logLine("ollama pass file=$file conf=$conf (a=$ca b=$cb)");
        exit(0);
    }
    $aIssues = $va === 'pass' ? 'pass' : implode(' | ', (array)($a['issues'] ?? ['no issues stated']));
    $bIssues = $vb === 'pass' ? 'pass' : implode(' | ', (array)($b['issues'] ?? ['no issues stated']));
    $why = $conf < $THRESHOLD ? "confidence {$conf}% below gate {$THRESHOLD}%" : "confidence {$conf}%";
    $reason = "Post-tool review blocked (verdicts A={$va} B={$vb}; {$why}). Reviewer A (correctness/security): {$aIssues}. Reviewer B (perf/pattern/comments): {$bIssues}. Dry-run A: " . ($a['dry_run_notes'] ?? '-') . " | Dry-run B: " . ($b['dry_run_notes'] ?? '-') . ". Required fixes: " . (empty($issues) ? 'see reviewer notes above' : implode(' | ', $issues));
    logLine("ollama block file=$file conf=$conf (a=$va/$ca b=$vb/$cb)");
    emitBlock($reason);
}

function anthropicReview(string $diff, string $file, ?string $key, ?string $base): void
{
    $prompt = "Check only the literal code change below. You have no access to any other file, so judge only what is shown. Pass if the code is syntactically correct PHP, Blade, or JS, has no obvious bug or unhandled edge case, no obvious security hole (injection, XSS, missing auth check, data leakage), and no obvious performance red flag (query inside a loop, missing eager load causing N+1, unbounded query on an evidently large table). Fail only if one of those is clearly present. Output STRICT JSON: {\"verdict\":\"pass|fail\",\"reason\":\"concrete problem + one or two line fix\"}.\n\nDIFF:\n$diff";
    $text = anthropicCall($prompt, $key, $base);
    if ($text === null) {
        logLine("anthropic passthrough (infra-fail) file=$file");
        exit(0);
    }
    $json = extractJson($text);
    $verdict = is_array($json) ? ($json['verdict'] ?? '') : '';
    if ($verdict === 'fail') {
        $reason = is_array($json) ? ($json['reason'] ?? '') : '';
        if ($reason === '') $reason = $text ?: 'reviewer flagged the change';
        logLine("anthropic block file=$file");
        emitBlock("Post-tool review (claude-sonnet-4-6): {$reason}");
    }
    logLine("anthropic pass file=$file");
    exit(0);
}

$stdin = file_get_contents('php://stdin');
$payload = json_decode($stdin, true);
if (!is_array($payload)) {
    exit(0);
}
$tool = $payload['tool_name'] ?? '';
$input = $payload['tool_input'] ?? [];
$file = $input['file_path'] ?? '(unknown)';
$diff = buildDiff($tool, $input);
$base = getenv('ANTHROPIC_BASE_URL');
$key = getenv('ANTHROPIC_API_KEY');
$ollama = is_string($base) && strpos($base, '11434') !== false;
if ($ollama) {
    ollamaReview($diff, $file);
} else {
    anthropicReview($diff, $file, $key, $base);
}
exit(0);