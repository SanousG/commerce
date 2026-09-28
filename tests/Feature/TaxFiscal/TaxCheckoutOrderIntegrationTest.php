<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\CartCalculator;
use App\Modules\Cart\Application\CreateCart;
use App\Modules\Checkout\Application\PrepareCheckout;
use App\Modules\Checkout\Application\StartCheckout;
use App\Modules\Checkout\Application\UpdateCheckout;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Orders\Application\CreateOrderFromCheckout;
use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaxCheckoutOrderIntegrationTest extends TaxFiscalFeatureTestCase
{
    public function test_promotion_tax_is_decomposed_from_final_line_and_copied_through_fiscal(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$checkout, , , $variant] = $this->pendingCheckout($store, quantity: 1, price: '118.0000');
        $promotion = $this->createPromotion($tenant, [
            'discount_type' => 'FIXED_AMOUNT', 'discount_value' => '18.0000', 'currency' => 'PEN',
        ]);

        $ready = $this->makeReady($tenant, $checkout);
        $line = $ready->lines()->sole();
        $this->assertSame('100.0000', $line->line_total);
        $this->assertSame('84.7458', $line->taxable_amount);
        $this->assertSame('15.2542', $line->tax_amount);
        $this->assertSame('100.0000', $ready->subtotal);
        $this->assertSame('15.2542', $ready->tax_total);
        $this->assertSame('100.0000', $ready->grand_total);

        $exempt = $this->createTaxCategory($tenant, 'EXEMPT');
        $this->createTaxRate($tenant, $exempt, '0.000000');
        $variant->product()->update(['tax_category_id' => $exempt->getKey()]);
        $promotion->update(['status' => PromotionStatus::Inactive]);

        $order = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready, $actor);
        $this->assertSame($line->tax_category_code, $order->items()->sole()->tax_category_code);
        $this->assertSame($ready->tax_total, $order->tax_total);
        $this->assertSame($ready->grand_total, $order->grand_total);

        $this->createIssuer($tenant);
        $this->createFiscalSeries($store);
        $document = $this->generateReceipt($tenant, $store, $order, $actor);
        $this->assertSame($order->taxable_amount, $document->taxable_amount);
        $this->assertSame($order->tax_total, $document->tax_amount);
        $this->assertSame($order->grand_total, $document->grand_total);
        $this->assertSame('STANDARD', $document->items()->sole()->tax_category_code->value);
    }

    public function test_mixed_categories_reconcile_checkout_order_and_fiscal_totals(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        $standard = $this->createTaxCategory($tenant);
        $this->createTaxRate($tenant, $standard);
        $exempt = $this->createTaxCategory($tenant, 'EXEMPT');
        $this->createTaxRate($tenant, $exempt, '0.000000');
        $unaffected = $this->createTaxCategory($tenant, 'UNAFFECTED');
        $this->createTaxRate($tenant, $unaffected, '0.000000');
        $cart = app(CreateCart::class)->handle($store);

        foreach ([[$standard, '118.0000'], [$exempt, '100.0000'], [$unaffected, '100.0000']] as [$category, $price]) {
            $product = $this->createProduct($tenant);
            $product->update(['tax_category_id' => $category->getKey()]);
            $variant = $product->variants()->sole();
            $listing = $this->createListing($store, $product);
            $listing->update(['status' => StoreListingStatus::Published, 'published_at' => now()]);
            $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, $price);
            $this->stock($tenant, $variant, 5);
            app(AddCartItem::class)->handle($cart, $listing->fresh(), $variant, 1);
        }

        $checkout = app(StartCheckout::class)->handle($tenant, $store, $cart->fresh(), (string) Str::uuid());
        app(UpdateCheckout::class)->handle($tenant, $checkout, [
            'customer_name' => 'Mixed Buyer', 'email' => 'mixed@example.test', 'delivery_type' => 'PICKUP',
        ]);
        $ready = app(PrepareCheckout::class)->handle($tenant, $checkout->fresh());
        $this->assertSame('100.0000', $ready->taxable_amount);
        $this->assertSame('100.0000', $ready->exempt_amount);
        $this->assertSame('100.0000', $ready->unaffected_amount);
        $this->assertSame('18.0000', $ready->tax_total);
        $this->assertSame('318.0000', $ready->grand_total);

        $order = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready, $actor);
        $this->createIssuer($tenant);
        $this->createFiscalSeries($store);
        $document = $this->generateReceipt($tenant, $store, $order, $actor);
        foreach (['taxable_amount', 'exempt_amount', 'unaffected_amount', 'tax_total', 'grand_total'] as $field) {
            $documentField = $field === 'tax_total' ? 'tax_amount' : $field;
            $this->assertSame($ready->{$field}, $order->{$field});
            $this->assertSame($order->{$field}, $document->{$documentField});
        }
    }

    public function test_missing_inactive_or_unrated_tax_configuration_blocks_prepare_before_reservations(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);

        foreach (['missing', 'unrated', 'inactive'] as $case) {
            [$cart, $listing, $variant] = $this->sellableCart($store);
            if ($case !== 'missing') {
                $category = $this->createTaxCategory($tenant, $case === 'inactive' ? 'EXEMPT' : 'STANDARD', [
                    'status' => $case === 'inactive' ? 'INACTIVE' : 'ACTIVE',
                ]);
                $listing->product()->update(['tax_category_id' => $category->getKey()]);
            }
            app(AddCartItem::class)->handle($cart, $listing, $variant, 1);
            $summary = app(CartCalculator::class)->calculate($cart->fresh());
            $expected = $case === 'missing' ? 'TAX_CATEGORY_MISSING'
                : ($case === 'unrated' ? 'TAX_RATE_MISSING' : 'TAX_CONFIGURATION_INVALID');
            $this->assertContains($expected, $summary->items[0]['issues']);
            $checkout = app(StartCheckout::class)->handle($tenant, $store, $cart->fresh(), (string) Str::uuid());
            app(UpdateCheckout::class)->handle($tenant, $checkout, [
                'customer_name' => 'Invalid Tax', 'email' => 'invalid@example.test', 'delivery_type' => 'PICKUP',
            ]);
            try {
                app(PrepareCheckout::class)->handle($tenant, $checkout->fresh());
                $this->fail("{$case} tax configuration prepared checkout.");
            } catch (ValidationException $exception) {
                $this->assertSame($expected, $exception->errors()['tax'][0]);
                $this->assertSame(0, InventoryReservation::query()->where('reference_type', 'CHECKOUT_LINE')->count());
            }
        }
    }

    public function test_quantity_rounding_uses_line_total_and_ready_snapshot_survives_rate_change(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        [$checkout] = $this->pendingCheckout($store, quantity: 3, price: '1.0001');
        $ready = $this->makeReady($tenant, $checkout);
        $line = $ready->lines()->sole();
        $this->assertSame('3.0003', $line->line_total);
        $this->assertSame('2.5426', $line->taxable_amount);
        $this->assertSame('0.4577', $line->tax_amount);
        $this->assertSame($line->line_total, $ready->grand_total);

        $rate = $tenant->taxRates()->sole();
        $rate->update(['status' => TaxCategoryStatus::Inactive]);
        $this->createTaxRate($tenant, $tenant->taxCategories()->where('code', 'STANDARD')->sole(), '10.000000');
        $this->assertSame('18.000000', $line->fresh()->tax_rate);
        $this->assertSame('0.4577', $ready->fresh()->tax_total);
    }

    public function test_effective_rate_uses_one_store_local_date_and_ready_keeps_it(): void
    {
        CarbonImmutable::setTestNow('2026-10-02 02:00:00 UTC');
        try {
            $tenant = $this->createTenantFor(User::factory()->create());
            $store = $this->createStore($tenant);
            $store->update(['timezone' => 'America/Lima']);
            $category = $this->createTaxCategory($tenant);
            $this->createTaxRate($tenant, $category, '10.000000', [
                'valid_from' => '2026-10-01', 'valid_until' => '2026-10-01',
            ]);
            $this->createTaxRate($tenant, $category, '20.000000', [
                'valid_from' => '2026-10-02', 'valid_until' => null,
            ]);
            [$cart, $listing, $variant] = $this->sellableCart($store, '110.0000');
            $listing->product()->update(['tax_category_id' => $category->getKey()]);
            app(AddCartItem::class)->handle($cart, $listing, $variant, 1);

            $first = app(StartCheckout::class)->handle($tenant, $store, $cart->fresh(), (string) Str::uuid());
            app(UpdateCheckout::class)->handle($tenant, $first, [
                'customer_name' => 'First Date', 'email' => 'first-date@example.test', 'delivery_type' => 'PICKUP',
            ]);
            $firstReady = app(PrepareCheckout::class)->handle($tenant, $first->fresh());
            $this->assertSame('10.000000', $firstReady->lines()->sole()->tax_rate);
            $this->assertTrue($firstReady->tax_calculated_at->equalTo(CarbonImmutable::parse('2026-10-02 02:00:00 UTC')));

            CarbonImmutable::setTestNow('2026-10-02 06:00:00 UTC');
            $secondCart = app(CreateCart::class)->handle($store);
            app(AddCartItem::class)->handle($secondCart, $listing, $variant, 1);
            $second = app(StartCheckout::class)->handle($tenant, $store, $secondCart, (string) Str::uuid());
            app(UpdateCheckout::class)->handle($tenant, $second, [
                'customer_name' => 'Second Date', 'email' => 'second-date@example.test', 'delivery_type' => 'PICKUP',
            ]);
            $secondReady = app(PrepareCheckout::class)->handle($tenant, $second->fresh());
            $this->assertSame('20.000000', $secondReady->lines()->sole()->tax_rate);
            $this->assertSame('10.000000', $firstReady->lines()->sole()->fresh()->tax_rate);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
