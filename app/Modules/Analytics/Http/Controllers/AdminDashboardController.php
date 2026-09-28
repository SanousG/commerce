<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\BuildAdminDashboard;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use App\Modules\Stores\Application\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, StoreContext $stores, BuildAdminDashboard $dashboard): Response
    {
        $validated = $request->validate([
            'range' => ['sometimes', 'string', Rule::enum(DashboardRange::class)],
        ]);

        $store = $stores->get();
        abort_unless($store !== null, 404);

        $range = DashboardRange::tryFrom($validated['range'] ?? '') ?? DashboardRange::Last7Days;

        return Inertia::render('admin/Dashboard', [
            'dashboard' => $dashboard->build($request->user(), $store, $range)->toArray(),
        ]);
    }
}
