export interface AdminNavItem {
    label: string;
    href: string;
    icon: string;
    permission?: string;
}

export interface AdminNavSection {
    label: string;
    items: AdminNavItem[];
}

export const adminNavigation: AdminNavSection[] = [
    { label: 'Overview', items: [
        { label: 'Dashboard', href: '/admin/dashboard', icon: 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z' },
        { label: 'Notifications', href: '/admin/notifications', icon: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4' },
    ] },
    { label: 'Catalog', items: [
        { label: 'Products', href: '/admin/products', permission: 'products.view', icon: 'M4 7h16v13H4V7Zm3-3h10v3H7V4Zm1 7h8' },
        { label: 'Media', href: '/admin/media', permission: 'media.view', icon: 'M4 5h16v14H4V5Zm3 10 3-3 2 2 2-2 3 3M8 9h.01' },
        { label: 'Categories', href: '/admin/categories', permission: 'categories.view', icon: 'M4 6h6v5H4V6Zm10 0h6v5h-6V6ZM4 15h6v5H4v-5Zm10 0h6v5h-6v-5Z' },
        { label: 'Listings', href: '/admin/listings', permission: 'listings.view', icon: 'M5 5h14v14H5V5Zm4 0v14M9 9h10M9 14h10' },
    ] },
    { label: 'Merchandising', items: [
        { label: 'Pricing', href: '/admin/pricing', permission: 'pricing.view', icon: 'M4 6h16v12H4V6Zm3 4h4m-4 4h7' },
        { label: 'Promotions', href: '/admin/promotions', permission: 'promotions.view', icon: 'm4 12 8-8 8 8-8 8-8-8Zm5-3h.01M15 15h.01' },
    ] },
    { label: 'Sales', items: [
        { label: 'Orders', href: '/admin/orders', permission: 'orders.view', icon: 'M5 4h14l1 16H4L5 4Zm3 4a4 4 0 0 0 8 0' },
        { label: 'Carts', href: '/admin/carts', permission: 'carts.view', icon: 'M3 4h2l2 11h10l3-8H6m2 13h.01M17 20h.01' },
        { label: 'Checkouts', href: '/admin/checkouts', permission: 'checkouts.view', icon: 'M5 4h14v16H5V4Zm4 4h6m-6 4h6m-6 4h4' },
        { label: 'Payments', href: '/admin/payments', permission: 'payments.view', icon: 'M3 6h18v12H3V6Zm0 4h18M7 15h3' },
    ] },
    { label: 'Operations', items: [
        { label: 'Fulfillment', href: '/admin/fulfillments', permission: 'fulfillments.view', icon: 'M3 7h11v10H3V7Zm11 3h4l3 3v4h-7v-7ZM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z' },
        { label: 'Returns', href: '/admin/returns', permission: 'returns.view', icon: 'm9 7-5 5 5 5M4 12h10a6 6 0 0 1 6 6' },
        { label: 'Refunds', href: '/admin/refunds', permission: 'refunds.view', icon: 'M20 7H7a4 4 0 0 0 0 8h10M8 4 4 7l4 3' },
        { label: 'Exchanges', href: '/admin/exchanges', permission: 'exchanges.view', icon: 'm7 7-4 4 4 4m-4-4h18M17 3l4 4-4 4' },
        { label: 'Customers', href: '/admin/customers', permission: 'customers.view', icon: 'M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 1a4 4 0 0 1 4 4v2m-5-15a4 4 0 0 1 0 8' },
    ] },
    { label: 'Inventory', items: [
        { label: 'Stock', href: '/admin/inventory', permission: 'inventory.view', icon: 'M4 9h16v11H4V9Zm2-5h12l2 5H4l2-5Zm3 9h6' },
        { label: 'Valuation', href: '/admin/inventory/valuation', permission: 'inventory.cost.view', icon: 'M4 19V9m5 10V5m5 14v-7m5 7V3' },
        { label: 'Locations', href: '/admin/inventory/locations', permission: 'inventory.view', icon: 'M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Zm-8 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z' },
    ] },
    { label: 'Procurement', items: [
        { label: 'Suppliers', href: '/admin/suppliers', permission: 'suppliers.view', icon: 'M3 20h18M5 20V8l7-4 7 4v12M9 12h6m-6 4h6' },
        { label: 'Purchase orders', href: '/admin/purchase-orders', permission: 'procurement.view', icon: 'M6 3h12v18H6V3Zm3 5h6m-6 4h6m-6 4h4' },
    ] },
    { label: 'Compliance', items: [
        { label: 'Fiscal documents', href: '/admin/fiscal-documents', permission: 'fiscal_documents.view', icon: 'M6 3h9l3 3v15H6V3Zm8 0v4h4M9 12h6m-6 4h6' },
        { label: 'Taxes', href: '/admin/taxes', permission: 'taxes.view', icon: 'm7 17 10-10M7 8h.01M17 16h.01' },
    ] },
    { label: 'Workspace', items: [
        { label: 'Activity', href: '/admin/activity', permission: 'audit.view', icon: 'M12 8v5l3 2m6-3a9 9 0 1 1-3-6.7M21 3v6h-6' },
    ] },
];
