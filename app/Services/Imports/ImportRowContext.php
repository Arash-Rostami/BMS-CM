<?php

namespace App\Services\Imports;

use Illuminate\Database\Eloquent\Model;

final class ImportRowContext
{
    /** @var ImportColumnDefinition[] */
    public array $columns = [];

    public array $pendingChildRows = [];

    public array $pendingExtraAttributes = [];

    public array $pendingPivotAttaches = [];

    public array $rawData = [];

    public function __construct(
        public readonly Model $record,
        public readonly array $options = [],
    ) {}

    public function column(string $name): ?ImportColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }
}
