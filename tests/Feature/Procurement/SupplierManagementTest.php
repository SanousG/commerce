<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\Supplier;
use Inertia\Testing\AssertableInertia;

class SupplierManagementTest extends ProcurementFeatureTestCase
{
    public function test_owner_can_create_update_and_archive_a_normalized_supplier(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->useTenant($user, $tenant);

        $this->post(route('admin.suppliers.store'), [
            'code' => '  acme_01 ', 'name' => '  Acme Supply  ', 'contact_name' => '  Jane  ',
            'email' => '  jane@example.test ', 'phone' => '  555-0100 ', 'website' => ' https://example.test ',
            'notes' => '  Preferred vendor  ',
        ])->assertRedirect();

        $supplier = Supplier::query()->sole();
        $this->assertSame(['ACME_01', 'Acme Supply', 'Jane', 'jane@example.test', '555-0100'], [
            $supplier->code, $supplier->name, $supplier->contact_name, $supplier->email, $supplier->phone,
        ]);

        $this->patch(route('admin.suppliers.update', $supplier), [
            'code' => ' acme-02 ', 'name' => ' Acme Updated ', 'status' => 'INACTIVE',
            'contact_name' => '', 'email' => '', 'phone' => '', 'website' => '', 'notes' => '',
        ])->assertRedirect();
        $this->assertSame(['ACME-02', 'Acme Updated', SupplierStatus::Inactive, null], [
            $supplier->fresh()->code, $supplier->fresh()->name, $supplier->fresh()->status, $supplier->fresh()->email,
        ]);

        $this->post(route('admin.suppliers.archive', $supplier))->assertRedirect(route('admin.suppliers.index'));
        $this->assertSame(SupplierStatus::Archived, $supplier->fresh()->status);
    }

    public function test_duplicate_code_is_rejected_within_tenant_but_allowed_in_another_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $this->supplier($tenantA, 'SHARED');
        $this->supplier($tenantB, 'SHARED');
        $this->useTenant($user, $tenantA);

        $this->post(route('admin.suppliers.store'), [
            'code' => ' shared ', 'name' => 'Duplicate', 'contact_name' => null, 'email' => null,
            'phone' => null, 'website' => null, 'notes' => null,
        ])->assertSessionHasErrors('code');

        $this->assertSame(2, Supplier::query()->where('code', 'SHARED')->count());
    }

    public function test_cross_tenant_supplier_http_routes_return_not_found(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $supplierB = $this->supplier($tenantB);
        $this->useTenant($user, $tenantA);

        $this->get(route('admin.suppliers.edit', $supplierB))->assertNotFound();
        $this->patch(route('admin.suppliers.update', $supplierB), [
            'code' => 'HIDDEN', 'name' => 'Hidden', 'status' => 'ACTIVE',
        ])->assertNotFound();
        $this->post(route('admin.suppliers.archive', $supplierB))->assertNotFound();
    }

    public function test_member_can_view_suppliers_but_cannot_mutate_them(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $supplier = $this->supplier($tenant);
        $this->useTenant($member, $tenant);

        $this->get(route('admin.suppliers.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/suppliers/Index')->has('suppliers.data', 1));
        $this->get(route('admin.suppliers.edit', $supplier))->assertOk();
        $this->post(route('admin.suppliers.store'), [])->assertForbidden();
        $this->patch(route('admin.suppliers.update', $supplier), [])->assertForbidden();
        $this->post(route('admin.suppliers.archive', $supplier))->assertForbidden();
    }
}
