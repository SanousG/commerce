<?php

namespace App\Modules\Stores\Http\Requests;

use App\Modules\Stores\Application\StoreAccess;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(StoreAccess::class)->canUpdate($this->user(), app(TenantContext::class)->get());
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::enum(StoreStatus::class)->only([StoreStatus::Active, StoreStatus::Inactive])],
            'currency' => ['required', 'string', Rule::in(['PEN', 'USD', 'EUR'])],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}_[A-Z]{2}$/'],
        ];
    }
}
