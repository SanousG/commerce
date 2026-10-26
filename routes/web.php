<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\UserLocaleController;
use App\Modules\Access\Http\Controllers\AccessController;
use App\Modules\Analytics\Http\Controllers\AdminDashboardController;
use App\Modules\Audit\Http\Controllers\ActivityController;
use App\Modules\Cart\Http\Controllers\CartController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductMediaController;
use App\Modules\Catalog\Http\Controllers\ProductOptionController;
use App\Modules\Catalog\Http\Controllers\ProductVariantController;
use App\Modules\Catalog\Http\Controllers\ProductVariantMediaController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Costing\Http\Controllers\InventoryCostController;
use App\Modules\Customers\Http\Controllers\CustomerAddressController;
use App\Modules\Customers\Http\Controllers\CustomerController;
use App\Modules\Fiscal\Http\Controllers\FiscalDocumentController;
use App\Modules\Fiscal\Http\Controllers\FiscalSeriesController;
use App\Modules\Fiscal\Http\Controllers\FiscalSettingsController;
use App\Modules\Fulfillment\Http\Controllers\FulfillmentController;
use App\Modules\Fulfillment\Http\Controllers\ShipmentController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\InventoryLocationController;
use App\Modules\Listings\Http\Controllers\ListingController;
use App\Modules\Media\Http\Controllers\MediaController;
use App\Modules\Notifications\Http\Controllers\AdminNotificationController;
use App\Modules\Notifications\Http\Controllers\NotificationPreferenceController;
use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Payments\Http\Controllers\OrderPaymentTermsController;
use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Payments\Http\Controllers\PaymentSettingsController;
use App\Modules\Payments\Http\Controllers\RefundController;
use App\Modules\Pricing\Http\Controllers\PricingController;
use App\Modules\Procurement\Http\Controllers\PurchaseOrderController;
use App\Modules\Procurement\Http\Controllers\PurchaseOrderItemController;
use App\Modules\Procurement\Http\Controllers\PurchaseReceiptController;
use App\Modules\Procurement\Http\Controllers\PurchaseReceiptItemController;
use App\Modules\Procurement\Http\Controllers\SupplierController;
use App\Modules\Procurement\Http\Controllers\SupplierProductController;
use App\Modules\Promotions\Http\Controllers\PromotionController;
use App\Modules\Returns\Http\Controllers\ExchangeController;
use App\Modules\Returns\Http\Controllers\ReturnController;
use App\Modules\Returns\Http\Controllers\ReturnItemController;
use App\Modules\Stores\Http\Controllers\StoreController;
use App\Modules\Stores\Http\Controllers\StoreSettingsController;
use App\Modules\Tax\Http\Controllers\TaxController;
use App\Modules\Tenancy\Http\Controllers\AcceptTenantInvitationController;
use App\Modules\Tenancy\Http\Controllers\AdminSettingsController;
use App\Modules\Tenancy\Http\Controllers\TeamController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use App\Modules\Tenancy\Http\Controllers\TenantInvitationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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

