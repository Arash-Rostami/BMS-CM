<?php

$text = <<<'TEXT'
Session self-introduction policy for this project: at the very start of the session, before any other work, introduce yourself to the user in one or two short lines. State the model you are running as, read from your session environment identity line, and declare which workflow you will follow for this session. If the driving model is Claude or another Anthropic model, say you are following the Anthropic Native workflow, meaning you orchestrate, the delegation policy above is active, and code-gen slices can be delegated to glm-5.2:cloud or kimi-k2.7-code:cloud on request. If the driving model is glm-5.2:cloud, say you are following the Ollama Native workflow, meaning GLM-5.2 acts as Lead orchestrator, the delegation policy above is inert since you cannot delegate to yourself, and .claude/skills/ollama/SKILL.md governs non-trivial work via the 5-phase pipeline. Keep it brief, this only makes the active operating mode explicit to the user up front, do not list every policy detail.
TEXT;

echo json_encode(['hookSpecificOutput' => ['hookEventName' => 'SessionStart', 'additionalContext' => $text]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);