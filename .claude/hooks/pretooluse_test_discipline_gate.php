<?php

$input = json_decode(file_get_contents('php://stdin'), true);
if (!is_array($input)) {
    exit(0);
}

$command = $input['tool_input']['command'] ?? null;

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

if (!is_string($command) || $command === '') {
    allow();
}

if (!preg_match('/\bphp\s+artisan\s+test\b/i', $command)) {
    allow();
}

if (preg_match('/--filter\b|--testsuite\b|--group\b/i', $command)) {
    allow();
}

if (str_contains($command, '# full-suite-ok')) {
    allow();
}

deny(
    "Settled project policy (tests/testPattern.md §3b): a whole-app 'php artisan test' run with no --filter/--testsuite/--group scope only runs when the user's own message explicitly asked for a full suite (\"run the full suite\", \"run all tests\"), never on the assistant's own inference. " .
    "Scope this run with --filter <TestClass>/<method> (or --testsuite), or if the user's most recent message genuinely did ask for a full run, append '# full-suite-ok' to the command to proceed."
);
