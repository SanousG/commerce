<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Application\ArchiveCustomer;
use App\Modules\Customers\Application\CreateCustomer;
use App\Modules\Customers\Application\CustomerAccess;
use App\Modules\Customers\Application\UpdateCustomer;
use App\Modules\Customers\Domain\Enums\CustomerStatus;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use App\Modules\Customers\Http\Requests\CreateCustomerRequest;
use App\Modules\Customers\Http\Requests\UpdateCustomerRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request, TenantContext $context, CustomerAccess $access): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $search = trim((string) $request->query('search'));
        $customers = $context->get()->customers()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            }))
            ->withCount('addresses')
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString()
            ->through(fn (Customer $customer): array => $this->customerData($customer));

        return Inertia::render('admin/customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(Request $request, CustomerAccess $access): Response
    {
        abort_unless($access->canCreate($request->user()), 403);

        return Inertia::render('admin/customers/Create');
    }

    public function store(CreateCustomerRequest $request, TenantContext $context, CreateCustomer $action): RedirectResponse
    {
        $customer = $action->handle($context->get(), $request->validated());

        return to_route('admin.customers.edit', $customer)->with('status', 'Customer created.');
    }

    public function edit(Request $request, TenantContext $context, CustomerAccess $access, string $customer): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $scoped = $context->get()->customers()->with('addresses')->findOrFail($customer);

        return Inertia::render('admin/customers/Edit', [
            'customer' => $this->customerData($scoped),
            'addresses' => $scoped->addresses->map(fn (CustomerAddress $address): array => $this->addressData($address)),
            'statuses' => array_column(CustomerStatus::cases(), 'value'),
            'defaultCountry' => config('commerce.default_country', 'PE'),
        ]);
    }

    public function update(UpdateCustomerRequest $request, TenantContext $context, UpdateCustomer $action, string $customer): RedirectResponse
    {
        $tenant = $context->get();
        $scoped = $tenant->customers()->findOrFail($customer);
        $action->handle($tenant, $scoped, $request->validated());

        return back()->with('status', 'Customer updated.');
    }

    public function archive(Request $request, TenantContext $context, CustomerAccess $access, ArchiveCustomer $action, string $customer): RedirectResponse
    {
        abort_unless($access->canArchive($request->user()), 403);
        $tenant = $context->get();
        $scoped = $tenant->customers()->findOrFail($customer);
        $action->handle($tenant, $scoped);

        return to_route('admin.customers.index')->with('status', 'Customer archived.');
    }

    private function customerData(Customer $customer): array
    {
        return [
            'id' => $customer->getKey(),
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'display_name' => $customer->displayName(),
            'email' => $customer->email,
            'phone' => $customer->phone,
            'status' => $customer->status->value,
            'notes' => $customer->notes,
            'addresses_count' => (int) ($customer->addresses_count ?? $customer->addresses()->count()),
            'created_at' => $customer->created_at?->toISOString(),
        ];
    }

    private function addressData(CustomerAddress $address): array
    {
        return [
            'id' => $address->getKey(),
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'country_code' => $address->country_code,
            'region' => $address->region,
            'city' => $address->city,
            'district' => $address->district,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'postal_code' => $address->postal_code,
            'reference' => $address->reference,
            'is_default_shipping' => $address->is_default_shipping,
            'is_default_billing' => $address->is_default_billing,
        ];
    }
}
