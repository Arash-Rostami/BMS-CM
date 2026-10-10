<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Components\Operators;

use App\Services\NameSearch;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Illuminate\Database\Eloquent\Model;

class NamedIsRelatedToOperator extends IsRelatedToOperator
{
    public static function for(Model $related): static
    {
        $operator = static::make()->searchable();

        return $operator
            ->getSearchResultsUsing(fn (string $search): array => NameSearch::options($related, $search))
            ->getOptionLabelsUsing(fn (array $values): array => NameSearch::labels($related, $values))
            ->getOptionLabelUsing(fn (mixed $value): ?string => NameSearch::labels($related, [$value])[$value] ?? null);
    }

    public function getSummary(): string
    {
        $constraint = $this->getConstraint();
        $values = (array) $this->getValueSetting();
        $labels = collect(NameSearch::labels($this->getRelationship()->getRelated(), $values))
            ->join(__('filament-query-builder::query-builder.operators.relationship.is_related_to.summary.values_glue.0'), __('filament-query-builder::query-builder.operators.relationship.is_related_to.summary.values_glue.final'));
        $kind = ($constraint->isMultiple() ? 'multiple' : 'single').'.'.($this->isInverse() ? 'inverse' : 'direct');

        return __("filament-query-builder::query-builder.operators.relationship.is_related_to.summary.{$kind}", [
            'relationship' => $constraint->getAttributeLabel(),
            'values' => $labels,
        ]);
    }
}
