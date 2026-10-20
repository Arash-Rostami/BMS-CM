<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportBanner;
use App\Filament\Pages\Reports\Concerns\HasReportFilters;
use App\Filament\Pages\Reports\Concerns\HasReportTable;
use App\Filament\Traits\HandlesActionExceptions;
use App\Services\Reports\Support\ReportFilters;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

abstract class ReportPage extends Page implements HasTable
{
    use HandlesActionExceptions;
    use HasFiltersForm, InteractsWithTable {
        HasFiltersForm::normalizeTableFilterValuesFromQueryString insteadof InteractsWithTable;
        HasFiltersForm::updatedFilters as persistFilters;
    }
    use HasReportBanner;
    use HasReportFilters;
    use HasReportTable;

    protected string $view = 'filament.pages.reports.report';

    abstract public static function permission(): string;

    abstract public static function service(): string;

    abstract public static function key(): string;

    abstract public function filtersForm(Schema $schema): Schema;

    abstract public function table(Table $table): Table;

    abstract protected function attentionWidget(): ?string;

    /**
     * @return array<string, class-string<self>>
     */
    public static function registry(): array
    {
        return collect(Filament::getPages())
            ->filter(fn (string $page): bool => is_subclass_of($page, self::class))
            ->mapWithKeys(fn (string $page): array => [$page::key() => $page])
            ->all();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('report.'.static::permission()) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/report/strings.pages.'.static::key().'.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.pages.'.static::key().'.heading');
    }

    #[On('calendar-toggled')]
    public function calendarToggled(): void
    {
        $this->redirect(url()->previous());
    }

    #[Url(as: 'q')]
    public ?string $quick = null;

    #[Computed]
    public function filterObject(): ReportFilters
    {
        return ReportFilters::fromArray([...($this->filters ?? []), 'quick' => $this->quick]);
    }

    public function filterTableBy(mixed $key = null): void
    {
        $key = ReportFilters::fromArray(['quick' => $key])->quick;
        $this->quick = $key === $this->quick ? null : $key;
        unset($this->filterObject, $this->quickLabel);
        $this->resetTable();
    }

    public function removeTableFilter(string $filterName, ?string $field = null, bool $isRemovingAllFilters = false): void
    {
        $this->quick = null;
        unset($this->filterObject, $this->quickLabel);

        if (! $isRemovingAllFilters) {
            $this->resetTable();
        }
    }

    #[Computed]
    public function quickLabel(): ?string
    {
        foreach ($this->summary as $card) {
            foreach ($card['lines'] as $line) {
                if ($this->quick !== null && ($line['quick'] ?? null) === $this->quick) {
                    return $card['label'].' — '.$line['label'];
                }
            }
        }

        $key = 'resources/report/strings.quick.keys.'.$this->quick;

        return $this->quick !== null && Lang::has($key) ? __($key) : $this->quick;
    }

    /**
     * @return array<int, array{key: string, label: string, lines: array<int, array{label: string, value: string, quick?: string}>, tone: ?string}>
     */
    #[Computed]
    public function summary(): array
    {
        return app(static::service())->summary($this->filterObject);
    }

    /**
     * @return array{intro: string, sections: array<string, array<string, mixed>>}
     */
    #[Computed]
    public function guide(): array
    {
        $file = 'resources/report/guide_'.static::key();
        $intro = __($file.'.intro');
        $sections = __($file.'.sections');
        $sections = is_array($sections) ? $sections : [];
        $values = $this->thresholdValues();
        array_walk_recursive($sections, function (mixed &$text) use ($values): void {
            $text = is_string($text) ? strtr($text, $values) : $text;
        });

        return [
            'intro' => is_string($intro) && $intro !== $file.'.intro' ? strtr($intro, $values) : '',
            'sections' => $sections,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function thresholdValues(): array
    {
        return collect([...array_keys(ReportFilters::THRESHOLD_DEFAULTS), 'idle'])
            ->mapWithKeys(fn (string $key): array => [":th_{$key}" => (string) $this->filterObject->threshold($key)])
            ->all();
    }

    public function updatedFilters(): void
    {
        $this->persistFilters();
        $this->quick = null;
        unset($this->filterObject, $this->summary, $this->banner, $this->quickLabel, $this->guide);
        $this->resetTable();
        $this->dispatch('report-filters-changed');
    }

    public function hasActiveFilters(): bool
    {
        $ignored = array_flip(['quick', 'view_mode']);

        return array_diff_key(ReportFilters::fromArray($this->filters ?? [])->toArray(), $ignored) != array_diff_key(ReportFilters::fromArray([])->toArray(), $ignored);
    }

    public function resetReportFilters(): void
    {
        $this->filtersForm->fill();
        $this->updatedFilters();
        $this->dispatch('report-severity', severity: null);
    }

    public function analytics(Schema $schema): Schema
    {
        return $schema
            ->components([Grid::make(2)->schema(fn (): array => $this->getWidgetsSchemaComponents($this->analyticsWidgets()))])
            ->hidden(fn (): bool => $this->getWidgetsSchemaComponents($this->analyticsWidgets()) === []);
    }

    protected function analyticsWidgets(): array
    {
        return [];
    }

    public function attention(): ?string
    {
        $widget = $this->attentionWidget();

        return $widget && $widget::canView() ? $widget : null;
    }
}
