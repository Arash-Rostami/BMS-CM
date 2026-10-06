<?php

namespace App\Filament\Resources\Operational\PaymentResource\Traits;

use App\Models\BankProfile;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Services\CodeGenerator;

trait PreparePaymentFromTargetable
{
    public function afterFillFromTargetable(): void
    {
        $map = [
            'purchase_order_id' => PurchaseOrder::class,
            'registered_order_id' => RegisteredOrder::class,
            'bank_profile_id' => BankProfile::class,
        ];

        $requestParams = request()->query();

        foreach ($map as $param => $class) {
            $id = $requestParams[$param] ?? null;

            if (empty($id)) {
                continue;
            }

            $model = $class::find($id);

            if (! $model) {
                continue;
            }

            $data = static::prepareData($model, []);
            $data['targetable_type'] = $class;
            $data['targetable_id'] = $id;

            $this->form->fill($data);

            return;
        }
    }

    protected static function prepareData($targetModel, array $dataToFill): array
    {
        if (! $targetModel) {
            return $dataToFill;
        }

        $dataToFill = static::copyTargetableAttributes($targetModel, $dataToFill);
        $dataToFill['payment_no'] = CodeGenerator::generate('payment_no') ?? null;
        $dataToFill['payment_date'] = now();

        return $dataToFill;
    }

    protected static function copyTargetableAttributes($targetModel, array $dataToFill): array
    {
        $dataToFill['payee_id'] = $targetModel->seller_id ?? null;
        $dataToFill['payor_id'] = $targetModel->company_id ?? $targetModel->buyer_id ?? null;
        $dataToFill['bank_id'] = $targetModel->bank_id ?? null;
        $dataToFill['currency_id'] = $targetModel->currency_id ?? null;

        return $dataToFill;
    }
}
