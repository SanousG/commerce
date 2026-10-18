<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\BuildAdminDashboard;
use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use App\Modules\Analytics\Http\Requests\DashboardRequest;
use App\Modules\Stores\Application\StoreContext;
use Inertia\Inertia;
use Inertia\Response;

final class AdminDashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, StoreContext $stores, BuildAdminDashboard $dashboard): Response
    {
        $store = $stores->get();
        abort_unless($store !== null, 404);

        $validated = $request->validated();
        $range = ($validated['range'] ?? null) === DashboardRange::Custom->value
            ? DashboardDateRange::custom($validated['from'], $validated['to'], $store->timezone)
            : DashboardDateRange::make(
                DashboardRange::tryFrom($validated['range'] ?? '') ?? DashboardRange::Last7Days,
                $store->timezone,
            );

        return Inertia::render('admin/Dashboard', [
            'dashboard' => $dashboard->build($request->user(), $store, $range)->toArray(),
        ]);
    }
}
