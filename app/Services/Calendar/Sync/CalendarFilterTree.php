<?php

namespace App\Services\Calendar\Sync;

use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Illuminate\Validation\ValidationException;

class CalendarFilterTree
{
    /**
     * @throws ValidationException
     */
    public function walkLeaves(mixed $rules, callable $callback): void
    {
        if (! is_array($rules)) {
            $this->throwPreviewValidation('filters');
        }

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                $this->throwPreviewValidation('filters');
            }

            if (($rule['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME) {
                $this->walkOrGroups($rule, $callback);

                continue;
            }

            $callback($rule);
        }
    }

    public function prune(array $rules): array
    {
        $pruned = [];

        foreach ($rules as $key => $rule) {
            $groups = is_array($rule) && ($rule['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME
                ? ($rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? null)
                : null;

            if (is_array($groups)) {
                $rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] = $groups = $this->pruneGroups($groups);

                if ($groups === []) {
                    continue;
                }
            }

            $pruned[$key] = $rule;
        }

        return $pruned;
    }

    public function throwPreviewValidation(string $field): never
    {
        throw ValidationException::withMessages([
            $field => __('resources/calendarRule/strings.form.validation_'.$field),
        ]);
    }

    private function pruneGroups(array $groups): array
    {
        $kept = [];

        foreach ($groups as $key => $group) {
            if (is_array($group) && is_array($group['rules'] ?? null)) {
                $group['rules'] = $this->prune($group['rules']);
            }

            if (! is_array($group) || filled($group['rules'] ?? 'x')) {
                $kept[$key] = $group;
            }
        }

        return $kept;
    }

    private function walkOrGroups(array $block, callable $callback): void
    {
        $groups = $block['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? null;

        if (! is_array($block['data'] ?? null) || ! is_array($groups)) {
            $this->throwPreviewValidation('filters');
        }

        foreach ($groups as $group) {
            if (is_array($group)) {
                $this->walkLeaves($group['rules'] ?? [], $callback);
            }
        }
    }
}
