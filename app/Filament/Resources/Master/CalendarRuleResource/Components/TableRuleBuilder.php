<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Components;

use App\Services\Calendar\CalendarPathResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder\Block;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class TableRuleBuilder extends RuleBuilder
{
    /**
     * @var array<int, string>|null
     */
    protected ?array $pickable = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addAction(function (Action $action): Action {
            $nested = $this->getNestingDepth() > 1;

            if ($nested) {
                $action->link()->size(Size::Small);
            }

            return $action
                ->label(__($nested ? 'resources/calendarRule/strings.form.add_to_set' : 'resources/calendarRule/strings.form.add_condition'))
                ->icon(Heroicon::Plus)
                ->disabled(fn (): bool => $this->isAtRuleLimit())
                ->tooltip(fn (): ?string => $this->getRuleLimitReachedTooltip());
        });
    }

    /**
     * @param  array<int, string>  $names
     */
    public function pickable(array $names): static
    {
        $this->pickable = $names;

        return $this;
    }

    /**
     * @return array<Block>
     */
    public function getBlockPickerBlocks(): array
    {
        $root = $this->getRootRuleBuilder();
        $pickable = $root instanceof self ? $root->pickable : null;

        return array_map(
            fn (Block $block): Block => $block->getName() === static::OR_BLOCK_NAME
                ? $block
                : $block->getClone()->label(Str::afterLast((string) $block->getLabel(), CalendarPathResolver::SEPARATOR)),
            array_filter(
                parent::getBlockPickerBlocks(),
                fn (Block $block): bool => $pickable === null
                    || $block->getName() === static::OR_BLOCK_NAME
                    || in_array($block->getName(), $pickable, true)
            ),
        );
    }
}
