<?php

namespace App\Modules\Access\Domain\Enums;

enum PermissionKey: string
{
    case TenantView = 'tenant.view';
    case TenantManage = 'tenant.manage';
    case StoresView = 'stores.view';
    case StoresCreate = 'stores.create';
    case StoresUpdate = 'stores.update';
    case StoresSelect = 'stores.select';
    case MembersView = 'members.view';
    case MembersManage = 'members.manage';
    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsUpdate = 'products.update';
    case ProductsArchive = 'products.archive';
    case CategoriesView = 'categories.view';
    case CategoriesCreate = 'categories.create';
    case CategoriesUpdate = 'categories.update';
    case CategoriesArchive = 'categories.archive';
    case PricingView = 'pricing.view';
    case PricingCreate = 'pricing.create';
    case PricingUpdate = 'pricing.update';
    case PricingArchive = 'pricing.archive';
    case PromotionsView = 'promotions.view';
    case PromotionsCreate = 'promotions.create';
    case PromotionsUpdate = 'promotions.update';
    case PromotionsArchive = 'promotions.archive';
    case InventoryView = 'inventory.view';
    case InventoryAdjust = 'inventory.adjust';
    case ListingsView = 'listings.view';
    case ListingsCreate = 'listings.create';
    case ListingsUpdate = 'listings.update';
    case ListingsPublish = 'listings.publish';
    case ListingsArchive = 'listings.archive';
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';
    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersUpdate = 'customers.update';
    case CustomersArchive = 'customers.archive';
    case CartsView = 'carts.view';
    case CartsManage = 'carts.manage';
    case CheckoutsView = 'checkouts.view';
    case CheckoutsManage = 'checkouts.manage';
    case AnalyticsView = 'analytics.view';
    case SettingsView = 'settings.view';
    case SettingsManage = 'settings.manage';
}
