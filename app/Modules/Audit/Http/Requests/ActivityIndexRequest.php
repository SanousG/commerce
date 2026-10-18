<?php

namespace App\Modules\Audit\Http\Requests;

use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ActivityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PermissionChecker::class)->userCan($user, 'audit.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'actor_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
                if (in_array($value, ['all', 'system'], true)) {
                    return;
                }

                if (! ctype_digit((string) $value) || ! DB::table('tenant_members')
                    ->where('tenant_id', $tenantId)->where('user_id', $value)->exists()) {
                    $fail('The selected actor is not a tenant member.');
                }
            }],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'subject_type' => ['nullable', Rule::enum(AuditSubjectType::class)],
            'subject_id' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
                if (in_array($value, ['all', 'current'], true)) {
                    return;
                }

                if (! is_string($value) || ! Str::isUuid($value) || ! DB::table('stores')
                    ->where('tenant_id', $tenantId)->where('id', $value)->exists()) {
                    $fail('The selected store does not belong to the tenant.');
                }
            }],
        ];
    }
}
