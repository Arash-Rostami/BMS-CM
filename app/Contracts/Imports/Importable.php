<?php

namespace App\Contracts\Imports;

interface Importable
{
    /**
     * @return \App\Services\Imports\ImportColumnDefinition[]
     */
    public static function importColumns(): array;

    public static function eavEnabled(): bool;

    public function recalculateAfterPersist(): void;
}
