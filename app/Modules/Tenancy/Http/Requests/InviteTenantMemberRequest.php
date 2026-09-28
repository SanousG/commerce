<?php

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTenantMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionKey::MembersManage->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role_id' => [
                'required',
                'uuid',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->whereNull('tenant_id')->where('is_system', true)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}
