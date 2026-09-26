<?php

$base  = getenv('ANTHROPIC_BASE_URL') ?: '';
$envModel = getenv('ANTHROPIC_MODEL') ?: '';
$leadModel = getenv('FATEH_LEAD_MODEL') ?: '';
$host  = strtolower((string) parse_url($base, PHP_URL_HOST));
$port  = (int) parse_url($base, PHP_URL_PORT);

$isLocalOllama = ($host === 'localhost' || $host === '127.0.0.1' || $host === '0.0.0.0') && ($port === 11434 || str_contains($base, '11434'));
$isAnthropic   = $base === '' || str_contains($host, 'api.anthropic.com');

if ($isLocalOllama) {
    $pipeline = 'Ollama';
    $model = $leadModel !== '' ? $leadModel : $envModel;
    $workflow = 'Ollama-Native — the launched Lead model (FATEH_LEAD_MODEL) is the Lead orchestrator; delegation policy inert (cannot self-delegate); .claude/skills/ollama/SKILL.md governs non-trivial work via the lean lane pipeline with tiered review gates.';
} elseif ($isAnthropic) {
    $pipeline = 'Claude';
    $model = $envModel;
    $workflow = 'Anthropic-Native — you orchestrate directly against the Anthropic API; delegation policy active (claude-planner / claude-coder / claude-reviewer subagents); complex work closes with a claude-reviewer pass.';
} else {
    $pipeline = 'Unknown provider';
    $model = $envModel;
    $workflow = 'Provider not recognized from ANTHROPIC_BASE_URL — report the raw base URL and model, and ask the user which workflow applies before proceeding.';
}

$text = <<<TEXT
Session self-introduction policy for this project: at the very start of the session, before any other work, introduce yourself in one or two short lines. Detection is deterministic from the environment — do NOT guess or override it.

- Active pipeline: {$pipeline}
- Base URL: {$base}
- Model id: {$model}
- Workflow: {$workflow}

Greeting format: "<Pipeline> agent — <exact model id>", e.g. "Ollama agent — glm-5.3-flash:cloud" or "Claude agent — Sonnet 5". The model id must be concrete: when env vars are unset, take it from the model declaration in your own environment context (the "You are powered by ..." line) instead of falling back to a pipeline-only label. Never greet as just "Ollama agent" or "Claude agent" without naming the exact model. If the pipeline is "Unknown provider", surface the raw base URL and ask which workflow applies rather than assuming. Keep it brief — this only makes the active operating mode explicit up front; do not list every policy detail.
TEXT;

echo json_encode(['hookSpecificOutput' => ['hookEventName' => 'SessionStart', 'additionalContext' => $text]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);