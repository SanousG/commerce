<?php

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionKey::SettingsManage->value) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }
}
