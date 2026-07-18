<?php

$text = <<<'TEXT'
Before doing anything else in this session, carefully read and internalize the skills at .claude/skills/code-reviewer/SKILL.md, .claude/skills/laravel-performance/SKILL.md, and .claude/skills/ollama/SKILL.md. Summarize the key review, performance, and multi-agent orchestration rules in your own words, then follow them strictly for all future actions in this session. This is reinforced as a hard prerequisite by CLAUDE.md at the repo root, which outranks this injected context on ordering and priority.
TEXT;

echo json_encode(['hookSpecificOutput' => ['hookEventName' => 'SessionStart', 'additionalContext' => $text]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);