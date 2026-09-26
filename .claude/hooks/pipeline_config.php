<?php

return [
    'stack' => 'laravel',
    'skills' => ['.claude/skills/code-reviewer/SKILL.md', '.claude/skills/laravel-performance/SKILL.md'],
    'serious_dirs' => '#(^|[\\\\/])(?:app[\\\\/](?:Services|Actions|Policies|Http[\\\\/]Middleware|Jobs|Models|Filament|Livewire|Events|Observers))([\\\\/]|$)#i',
    'code_roots' => ['app', 'tests', 'resources', 'config', 'database', 'routes'],
];