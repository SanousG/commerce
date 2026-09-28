<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;

class UpdateStorePaymentSettings
{
    public function handle(Tenant $tenant, Store $store, array $data): StorePaymentSettings
    {
        $type = CodDepositType::from($data['cod_deposit_type']);
        $value = $type === CodDepositType::None ? '0.0000' : $data['cod_deposit_value'];

        return StorePaymentSettings::query()->updateOrCreate(
            ['store_id' => $store->getKey()],
            [
                'tenant_id' => $tenant->getKey(),
                'bank_transfer_enabled' => $data['bank_transfer_enabled'],
                'cash_enabled' => $data['cash_enabled'],
                'online_enabled' => $data['online_enabled'],
                'cod_enabled' => $data['cod_enabled'],
                'cod_deposit_type' => $type,
                'cod_deposit_value' => $value,
            ],
        );
    }
}