Route::get('/invitations/{token}', [AcceptTenantInvitationController::class, 'show'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('invitations.show');
Route::post('/invitations/{token}/accept', [AcceptTenantInvitationController::class, 'accept'])
    ->middleware(['auth', 'auth.session', 'verified', 'throttle:invitation-accept'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('invitations.accept');

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::patch('/locale', [UserLocaleController::class, 'update'])->name('locale.update');
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::post('/tenants/{tenant}/select', [TenantController::class, 'select'])
            ->whereUuid('tenant')
            ->name('tenants.select');

        Route::middleware('tenant')->group(function () {
            Route::get('/media', [MediaController::class, 'index'])->middleware('can:media.view')->name('media.index');
            Route::post('/media', [MediaController::class, 'store'])->middleware(['can:media.upload', 'throttle:media-upload'])->name('media.store');
            Route::get('/media/{mediaAsset}', [MediaController::class, 'show'])->middleware('can:media.view')->whereUuid('mediaAsset')->name('media.show');
            Route::patch('/media/{mediaAsset}', [MediaController::class, 'update'])->middleware('can:media.manage')->whereUuid('mediaAsset')->name('media.update');
            Route::delete('/media/{mediaAsset}', [MediaController::class, 'destroy'])->middleware('can:media.delete')->whereUuid('mediaAsset')->name('media.destroy');
            Route::get('/media/{mediaAsset}/thumbnail', [MediaController::class, 'thumbnail'])->middleware('can:media.view')->whereUuid('mediaAsset')->name('media.thumbnail');
            Route::get('/media/{mediaAsset}/file', [MediaController::class, 'file'])->middleware('can:media.view')->whereUuid('mediaAsset')->name('media.file');

            Route::get('/access', AccessController::class)->name('access.index');
            Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
            Route::get('/stores/create', [StoreController::class, 'create'])->name('stores.create');
            Route::post('/stores', [StoreController::class, 'store'])->name('stores.store');
            Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])->whereUuid('store')->name('stores.edit');
            Route::patch('/stores/{store}', [StoreController::class, 'update'])->whereUuid('store')->name('stores.update');
            Route::post('/stores/{store}/select', [StoreController::class, 'select'])
                ->whereUuid('store')
                ->name('stores.select');
            Route::get('/stores/{store}/settings', [StoreSettingsController::class, 'show'])
                ->middleware('can:stores.view')->whereUuid('store')->name('stores.settings.show');
            Route::patch('/stores/{store}/settings', [StoreSettingsController::class, 'update'])
                ->middleware('can:stores.update')->whereUuid('store')->name('stores.settings.update');

            Route::get('/settings', [AdminSettingsController::class, 'index'])
                ->middleware('can:settings.view')->name('settings.index');
            Route::get('/settings/general', [AdminSettingsController::class, 'general'])
                ->middleware('can:settings.view')->name('settings.general.show');
            Route::patch('/settings/general', [AdminSettingsController::class, 'update'])
                ->middleware('can:settings.manage')->name('settings.general.update');
            Route::get('/settings/team', [TeamController::class, 'index'])
                ->middleware('can:members.view')->name('settings.team.index');
            Route::post('/settings/team/invitations', [TenantInvitationController::class, 'store'])
                ->middleware(['can:members.manage', 'throttle:tenant-invitations'])->name('settings.team.invitations.store');
            Route::post('/settings/team/invitations/{invitation}/resend', [TenantInvitationController::class, 'resend'])
                ->middleware(['can:members.manage', 'throttle:tenant-invitations'])->whereUuid('invitation')->name('settings.team.invitations.resend');
            Route::post('/settings/team/invitations/{invitation}/revoke', [TenantInvitationController::class, 'revoke'])
                ->middleware('can:members.manage')->whereUuid('invitation')->name('settings.team.invitations.revoke');
            Route::patch('/settings/team/members/{member}/role', [TeamController::class, 'changeRole'])
                ->middleware('can:members.manage')->whereUuid('member')->name('settings.team.members.role');
            Route::post('/settings/team/members/{member}/deactivate', [TeamController::class, 'deactivate'])
                ->middleware('can:members.manage')->whereUuid('member')->name('settings.team.members.deactivate');
            Route::post('/settings/team/members/{member}/reactivate', [TeamController::class, 'reactivate'])
                ->middleware('can:members.manage')->whereUuid('member')->name('settings.team.members.reactivate');

            Route::get('/taxes', [TaxController::class, 'index'])->middleware('can:taxes.view')->name('taxes.index');
            Route::post('/tax-categories', [TaxController::class, 'storeCategory'])->middleware('can:taxes.manage')->name('tax-categories.store');
            Route::patch('/tax-categories/{taxCategory}', [TaxController::class, 'updateCategory'])->middleware('can:taxes.manage')->whereUuid('taxCategory')->name('tax-categories.update');
            Route::post('/tax-rates', [TaxController::class, 'storeRate'])->middleware('can:taxes.manage')->name('tax-rates.store');
            Route::patch('/tax-rates/{taxRate}', [TaxController::class, 'updateRate'])->middleware('can:taxes.manage')->whereUuid('taxRate')->name('tax-rates.update');

            Route::get('/settings/fiscal', [FiscalSettingsController::class, 'show'])->middleware('can:fiscal_settings.view')->name('fiscal.settings.show');
            Route::patch('/settings/fiscal', [FiscalSettingsController::class, 'update'])->middleware('can:fiscal_settings.manage')->name('fiscal.settings.update');

            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])->whereUuid('customer')->name('customers.show');
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
            Route::post('/products/{product}/media', [ProductMediaController::class, 'store'])->middleware(['can:products.update', 'can:media.view'])->whereUuid('product')->name('products.media.store');
            Route::delete('/products/{product}/media/{mediaAsset}', [ProductMediaController::class, 'destroy'])->middleware(['can:products.update', 'can:media.view'])->whereUuid(['product', 'mediaAsset'])->name('products.media.destroy');
            Route::patch('/products/{product}/media-order', [ProductMediaController::class, 'reorder'])->middleware(['can:products.update', 'can:media.view'])->whereUuid('product')->name('products.media.reorder');
            Route::post('/product-variants/{variant}/media', [ProductVariantMediaController::class, 'store'])->middleware(['can:products.update', 'can:media.view'])->whereUuid('variant')->name('product-variants.media.store');
            Route::delete('/product-variants/{variant}/media/{mediaAsset}', [ProductVariantMediaController::class, 'destroy'])->middleware(['can:products.update', 'can:media.view'])->whereUuid(['variant', 'mediaAsset'])->name('product-variants.media.destroy');
            Route::patch('/product-variants/{variant}/media-order', [ProductVariantMediaController::class, 'reorder'])->middleware(['can:products.update', 'can:media.view'])->whereUuid('variant')->name('product-variants.media.reorder');
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
            Route::get('/inventory/valuation', [InventoryCostController::class, 'index'])
                ->middleware('can:inventory.cost.view')->name('inventory.cost.index');
            Route::post('/inventory/locations/{location}/variants/{variant}/initialize-cost', [InventoryCostController::class, 'initialize'])
                ->middleware('can:inventory.cost.manage')->whereUuid(['location', 'variant'])->name('inventory.cost.initialize');
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

            Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('can:suppliers.view')->name('suppliers.index');
            Route::get('/suppliers/create', [SupplierController::class, 'create'])->middleware('can:suppliers.create')->name('suppliers.create');
            Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('can:suppliers.create')->name('suppliers.store');
            Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('can:suppliers.view')->whereUuid('supplier')->name('suppliers.edit');
            Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('can:suppliers.update')->whereUuid('supplier')->name('suppliers.update');
            Route::post('/suppliers/{supplier}/archive', [SupplierController::class, 'archive'])->middleware('can:suppliers.archive')->whereUuid('supplier')->name('suppliers.archive');
            Route::post('/suppliers/{supplier}/products', [SupplierProductController::class, 'store'])->middleware('can:suppliers.update')->whereUuid('supplier')->name('suppliers.products.store');
            Route::patch('/supplier-products/{supplierProduct}', [SupplierProductController::class, 'update'])->middleware('can:suppliers.update')->whereUuid('supplierProduct')->name('supplier-products.update');
            Route::post('/supplier-products/{supplierProduct}/archive', [SupplierProductController::class, 'archive'])->middleware('can:suppliers.archive')->whereUuid('supplierProduct')->name('supplier-products.archive');

            Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('can:procurement.view')->name('purchase-orders.index');
            Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->middleware('can:procurement.manage')->name('purchase-orders.create');
            Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('can:procurement.manage')->name('purchase-orders.store');
            Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('can:procurement.view')->whereUuid('purchaseOrder')->name('purchase-orders.show');
            Route::patch('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('can:procurement.manage')->whereUuid('purchaseOrder')->name('purchase-orders.update');
            Route::post('/purchase-orders/{purchaseOrder}/items', [PurchaseOrderItemController::class, 'store'])->middleware('can:procurement.manage')->whereUuid('purchaseOrder')->name('purchase-orders.items.store');
            Route::patch('/purchase-order-items/{item}', [PurchaseOrderItemController::class, 'update'])->middleware('can:procurement.manage')->whereUuid('item')->name('purchase-order-items.update');
            Route::delete('/purchase-order-items/{item}', [PurchaseOrderItemController::class, 'destroy'])->middleware('can:procurement.manage')->whereUuid('item')->name('purchase-order-items.destroy');
            Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('can:procurement.approve')->whereUuid('purchaseOrder')->name('purchase-orders.approve');
            Route::post('/purchase-orders/{purchaseOrder}/mark-ordered', [PurchaseOrderController::class, 'markOrdered'])->middleware('can:procurement.manage')->whereUuid('purchaseOrder')->name('purchase-orders.mark-ordered');
            Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('can:procurement.manage')->whereUuid('purchaseOrder')->name('purchase-orders.cancel');

            Route::post('/purchase-orders/{purchaseOrder}/receipts', [PurchaseReceiptController::class, 'store'])->middleware('can:procurement.receive')->whereUuid('purchaseOrder')->name('purchase-orders.receipts.store');
            Route::get('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'show'])->middleware('can:procurement.view')->whereUuid('receipt')->name('purchase-receipts.show');
            Route::patch('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'update'])->middleware('can:procurement.receive')->whereUuid('receipt')->name('purchase-receipts.update');
            Route::post('/purchase-receipts/{receipt}/items', [PurchaseReceiptItemController::class, 'store'])->middleware('can:procurement.receive')->whereUuid('receipt')->name('purchase-receipts.items.store');
            Route::patch('/purchase-receipt-items/{item}', [PurchaseReceiptItemController::class, 'update'])->middleware('can:procurement.receive')->whereUuid('item')->name('purchase-receipt-items.update');
            Route::delete('/purchase-receipt-items/{item}', [PurchaseReceiptItemController::class, 'destroy'])->middleware('can:procurement.receive')->whereUuid('item')->name('purchase-receipt-items.destroy');
            Route::post('/purchase-receipts/{receipt}/post', [PurchaseReceiptController::class, 'post'])->middleware('can:procurement.receive')->whereUuid('receipt')->name('purchase-receipts.post');
            Route::post('/purchase-receipts/{receipt}/cancel', [PurchaseReceiptController::class, 'cancel'])->middleware('can:procurement.receive')->whereUuid('receipt')->name('purchase-receipts.cancel');
        });

        Route::get('/dashboard', AdminDashboardController::class)
            ->middleware(['tenant', 'store'])
            ->name('dashboard');

        Route::middleware(['tenant', 'store'])->group(function () {
            Route::get('/activity', [ActivityController::class, 'index'])->middleware('can:audit.view')->name('activity.index');
            Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/read-all', [AdminNotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
            Route::post('/notifications/{notification}/dismiss', [AdminNotificationController::class, 'dismiss'])->whereUuid('notification')->name('notifications.dismiss');
            Route::get('/settings/notifications', [NotificationPreferenceController::class, 'show'])->name('notifications.preferences.show');
            Route::match(['put', 'patch'], '/settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');
            Route::get('/fiscal-documents', [FiscalDocumentController::class, 'index'])->middleware('can:fiscal_documents.view')->name('fiscal-documents.index');
            Route::get('/fiscal-documents/{fiscalDocument}', [FiscalDocumentController::class, 'show'])->middleware('can:fiscal_documents.view')->whereUuid('fiscalDocument')->name('fiscal-documents.show');
            Route::post('/fiscal-documents/{fiscalDocument}/submit-sunat', [FiscalDocumentController::class, 'submitSunat'])->middleware(['can:fiscal_documents.manage', 'throttle:sunat-submit'])->whereUuid('fiscalDocument')->name('fiscal-documents.submit-sunat');
            Route::get('/fiscal-documents/{fiscalDocument}/submissions/{submission}/artifacts/{artifact}', [FiscalDocumentController::class, 'downloadArtifact'])->middleware('can:fiscal_documents.manage')->whereUuid(['fiscalDocument', 'submission'])->whereIn('artifact', ['signed-xml', 'cdr'])->name('fiscal-documents.artifacts.download');
            Route::post('/orders/{order}/fiscal-document', [FiscalDocumentController::class, 'store'])->middleware('can:fiscal_documents.issue')->whereUuid('order')->name('orders.fiscal-document.store');

            Route::post('/fiscal-series', [FiscalSeriesController::class, 'store'])->middleware('can:fiscal_settings.manage')->name('fiscal-series.store');
            Route::patch('/fiscal-series/{fiscalSeries}', [FiscalSeriesController::class, 'update'])->middleware('can:fiscal_settings.manage')->whereUuid('fiscalSeries')->name('fiscal-series.update');

            Route::get('/fulfillments', [FulfillmentController::class, 'index'])->middleware(['can:fulfillments.view', 'can:shipments.view'])->name('fulfillments.index');
            Route::get('/fulfillments/{fulfillment}', [FulfillmentController::class, 'show'])->middleware(['can:fulfillments.view', 'can:shipments.view'])->whereUuid('fulfillment')->name('fulfillments.show');
            Route::post('/orders/{order}/fulfillments', [FulfillmentController::class, 'store'])->middleware('can:fulfillments.manage')->whereUuid('order')->name('orders.fulfillments.store');
            Route::post('/fulfillments/{fulfillment}/start', [FulfillmentController::class, 'start'])->middleware('can:fulfillments.manage')->whereUuid('fulfillment')->name('fulfillments.start');
            Route::post('/fulfillments/{fulfillment}/complete', [FulfillmentController::class, 'complete'])->middleware('can:fulfillments.complete')->whereUuid('fulfillment')->name('fulfillments.complete');
            Route::post('/fulfillments/{fulfillment}/cancel', [FulfillmentController::class, 'cancel'])->middleware('can:fulfillments.manage')->whereUuid('fulfillment')->name('fulfillments.cancel');
            Route::post('/fulfillments/{fulfillment}/shipment', [ShipmentController::class, 'store'])->middleware('can:shipments.manage')->whereUuid('fulfillment')->name('fulfillments.shipment.store');
            Route::post('/shipments/{shipment}/ready', [ShipmentController::class, 'ready'])->middleware('can:shipments.manage')->whereUuid('shipment')->name('shipments.ready');
            Route::post('/shipments/{shipment}/ship', [ShipmentController::class, 'ship'])->middleware('can:shipments.manage')->whereUuid('shipment')->name('shipments.ship');
            Route::post('/shipments/{shipment}/deliver', [ShipmentController::class, 'deliver'])->middleware('can:shipments.manage')->whereUuid('shipment')->name('shipments.deliver');
            Route::post('/shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])->middleware('can:shipments.manage')->whereUuid('shipment')->name('shipments.cancel');

            Route::get('/payments', [PaymentController::class, 'index'])->middleware('can:payments.view')->name('payments.index');
            Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('can:payments.view')->whereUuid('payment')->name('payments.show');
            Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->middleware('can:payments.manage')->whereUuid('order')->name('orders.payments.store');
            Route::post('/payments/{payment}/capture', [PaymentController::class, 'capture'])->middleware('can:payments.capture')->whereUuid('payment')->name('payments.capture');
            Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->middleware('can:payments.manage')->whereUuid('payment')->name('payments.cancel');
            Route::match(['put', 'patch'], '/orders/{order}/payment-terms', [OrderPaymentTermsController::class, 'update'])->middleware('can:payments.manage')->whereUuid('order')->name('orders.payment-terms.update');
            Route::get('/settings/payments', [PaymentSettingsController::class, 'show'])->middleware('can:payments.settings')->name('payments.settings.show');
            Route::match(['put', 'patch'], '/settings/payments', [PaymentSettingsController::class, 'update'])->middleware('can:payments.settings')->name('payments.settings.update');

            Route::get('/refunds', [RefundController::class, 'index'])->middleware('can:refunds.view')->name('refunds.index');
            Route::get('/refunds/{refund}', [RefundController::class, 'show'])->middleware('can:refunds.view')->whereUuid('refund')->name('refunds.show');
            Route::post('/payments/{payment}/refunds', [RefundController::class, 'store'])->middleware('can:refunds.manage')->whereUuid('payment')->name('payments.refunds.store');
            Route::post('/refunds/{refund}/complete', [RefundController::class, 'complete'])->middleware('can:refunds.manage')->whereUuid('refund')->name('refunds.complete');

            Route::get('/returns', [ReturnController::class, 'index'])->middleware('can:returns.view')->name('returns.index');
            Route::get('/returns/{return}', [ReturnController::class, 'show'])->middleware('can:returns.view')->whereUuid('return')->name('returns.show');
            Route::post('/orders/{order}/returns', [ReturnController::class, 'store'])->middleware('can:returns.manage')->whereUuid('order')->name('orders.returns.store');
            Route::post('/returns/{return}/approve', [ReturnController::class, 'approve'])->middleware('can:returns.manage')->whereUuid('return')->name('returns.approve');
            Route::post('/returns/{return}/reject', [ReturnController::class, 'reject'])->middleware('can:returns.manage')->whereUuid('return')->name('returns.reject');
            Route::post('/returns/{return}/receive', [ReturnController::class, 'receive'])->middleware('can:returns.manage')->whereUuid('return')->name('returns.receive');
            Route::post('/returns/{return}/complete', [ReturnController::class, 'complete'])->middleware('can:returns.manage')->whereUuid('return')->name('returns.complete');
            Route::post('/returns/{return}/cancel', [ReturnController::class, 'cancel'])->middleware('can:returns.manage')->whereUuid('return')->name('returns.cancel');
            Route::post('/return-items/{item}/inspect', [ReturnItemController::class, 'inspect'])->middleware('can:returns.inspect')->whereUuid('item')->name('return-items.inspect');
            Route::post('/return-items/{item}/restock', [ReturnItemController::class, 'restock'])->middleware('can:returns.restock')->whereUuid('item')->name('return-items.restock');

            Route::get('/exchanges', [ExchangeController::class, 'index'])->middleware('can:exchanges.view')->name('exchanges.index');
            Route::get('/exchanges/{exchange}', [ExchangeController::class, 'show'])->middleware('can:exchanges.view')->whereUuid('exchange')->name('exchanges.show');
            Route::post('/returns/{return}/exchanges', [ExchangeController::class, 'store'])->middleware('can:exchanges.manage')->whereUuid('return')->name('returns.exchanges.store');
            Route::post('/exchanges/{exchange}/approve', [ExchangeController::class, 'approve'])->middleware('can:exchanges.manage')->whereUuid('exchange')->name('exchanges.approve');
            Route::post('/exchanges/{exchange}/complete', [ExchangeController::class, 'complete'])->middleware('can:exchanges.manage')->whereUuid('exchange')->name('exchanges.complete');
            Route::post('/exchanges/{exchange}/cancel', [ExchangeController::class, 'cancel'])->middleware('can:exchanges.manage')->whereUuid('exchange')->name('exchanges.cancel');

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
