<?php

namespace App\Services\Reports\Checks;

use Closure;

final readonly class Check
{
    public function __construct(
        public string $key,
        public string $model,
        public string $severity,
        public array $reports,
        public Closure $query,
        public Closure $offending,
        public Closure $expected,
        public array $fix,
        public ?Closure $systemFix = null,
    ) {}

    public function messageKey(): string
    {
        return "resources/report/strings.attention.checks.{$this->key}.message";
    }

    public function expectedKey(): string
    {
        return "resources/report/strings.attention.checks.{$this->key}.expected";
    }
}
