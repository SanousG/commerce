<?php

namespace App\Modules\Stores\Http\Requests;

use App\Modules\Stores\Application\StoreAccess;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null
            && $this->user() !== null
            && app(StoreAccess::class)->canCreate($this->user(), $tenant);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', Rule::in(['PEN', 'USD', 'EUR'])],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}_[A-Z]{2}$/'],
        ];
    }
}
