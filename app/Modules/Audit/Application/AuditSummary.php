<?php

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\Models\AuditEntry;

final class AuditSummary
{
    public function for(AuditEntry $entry): string
    {
        $actor = $entry->actor?->name ?? ($entry->actor_type->value === 'SYSTEM' ? 'System' : 'Deleted user');
        $action = str($entry->action->value)->replace(['.', '_'], ' ')->headline()->lower();
        $subject = str($entry->subject_type->value)->replace('_', ' ')->lower();

        return "{$actor} {$action} {$subject} {$entry->subject_id}.";
    }
}
