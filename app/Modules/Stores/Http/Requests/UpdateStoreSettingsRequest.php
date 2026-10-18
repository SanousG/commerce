<?php

namespace App\Modules\Stores\Http\Requests;

use App\Modules\Stores\Application\StoreAccess;
use App\Modules\Tenancy\Application\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreSettingsRequest extends FormRequest
{
    private const SOCIAL_HOSTS = [
        'facebook' => ['facebook.com'],
        'instagram' => ['instagram.com'],
        'tiktok' => ['tiktok.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'whatsapp' => ['wa.me', 'whatsapp.com'],
    ];

    public function authorize(): bool
    {
        return $this->user() !== null
            && app(StoreAccess::class)->canUpdate($this->user(), app(TenantContext::class)->get());
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:120'],
            'short_description' => ['nullable', 'string', 'max:280'],
            'contact_email' => ['nullable', 'email:rfc', 'max:254'],
            'contact_phone' => ['nullable', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'whatsapp_phone' => ['nullable', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'country_code' => ['required', 'string', Rule::in(['PE'])],
            'postal_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9 -]+$/'],
            'currency' => ['required', 'string', Rule::in(['PEN', 'USD', 'EUR'])],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}_[A-Z]{2}$/'],
            'social_links' => ['required', 'array:facebook,instagram,tiktok,youtube,whatsapp'],
            ...collect(array_keys(self::SOCIAL_HOSTS))->mapWithKeys(fn (string $channel): array => [
                "social_links.{$channel}" => ['nullable', 'url:https', 'max:500', $this->socialUrl($channel)],
            ])->all(),
            'logo_media_asset_id' => ['nullable', 'uuid'],
            'favicon_media_asset_id' => ['nullable', 'uuid'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'store_updated_at' => ['required', 'date'],
            'settings_updated_at' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'display_name', 'short_description', 'contact_email', 'contact_phone', 'whatsapp_phone',
            'address_line_1', 'address_line_2', 'district', 'province', 'department', 'postal_code',
            'logo_media_asset_id', 'favicon_media_asset_id', 'secondary_color',
        ];
        $normalized = [];
        foreach ($nullable as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
        foreach (['contact_phone', 'whatsapp_phone'] as $field) {
            if (is_string($normalized[$field])) {
                $normalized[$field] = preg_replace('/[\s().-]+/', '', $normalized[$field]);
            }
        }
        $social = collect((array) $this->input('social_links', []))->map(
            fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null,
        )->all();

        $this->merge([
            ...$normalized,
            'contact_email' => is_string($normalized['contact_email']) ? mb_strtolower($normalized['contact_email']) : null,
            'country_code' => strtoupper((string) $this->input('country_code')),
            'currency' => strtoupper((string) $this->input('currency')),
            'primary_color' => strtoupper((string) $this->input('primary_color')),
            'secondary_color' => is_string($normalized['secondary_color']) ? strtoupper($normalized['secondary_color']) : null,
            'social_links' => $social,
        ]);
    }

    private function socialUrl(string $channel): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($channel): void {
            if ($value === null) {
                return;
            }
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            $allowed = collect(self::SOCIAL_HOSTS[$channel])->contains(
                fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain),
            );
            if (! $allowed) {
                $fail("The {$channel} URL must use an official {$channel} domain.");
            }
        };
    }
}
