<?php

namespace Tests\Support\Reports;

use App\Models\BankProfile;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Builders\ReportService;
use Illuminate\Database\Eloquent\Builder;

class StubReportService implements ReportService
{
    public function query(ReportFilters $filters): Builder
    {
        return BankProfile::query()
            ->when($filters->registeredOrderId, fn (Builder $query, int $id) => $query->where('registered_order_id', $id))
            ->when($filters->bankId, fn (Builder $query, int $id) => $query->where('bank_id', $id));
    }

    public function summary(ReportFilters $filters): array
    {
        return [[
            'key' => 'profiles',
            'label' => 'Stub profiles',
            'lines' => [['label' => 'Stub rows', 'value' => (string) $this->query($filters)->count()]],
            'tone' => 'info',
        ]];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->query($filters)->orderBy('id')->lazy()->map(fn (BankProfile $profile): array => [
            'id' => $profile->id,
            'bp_number' => $profile->bp_number,
            'ignored' => 'must not be exported',
        ]);
    }

    public function exportLabels(): array
    {
        return ['id' => 'ID', 'bp_number' => 'BP Number'];
    }
}
