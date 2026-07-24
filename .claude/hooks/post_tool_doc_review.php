<?php

$THRESHOLD = 93;
$OLLAMA_URL = 'http://localhost:11434/api/chat';
$OLLAMA_MODEL = 'glm-5.2:cloud';
$FALLBACK_MODEL = 'glm-5.1:cloud';
$ANTHROPIC_MODEL = 'claude-sonnet-4-6';
$LOG = __DIR__ . '/../doc_review.log';
$REPO_ROOT = dirname(__DIR__, 2);
$MAX_IDENTIFIERS = 12;

function logLine(string $line): void
{
    global $LOG;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($LOG, "[$ts] $line\n", FILE_APPEND);
}

function isDocFile(string $file): bool
{
    $base = basename(str_replace('\\', '/', $file));
    if (strcasecmp($base, 'CLAUDE.md') === 0) return true;
    if (preg_match('/Pattern\.md$/i', $base)) return true;
    return false;
}

function fileContext(string $file): string
{
    $real = realpath($file);
    if ($real === false || !is_file($real)) return '';
    $contents = @file_get_contents($real);
    if ($contents === false || $contents === '') return '';
    $lines = explode("\n", $contents);
    if (count($lines) > 900) {
        $contents = implode("\n", array_slice($lines, 0, 900)) . "\n... (truncated, " . count($lines) . " lines total)";
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

function changedText(string $tool, array $input): string
{
    if ($tool === 'Write') return (string)($input['content'] ?? '');
    return (string)($input['new_string'] ?? '');
}

function extractIdentifiers(string $text): array
{
    global $MAX_IDENTIFIERS;
    preg_match_all('/`([^`\n]{2,80})`/', $text, $m);
    $out = [];
    foreach (array_unique($m[1]) as $c) {
        $c = trim($c);
        if ($c === '' || substr_count($c, ' ') > 2) continue;
        $looksCodey = preg_match('#[/\\\\]#', $c)
            || strpos($c, '::') !== false
            || strpos($c, '->') !== false
            || strpos($c, '(') !== false
            || preg_match('/^[A-Z][A-Za-z0-9]*$/', $c)
            || preg_match('/^[a-z][a-zA-Z0-9]*_[a-zA-Z0-9_]*$/', $c)
            || preg_match('/^\$[a-zA-Z_]/', $c);
        if ($looksCodey) $out[] = $c;
        if (count($out) >= $MAX_IDENTIFIERS) break;
    }
    return $out;
}

function verifyIdentifier(string $repoRoot, string $token): string
{
    $pathLike = strpos($token, '/') !== false && strpos($token, ' ') === false;
    if ($pathLike) {
        $abs = $repoRoot . '/' . ltrim($token, '/');
        if (is_file($abs) || is_dir($abs)) {
            return "`$token` -> found (path exists on disk)";
        }
    }
    $term = preg_replace('/\(\)$/', '', $token);
    $term = trim($term, ":->$ \t");
    if ($term === '' || strlen($term) < 2) {
        return "`$token` -> skipped (too short to grep meaningfully)";
    }
    $cmd = sprintf(
        'git -C %s grep -I -l -F -- %s -- . %s %s 2>&1',
        escapeshellarg($repoRoot),
        escapeshellarg($term),
        escapeshellarg(':!vendor'),
        escapeshellarg(':!node_modules')
    );
    $output = [];
    $code = 0;
    @exec($cmd, $output, $code);
    if ($code === 0 && count($output) > 0) {
        $n = count($output);
        return "`$token` -> found in $n file(s)";
    }
    if ($code === 1) {
        return "`$token` -> NOT FOUND anywhere in the repo (grep returned zero matches)";
    }
    return "`$token` -> verification unavailable (grep error, treat as unverified, not as a failure signal)";
}

function buildVerificationReport(string $repoRoot, string $changed): string
{
    $ids = extractIdentifiers($changed);
    if (empty($ids)) return "(no backtick-quoted identifiers found in the changed text to check)";
    $lines = [];
    foreach ($ids as $id) {
        $lines[] = verifyIdentifier($repoRoot, $id);
    }
    return implode("\n", $lines);
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

function commonDocPreamble(string $diff, string $verification): string
{
    return "You are reviewing an update to an AI-agent-facing architecture/pattern documentation file (a *.md \"pattern doc\" or the root CLAUDE.md) in a Laravel 12 + Filament v4 codebase. Future AI coding sessions treat these docs as ground truth without re-deriving it from source, so an inaccurate or bloated doc silently misleads every future session that trusts it — treat that as a real defect, not a style nitpick.\n\nProject conventions for these docs (enforce strictly):\n- Terse, table/list-driven reference, NOT a narrative or dated changelog (e.g. \"on 2026-07-25 we tried X then Y\") — CLAUDE.md's own header explicitly bans dated-diary entries in favor of durable, in-place facts.\n- Single source of truth per domain: this project just consolidated CSS into stylesPattern.md, JS/Alpine into scriptPattern.md, models into modelsPattern.md, Filament resources into filamentPattern.md, services into servicesPattern.md, localization into localizationPattern.md, dashboard widgets into widgetsPattern.md, and views into viewsPattern.md — every other file now holds only a one-line pointer to the owning doc. New content belonging to one of those domains must not be re-added elsewhere as anything more than a one-line pointer.\n- No code comments inside any code snippet shown in the doc.\n- No padding: no hedging, no repeated caveats, no restating a section header in prose, no explaining something a competent Laravel/Filament dev doesn't need spelled out.\n\nStatic verification — grep run against the live repo for every backtick-quoted identifier found in the new/changed text:\n$verification\n\n$diff\n\nOutput STRICT JSON only: {\"verdict\":\"pass|fail\",\"issues\":[\"concrete problem\"],\"confidence\":0-100}. verdict=pass only if no real issue is present. confidence = your confidence this doc update is accurate, non-redundant, and correctly scoped (0-100); a clean, verified, well-scoped update should score 93 or higher, score below 93 only when you can name a concrete concern.";
}

function reviewerA(string $diff, string $verification): string
{
    return "Your review lens: factual accuracy. Judge whether every concrete, checkable claim in the new/changed text (file paths, class/method/trait names, config keys, exact counts, specific behavior claims) is plausible and internally consistent with the rest of the attached file. Weight the static verification results heavily: a NOT-FOUND result on an identifier central to a new claim is a strong signal of a stale or fabricated fact — fail on it unless the surrounding text makes clear it's describing something intentionally new/proposed rather than an existing fact. Also flag any claim that contradicts something else already stated elsewhere in the same file. " . commonDocPreamble($diff, $verification);
}

function reviewerB(string $diff, string $verification): string
{
    return "Your review lens: doc hygiene — redundancy, scope, and style. Flag: (1) narrative or dated changelog-style prose instead of a flat durable fact or rule; (2) content that duplicates, or belongs in, a different domain's pattern doc per this project's single-source-of-truth convention (a one-line cross-reference pointer is fine, a re-explanation is not); (3) code comments inside any snippet; (4) padding, hedging, or verbose restatement of the obvious. " . commonDocPreamble($diff, $verification);
}

function ollamaReview(string $diff, string $verification, string $file): void
{
    global $THRESHOLD;
    $r1 = ollamaCall([reviewerA($diff, $verification), reviewerB($diff, $verification)]);
    $a1 = $r1[0] ?? null;
    $b1 = $r1[1] ?? null;
    if (!$a1) $a1 = ollamaCallFallback(reviewerA($diff, $verification));
    if (!$b1) $b1 = ollamaCallFallback(reviewerB($diff, $verification));
    if (!$a1 || !$b1) {
        logLine("ollama passthrough (infra-fail + fallback-fail) file=$file a1=" . ($a1 ? 'ok' : 'null') . " b1=" . ($b1 ? 'ok' : 'null'));
        exit(0);
    }
    $a2prompt = reviewerA($diff, $verification) . "\n\nOther reviewer's round-1 verdict (doc hygiene): " . json_encode($b1, JSON_UNESCAPED_SLASHES) . "\nRe-evaluate with this in view; output the same JSON shape.";
    $b2prompt = reviewerB($diff, $verification) . "\n\nOther reviewer's round-1 verdict (factual accuracy): " . json_encode($a1, JSON_UNESCAPED_SLASHES) . "\nRe-evaluate with this in view; output the same JSON shape.";
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
    $reason = "Doc review blocked (verdicts A={$va} B={$vb}; {$why}). Reviewer A (accuracy): {$aIssues}. Reviewer B (hygiene/redundancy/scope): {$bIssues}. Required fixes: " . (empty($issues) ? 'see reviewer notes above' : implode(' | ', $issues));
    logLine("ollama block file=$file conf=$conf (a=$va/$ca b=$vb/$cb)");
    emitBlock($reason);
}

function anthropicReview(string $diff, string $verification, string $file, ?string $key, ?string $base): void
{
    $prompt = "Review this update to an AI-agent-facing pattern documentation file in a Laravel 12 + Filament v4 codebase. These docs are the sole reference future AI sessions trust, so treat inaccurate or bloated docs as a real defect.\n\nProject conventions: terse, table/list-driven, no narrative or dated changelog framing, single source of truth per domain (CSS/JS/models/Filament/services/localization/widgets/views each own exactly one authoritative pattern doc — everything else is a one-line pointer, not a re-explanation), no code comments in snippets, no padding.\n\nStatic verification (grep against the live repo for backtick-quoted identifiers in the new text):\n$verification\n\nFail if: a claim is contradicted by a NOT-FOUND verification result (unless clearly describing something new/proposed), the change duplicates content that belongs in another domain's pattern doc, it reads as a dated narrative instead of a durable fact, or it introduces padding/code comments. Pass only if none of those apply.\n\n$diff\n\nOutput STRICT JSON: {\"verdict\":\"pass|fail\",\"reason\":\"concrete problem + one line fix\"}.";
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
        emitBlock("Doc review (claude-sonnet-4-6): {$reason}");
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
if (!isDocFile($file)) {
    exit(0);
}
$diff = buildDiff($tool, $input);
$verification = buildVerificationReport($REPO_ROOT, changedText($tool, $input));
$base = getenv('ANTHROPIC_BASE_URL');
$key = getenv('ANTHROPIC_API_KEY');
$ollama = is_string($base) && strpos($base, '11434') !== false;
if ($ollama) {
    ollamaReview($diff, $verification, $file);
} else {
    anthropicReview($diff, $verification, $file, $key, $base);
}
exit(0);
