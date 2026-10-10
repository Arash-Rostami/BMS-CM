<?php

namespace App\Models\Traits\NotificationSetting;

trait Setting
{
    public function getActions(): array
    {
        return $this->settingList('actions');
    }

    public function getAllSettings(): array
    {
        return $this->settings ?? [];
    }

    public function getColumns(): array
    {
        return $this->settingList('columns');
    }

    /**
     * Per-column value lists keyed by watched column.
     *
     * @return array<string, array<int, int|float|string>>
     */
    public function getColumnValues(): array
    {
        $raw = $this->getValues();
        $columns = $this->getColumns();

        if ($raw === [] || $columns === [] || $this->hasMalformedValues()) {
            return [];
        }

        return array_filter($raw, fn ($values) => $values !== []);
    }

    /**
     * @return array<int, string>
     */
    public function getValueLabels(): array
    {
        $labels = [];

        foreach ($this->getColumnValues() as $column => $values) {
            $labels[] = static::columnLabel($this->getTables(), $column).': '.implode(', ', array_map(fn ($value) => static::displayValue($this->getTables(), $column, $value), $values));
        }

        return $labels;
    }

    public function hasMalformedValues(): bool
    {
        $raw = $this->rawSettings()['values'] ?? [];

        if ($raw === [] || $raw === null) {
            return false;
        }

        if (! is_array($raw)) {
            return true;
        }

        if (array_is_list($raw)) {
            return true;
        }

        $columns = $this->getColumns();

        foreach ($raw as $column => $values) {
            if (! in_array($column, $columns, true) || ! is_array($values) || ! array_is_list($values) || ! $this->isScalarList($values)) {
                return true;
            }
        }

        return false;
    }

    public function getLocalizedActions(): array
    {
        return collect($this->getActions())->map(fn ($action) => match ($action) {
            'create' => __('resources/notificationSetting/strings.action_types.create'),
            'update' => __('resources/notificationSetting/strings.action_types.update'),
            'delete' => __('resources/notificationSetting/strings.action_types.delete'),
            default => $action,
        })->all();
    }

    public function getLocalizedTables(): array
    {
        return array_map(fn ($table) => static::getLocalizedTableLabel($table), $this->getTables());
    }

    public function getTables(): array
    {
        return $this->settingList('tables');
    }

    public function getUsers(): array
    {
        return $this->settingList('users');
    }

    public function getValues(): array
    {
        $values = $this->rawSettings()['values'] ?? [];

        return is_array($values) ? $values : [];
    }

    public function isActive(): bool
    {
        return in_array($this->rawSettings()['is_active'] ?? false, [true, 1, '1', 'true'], true);
    }

    private function rawSettings(): array
    {
        return is_array($this->settings) ? $this->settings : [];
    }

    private function settingList(string $key): array
    {
        $value = $this->rawSettings()[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_scalar')) : [];
    }

    private function isScalarList(array $values): bool
    {
        return count(array_filter($values, 'is_scalar')) === count($values);
    }
}
