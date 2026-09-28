<?php

namespace App\Modules\Fiscal\Application;

use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use App\Modules\Fiscal\Domain\Models\FiscalSeries;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveFiscalSeries
{
    public function create(Tenant $tenant, Store $store, array $data): FiscalSeries
    {
        return $this->persist($tenant, $store, null, $data);
    }

    public function update(Tenant $tenant, Store $store, FiscalSeries $series, array $data): FiscalSeries
    {
        return $this->persist($tenant, $store, $series, $data);
    }

    private function persist(Tenant $tenant, Store $store, ?FiscalSeries $series, array $data): FiscalSeries
    {
        try {
            return DB::transaction(function () use ($tenant, $store, $series, $data): FiscalSeries {
                $lockedSeries = $series === null ? null : FiscalSeries::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('store_id', $store->getKey())
                    ->lockForUpdate()
                    ->findOrFail($series->getKey());

                if ($data['status'] === FiscalConfigurationStatus::Active->value) {
                    $conflict = FiscalSeries::query()
                        ->where('tenant_id', $tenant->getKey())
                        ->where('store_id', $store->getKey())
                        ->where('document_type', $data['document_type'])
                        ->where('status', FiscalConfigurationStatus::Active->value)
                        ->when($lockedSeries, fn ($query) => $query->whereKeyNot($lockedSeries->getKey()))
                        ->lockForUpdate()
                        ->exists();

                    if ($conflict) {
                        throw ValidationException::withMessages([
                            'status' => 'An active series already exists for this store and document type.',
                        ]);
                    }
                }

                if ($lockedSeries === null) {
                    return FiscalSeries::query()->create([
                        'tenant_id' => $tenant->getKey(),
                        'store_id' => $store->getKey(),
                        ...$data,
                        'next_number' => 1,
                    ]);
                }

                if ($lockedSeries->documents()->exists()
                    && ($lockedSeries->document_type->value !== $data['document_type']
                        || $lockedSeries->series !== $data['series']
                        || $lockedSeries->number_padding !== $data['number_padding'])) {
                    throw ValidationException::withMessages([
                        'series' => 'Document type, series, and padding cannot change after a number has been issued.',
                    ]);
                }

                $lockedSeries->update($data);

                return $lockedSeries->refresh();
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505'
                && str_contains($exception->getMessage(), 'fiscal_series_one_active_per_store_type')) {
                throw ValidationException::withMessages([
                    'status' => 'An active series already exists for this store and document type.',
                ]);
            }

            throw $exception;
        }
    }
}
