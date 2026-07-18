<?php

$text = <<<'TEXT'
Documentation-sync policy for this project: whenever you establish, confirm, or discover a new reusable pattern, convention, or key learning during this session that is relevant to this project architecture, update the relevant module documentation file yourself, for example app/Filament/filamentPattern.md, resources/css/stylesPattern.md, resources/js/scriptPattern.md, or any other module-level *.md that documents patterns and conventions. Append or edit concisely, matching the existing style and structure of that doc, and only add what is genuinely new, never duplicate what is already documented. The PostToolUse reviewer only checks literal code correctness and has no file access, so it never flags documentation drift, this check is entirely your own responsibility based on what you actually changed. Do this proactively as part of completing the task, not only when explicitly asked. This keeps project documentation synchronized with the actual codebase over time.
TEXT;

echo json_encode(['hookSpecificOutput' => ['hookEventName' => 'SessionStart', 'additionalContext' => $text]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);