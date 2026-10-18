<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unread' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'resolved' => ['sometimes', 'boolean'],
            'severity' => ['sometimes', Rule::enum(NotificationSeverity::class)],
            'type' => ['sometimes', Rule::enum(NotificationType::class)],
        ];
    }
}
