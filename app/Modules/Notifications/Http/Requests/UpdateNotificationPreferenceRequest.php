<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Application\NotificationRouting;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email_enabled' => ['required', 'boolean'],
            'muted_types' => ['present', 'array'],
            'muted_types.*' => ['string', Rule::enum(NotificationType::class), Rule::notIn(NotificationRouting::MANDATORY_TYPES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'muted_types.*.not_in' => 'This notification type is mandatory and cannot be muted.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $muted = $this->input('muted_types', []);
            if (is_array($muted) && collect($muted)->contains(fn ($type): bool => in_array($type, NotificationRouting::MANDATORY_TYPES, true))) {
                $validator->errors()->add('muted_types', 'Mandatory notification types cannot be muted.');
            }
        });
    }
}
