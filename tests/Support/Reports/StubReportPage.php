<?php

namespace Tests\Support\Reports;

use App\Filament\Pages\Reports\ReportPage;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StubReportPage extends ReportPage
{
    public static function permission(): string
    {
        return 'view_fx_supply';
    }

    public static function service(): string
    {
        return StubReportService::class;
    }

    public static function key(): string
    {
        return 'fx_supply';
    }

    public static function register(): void
    {
        Filament::getCurrentOrDefaultPanel()->pages([static::class]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([static::getRegisteredOrderField(), static::getBankField()]);
    }

    public function table(Table $table): Table
    {
        return $this->reportTable($table)->columns([
            TextColumn::make('id'),
            TextColumn::make('bp_number'),
        ]);
    }

    protected function attentionWidget(): ?string
    {
        return StubAttentionWidget::class;
    }
}
