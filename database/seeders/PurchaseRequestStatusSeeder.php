<?php

namespace Database\Seeders;

use App\Models\PurchaseRequest;
use App\Models\Status;
use Illuminate\Database\Seeder;

class PurchaseRequestStatusSeeder extends Seeder
{
    private const COMMERCIAL_APPROVAL = 'status.grant_purchase_request_commercial_manager_approval';

    private const SALES_APPROVAL = 'status.grant_purchase_request_sales_manager_approval';

    public function run(): void
    {
        $type = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->value('type') ?? 'وضعیت درخواست خرید';

        foreach ($this->stages() as $stage) {
            $status = Status::firstOrNew([
                'english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
                'english_name' => $stage['english_name'],
            ]);

            if (! $status->exists) {
                $status->fill(['type' => $type, 'name' => $stage['name']]);
            }

            $status->stage_order = $stage['stage_order'];
            $status->approval_permission = $stage['approval_permission'];
            $status->save();
        }
    }

    private function stages(): array
    {
        return [
            ['english_name' => 'Submitted', 'name' => 'ارسال شده', 'stage_order' => 1, 'approval_permission' => null],
            ['english_name' => 'Under Review', 'name' => 'در حال بررسی', 'stage_order' => 2, 'approval_permission' => null],
            ['english_name' => 'Sales Manager Approval', 'name' => 'تاییدیه ابتدایی', 'stage_order' => 3, 'approval_permission' => self::SALES_APPROVAL],
            ['english_name' => 'Authorized', 'name' => 'تاییدیه نهایی', 'stage_order' => 4, 'approval_permission' => self::COMMERCIAL_APPROVAL],
        ];
    }
}
