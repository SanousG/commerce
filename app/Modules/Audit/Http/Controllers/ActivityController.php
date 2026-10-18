<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditSummary;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Audit\Domain\Models\AuditEntry;
use App\Modules\Audit\Http\Requests\ActivityIndexRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class ActivityController extends Controller
{
    public function index(
        ActivityIndexRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        AuditSummary $summaries,
    ): Response {
        $tenant = $tenants->get();
        abort_unless($tenant !== null, 404);
        $filters = $request->validated();
        $storeFilter = $filters['store_id'] ?? 'all';

        $query = AuditEntry::query()
            ->where('tenant_id', $tenant->getKey())
            ->with(['actor:id,name,email', 'store:id,name']);

        $query->when(isset($filters['date_from']), fn ($query) => $query->where('occurred_at', '>=', $filters['date_from'].' 00:00:00'));
        $query->when(isset($filters['date_to']), fn ($query) => $query->where('occurred_at', '<=', $filters['date_to'].' 23:59:59.999999'));
        $query->when(($filters['actor_id'] ?? 'all') === 'system', fn ($query) => $query->where('actor_type', 'SYSTEM'));
        $query->when(isset($filters['actor_id']) && ctype_digit((string) $filters['actor_id']), fn ($query) => $query->where('actor_user_id', $filters['actor_id']));
        $query->when(isset($filters['action']), fn ($query) => $query->where('action', $filters['action']));
        $query->when(isset($filters['subject_type']), fn ($query) => $query->where('subject_type', $filters['subject_type']));
        $query->when(isset($filters['subject_id']), fn ($query) => $query->where('subject_id', $filters['subject_id']));

        if ($storeFilter === 'current') {
            abort_unless($stores->get() !== null && $stores->get()->tenant_id === $tenant->getKey(), 404);
            $query->where('store_id', $stores->id());
        } elseif ($storeFilter !== 'all') {
            $query->where('store_id', $storeFilter);
        }

        $entries = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(50)->withQueryString();
        $entries->through(fn (AuditEntry $entry): array => [
            'id' => $entry->getKey(),
            'actor' => $entry->actor_type->value === 'SYSTEM' ? null : [
                'id' => (string) $entry->actor_user_id,
                'name' => $entry->actor?->name ?? 'Usuario eliminado',
                'email' => $entry->actor?->email ?? '',
            ],
            'actor_type' => $entry->actor_type->value,
            'action' => $entry->action->value,
            'subject_type' => $entry->subject_type->value,
            'subject_id' => $entry->subject_id,
            'subject_label' => $entry->subject_label,
            'store' => $entry->store === null ? null : ['id' => $entry->store->getKey(), 'name' => $entry->store->name],
            'metadata' => $entry->metadata,
            'occurred_at' => $entry->occurred_at->toIso8601String(),
        ]);

        return Inertia::render('admin/activity/Index', [
            'activities' => $entries,
            'filters' => [
                'action' => $filters['action'] ?? '',
                'subject_type' => $filters['subject_type'] ?? '',
                'actor_id' => $filters['actor_id'] ?? '',
                'store_id' => $storeFilter,
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'actions' => array_column(AuditAction::cases(), 'value'),
            'subjectTypes' => array_column(AuditSubjectType::cases(), 'value'),
            'actors' => $tenant->members()->with('user:id,name,email')->orderBy('created_at')->get()
                ->map(fn ($member) => ['id' => (string) $member->user_id, 'name' => $member->user->name, 'email' => $member->user->email]),
            'stores' => Store::query()->where('tenant_id', $tenant->getKey())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
