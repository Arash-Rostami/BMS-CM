<?php

$config = require __DIR__ . '/pipeline_config.php';

$base = getenv('ANTHROPIC_BASE_URL') ?: '';
$isOllama = str_contains($base, '11434');

$skills = implode(' and ', array_map('basename', array_map(fn (string $s) => dirname($s), $config['skills'])));
$skillText = implode(', ', $config['skills']);
$text = 'Before doing anything else in this session, carefully read and internalize the skills at '
    . $skillText
    . '. Summarize the key review and performance rules in your own words, then follow them strictly for all future actions in this session.';

if ($isOllama) {
    $ollamaSkill = '.claude/skills/ollama/SKILL.md';
    $text = str_replace(
        'the skills at ' . $skillText,
        'the skills at ' . $skillText . ', plus ' . $ollamaSkill,
        $text
    );
    $text = str_replace('review and performance rules', 'review, performance, and multi-agent orchestration rules', $text);
}

$text .= ' This is reinforced as a hard prerequisite by CLAUDE.md at the repo root, which outranks this injected context on ordering and priority.';

echo json_encode(['hookSpecificOutput' => ['hookEventName' => 'SessionStart', 'additionalContext' => $text]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);