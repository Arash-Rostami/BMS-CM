<?php

namespace App\Services\Imports;

use Closure;

final class ImportColumnDefinition
{
    private ?Closure $fallback = null;

    private bool $rejectIfStillBlank = false;

    private bool $includeInTemplate = true;

    private bool $rejectOnMismatch = true;

    public function __construct(
        public readonly string $name,
        public readonly ?string $labelKey = null,
        public readonly ?string $relation = null,
        public readonly ?string $model = null,
        public readonly array $resolveColumns = [],
        public readonly ?string $enumTranslationKey = null,
        public readonly ?string $statusType = null,
        public readonly bool $isNumber = false,
        public readonly bool $isDate = false,
        public readonly bool $isCountry = false,
    ) {}

    public static function match(string $name, string $labelKey, string $relation, string $model, array $resolveColumns): self
    {
        return new self($name, labelKey: $labelKey, relation: $relation, model: $model, resolveColumns: $resolveColumns);
    }

    public static function matchStatus(string $name, string $labelKey, string $statusType): self
    {
        return new self($name, labelKey: $labelKey, statusType: $statusType);
    }

    public static function matchEnum(string $name, string $labelKey, string $enumTranslationKey): self
    {
        return new self($name, labelKey: $labelKey, enumTranslationKey: $enumTranslationKey);
    }

    public static function matchCountry(string $name, string $labelKey): self
    {
        return new self($name, labelKey: $labelKey, isCountry: true);
    }

    public static function manualSet(string $name, string $labelKey, bool $isNumber = false, bool $isDate = false): self
    {
        return (new self($name, labelKey: $labelKey, isNumber: $isNumber, isDate: $isDate))
            ->withFallback(fn () => null, includeInTemplate: true, rejectIfStillBlank: true);
    }

    public static function optional(string $name, string $labelKey, bool $isNumber = false, bool $isDate = false, bool $includeInTemplate = true): self
    {
        $definition = new self($name, labelKey: $labelKey, isNumber: $isNumber, isDate: $isDate);
        $definition->includeInTemplate = $includeInTemplate;

        return $definition;
    }

    public function withFallback(Closure $fallback, ?bool $includeInTemplate = null, bool $rejectIfStillBlank = false): self
    {
        $this->fallback = $fallback;
        $this->includeInTemplate = $includeInTemplate ?? $this->includeInTemplate;
        $this->rejectIfStillBlank = $rejectIfStillBlank;

        return $this;
    }

    public function allowNullOnMismatch(): self
    {
        $this->rejectOnMismatch = false;

        return $this;
    }

    public function rejectOnMismatch(): bool
    {
        return $this->rejectOnMismatch;
    }

    public function hasMatchConfig(): bool
    {
        return $this->relation !== null || $this->statusType !== null || $this->enumTranslationKey !== null || $this->isCountry;
    }

    public function hasFallback(): bool
    {
        return $this->fallback !== null;
    }

    public function resolveFallback(ImportRowContext $context): mixed
    {
        return $this->fallback ? ($this->fallback)($context) : null;
    }

    public function rejectIfStillBlank(): bool
    {
        return $this->rejectIfStillBlank;
    }

    public function includeInTemplate(): bool
    {
        return $this->includeInTemplate;
    }
}
