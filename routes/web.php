<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Modules\Access\Http\Controllers\AccessController;
use App\Modules\Cart\Http\Controllers\CartController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductOptionController;
use App\Modules\Catalog\Http\Controllers\ProductVariantController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Customers\Http\Controllers\CustomerAddressController;
use App\Modules\Customers\Http\Controllers\CustomerController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\InventoryLocationController;
use App\Modules\Listings\Http\Controllers\ListingController;
use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Pricing\Http\Controllers\PricingController;
use App\Modules\Promotions\Http\Controllers\PromotionController;
use App\Modules\Stores\Http\Controllers\StoreController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (! Auth::check()) {
        return to_route('login');
    }

    return Auth::user()->hasVerifiedEmail()
        ? to_route('admin.dashboard')
        : to_route('verification.notice');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:registration');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-email')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:verification')
        ->name('verification.send');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::post('/tenants/{tenant}/select', [TenantController::class, 'select'])
            ->whereUuid('tenant')
            ->name('tenants.select');

        Route::middleware('tenant')->group(function () {
            Route::get('/access', AccessController::class)->name('access.index');
            Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
            Route::get('/stores/create', [StoreController::class, 'create'])->name('stores.create');
            Route::post('/stores', [StoreController::class, 'store'])->name('stores.store');
            Route::post('/stores/{store}/select', [StoreController::class, 'select'])
                ->whereUuid('store')
                ->name('stores.select');

            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->whereUuid('customer')->name('customers.edit');
            Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])->whereUuid('customer')->name('customers.update');
            Route::post('/customers/{customer}/archive', [CustomerController::class, 'archive'])->whereUuid('customer')->name('customers.archive');
            Route::post('/customers/{customer}/addresses', [CustomerAddressController::class, 'store'])->whereUuid('customer')->name('customers.addresses.store');
            Route::match(['put', 'patch'], '/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update'])
                ->whereUuid(['customer', 'address'])->name('customers.addresses.update');
            Route::post('/customers/{customer}/addresses/{address}/default/{type}', [CustomerAddressController::class, 'setDefault'])
                ->whereUuid(['customer', 'address'])->whereIn('type', ['shipping', 'billing'])->name('customers.addresses.default');
            Route::delete('/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy'])
                ->whereUuid(['customer', 'address'])->name('customers.addresses.destroy');

            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->whereUuid('product')->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->whereUuid('product')->name('products.update');
            Route::patch('/products/{product}/archive', [ProductController::class, 'archive'])->whereUuid('product')->name('products.archive');
            Route::post('/products/{product}/options', [ProductOptionController::class, 'store'])->whereUuid('product')->name('products.options.store');
            Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->whereUuid('product')->name('products.variants.store');
            Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
                ->whereUuid(['product', 'variant'])
                ->name('products.variants.update');

            Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->whereUuid('category')->name('categories.edit');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->whereUuid('category')->name('categories.update');
            Route::patch('/categories/{category}/archive', [CategoryController::class, 'archive'])->whereUuid('category')->name('categories.archive');

            Route::get('/pricing', [PricingController::class, 'index'])->name('pricing.index');
            Route::get('/pricing/price-lists/create', [PricingController::class, 'create'])->name('pricing.price-lists.create');
            Route::post('/pricing/price-lists', [PricingController::class, 'store'])->name('pricing.price-lists.store');
            Route::get('/pricing/price-lists/{priceList}/edit', [PricingController::class, 'edit'])->whereUuid('priceList')->name('pricing.price-lists.edit');
            Route::put('/pricing/price-lists/{priceList}', [PricingController::class, 'update'])->whereUuid('priceList')->name('pricing.price-lists.update');
            Route::post('/pricing/price-lists/{priceList}/make-default', [PricingController::class, 'makeDefault'])->whereUuid('priceList')->name('pricing.price-lists.make-default');
            Route::patch('/pricing/price-lists/{priceList}/archive', [PricingController::class, 'archive'])->whereUuid('priceList')->name('pricing.price-lists.archive');
            Route::put('/pricing/price-lists/{priceList}/variants/{variant}', [PricingController::class, 'setVariantPrice'])
                ->whereUuid(['priceList', 'variant'])->name('pricing.price-lists.variants.update');
            Route::delete('/pricing/price-lists/{priceList}/variants/{variant}', [PricingController::class, 'removeVariantPrice'])
                ->whereUuid(['priceList', 'variant'])->name('pricing.price-lists.variants.destroy');

            Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
            Route::get('/promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
            Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
            Route::get('/promotions/{promotion}/edit', [PromotionController::class, 'edit'])
                ->whereUuid('promotion')->name('promotions.edit');
            Route::match(['put', 'patch'], '/promotions/{promotion}', [PromotionController::class, 'update'])
                ->whereUuid('promotion')->name('promotions.update');
            Route::match(['post', 'patch'], '/promotions/{promotion}/archive', [PromotionController::class, 'archive'])
                ->whereUuid('promotion')->name('promotions.archive');

            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/inventory/variants/{variant}', [InventoryController::class, 'showVariant'])
                ->whereUuid('variant')->name('inventory.variants.show');
            Route::post('/inventory/locations/{location}/variants/{variant}/adjust', [InventoryController::class, 'adjust'])
                ->whereUuid(['location', 'variant'])->name('inventory.adjust');
            Route::get('/inventory/transfers/create', [InventoryController::class, 'createTransfer'])
                ->name('inventory.transfers.create');
            Route::post('/inventory/transfers', [InventoryController::class, 'transfer'])->name('inventory.transfers.store');
            Route::get('/inventory/locations', [InventoryLocationController::class, 'index'])->name('inventory.locations.index');
            Route::get('/inventory/locations/create', [InventoryLocationController::class, 'create'])
                ->name('inventory.locations.create');
            Route::post('/inventory/locations', [InventoryLocationController::class, 'store'])->name('inventory.locations.store');
            Route::get('/inventory/locations/{location}/edit', [InventoryLocationController::class, 'edit'])
                ->whereUuid('location')->name('inventory.locations.edit');
            Route::match(['put', 'patch'], '/inventory/locations/{location}', [InventoryLocationController::class, 'update'])
                ->whereUuid('location')->name('inventory.locations.update');
            Route::post('/inventory/locations/{location}/make-default', [InventoryLocationController::class, 'makeDefault'])
                ->whereUuid('location')->name('inventory.locations.make-default');
        });

        Route::get('/dashboard', fn () => Inertia::render('admin/Dashboard'))
            ->middleware(['tenant', 'store'])
            ->name('dashboard');

        Route::middleware(['tenant', 'store'])->group(function () {
            Route::get('/orders', [OrderController::class, 'index'])->middleware('can:orders.view')->name('orders.index');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('can:orders.view')->whereUuid('order')->name('orders.show');
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('can:orders.manage')->whereUuid('order')->name('orders.cancel');
            Route::post('/checkouts/{checkout}/order', [OrderController::class, 'storeFromCheckout'])->middleware('can:orders.manage')->whereUuid('checkout')->name('checkouts.order.store');

            Route::get('/checkouts', [CheckoutController::class, 'index'])->middleware('can:checkouts.view')->name('checkouts.index');
            Route::get('/checkouts/create', [CheckoutController::class, 'create'])->middleware('can:checkouts.manage')->name('checkouts.create');
            Route::post('/checkouts', [CheckoutController::class, 'store'])->middleware('can:checkouts.manage')->name('checkouts.store');
            Route::get('/checkouts/{checkout}', [CheckoutController::class, 'show'])->middleware('can:checkouts.view')->whereUuid('checkout')->name('checkouts.show');
            Route::patch('/checkouts/{checkout}', [CheckoutController::class, 'update'])->middleware('can:checkouts.manage')->whereUuid('checkout')->name('checkouts.update');
            Route::post('/checkouts/{checkout}/prepare', [CheckoutController::class, 'prepare'])->middleware('can:checkouts.manage')->whereUuid('checkout')->name('checkouts.prepare');
            Route::post('/checkouts/{checkout}/cancel', [CheckoutController::class, 'cancel'])->middleware('can:checkouts.manage')->whereUuid('checkout')->name('checkouts.cancel');

            Route::get('/carts', [CartController::class, 'index'])->middleware('can:carts.view')->name('carts.index');
            Route::post('/carts', [CartController::class, 'store'])->middleware('can:carts.manage')->name('carts.store');
            Route::get('/carts/{cart}', [CartController::class, 'show'])->middleware('can:carts.view')->whereUuid('cart')->name('carts.show');
            Route::post('/carts/{cart}/items', [CartController::class, 'addItem'])->middleware('can:carts.manage')->whereUuid('cart')->name('carts.items.store');
            Route::patch('/carts/{cart}/items/{item}', [CartController::class, 'updateItem'])->middleware('can:carts.manage')->whereUuid(['cart', 'item'])->name('carts.items.update');
            Route::delete('/carts/{cart}/items/{item}', [CartController::class, 'removeItem'])->middleware('can:carts.manage')->whereUuid(['cart', 'item'])->name('carts.items.destroy');
            Route::delete('/carts/{cart}/items', [CartController::class, 'clear'])->middleware('can:carts.manage')->whereUuid('cart')->name('carts.items.clear');
            Route::patch('/carts/{cart}/customer', [CartController::class, 'attachCustomer'])->middleware('can:carts.manage')->whereUuid('cart')->name('carts.customer.update');

            Route::get('/listings', [ListingController::class, 'index'])->middleware('can:listings.view')->name('listings.index');
            Route::get('/listings/create', [ListingController::class, 'create'])->middleware('can:listings.create')->name('listings.create');
            Route::post('/listings', [ListingController::class, 'store'])->middleware('can:listings.create')->name('listings.store');
            Route::put('/listings/settings/price-list', [ListingController::class, 'updateSettings'])->middleware('can:listings.update')->name('listings.settings.update');
            Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->middleware('can:listings.view')->whereUuid('listing')->name('listings.edit');
            Route::match(['put', 'patch'], '/listings/{listing}', [ListingController::class, 'update'])->middleware('can:listings.update')->whereUuid('listing')->name('listings.update');
            Route::post('/listings/{listing}/publish', [ListingController::class, 'publish'])->middleware('can:listings.publish')->whereUuid('listing')->name('listings.publish');
            Route::post('/listings/{listing}/unpublish', [ListingController::class, 'unpublish'])->middleware('can:listings.publish')->whereUuid('listing')->name('listings.unpublish');
            Route::post('/listings/{listing}/archive', [ListingController::class, 'archive'])->middleware('can:listings.archive')->whereUuid('listing')->name('listings.archive');
        });
    });
