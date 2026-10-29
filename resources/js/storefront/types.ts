export interface StorefrontImage {
    thumbnailUrl: string | null;
    mediumUrl: string;
    width: number;
    height: number;
    alt: string;
}

export interface PublicPrice {
    baseAmount: string;
    finalAmount: string;
    discountAmount: string;
    currency: string;
    hasDiscount: boolean;
    promotionName: string | null;
}

export interface PublicAvailability {
    state: 'available' | 'unavailable';
    availableUnits: number;
}

export interface PublicVariant {
    key: string;
    name: string;
    isDefault: boolean;
    options: Array<{ name: string; value: string }>;
    price: PublicPrice;
    availability: PublicAvailability;
    images: StorefrontImage[];
}

export interface PublicProduct {
    slug: string;
    url: string;
    name: string;
    description: string | null;
    categories: Array<{ name: string; slug: string; url: string }>;
    price: PublicPrice;
    availability: PublicAvailability;
    image: StorefrontImage | null;
    images: StorefrontImage[];
    variants: PublicVariant[];
}

export interface PublicCategory {
    name: string;
    slug: string;
    productCount: number;
    url: string;
}

export interface PublicStore {
    publicHandle: string;
    name: string;
    description: string | null;
    currency: string;
    timezone: string;
    locale: string;
    theme: 'essential';
    cart: { itemCount: number };
    branding: {
        primaryColor: string;
        accentColor: string;
        secondaryColor: string;
        logoUrl: string | null;
        faviconUrl: string | null;
    };
    contact: {
        email: string | null;
        phone: string | null;
        whatsapp: string | null;
        address: string[];
        socialLinks: Record<string, string>;
    };
}

export interface PublicCartItem {
    key: string;
    productName: string;
    productUrl: string;
    category: string | null;
    variantName: string;
    options: Array<{ name: string; value: string }>;
    image: StorefrontImage | null;
    quantity: number;
    available: number;
    baseUnitPrice: string | null;
    finalUnitPrice: string | null;
    lineTotal: string | null;
    issues: string[];
}

export interface PublicCart {
    itemCount: number;
    lineCount: number;
    currency: string | null;
    subtotalBeforeDiscounts: string;
    discountTotal: string;
    subtotal: string;
    taxTotal: string;
    valid: boolean;
    maxQuantity: number;
    items: PublicCartItem[];
}

export interface PublicCheckoutItem {
    productName: string;
    variantName: string | null;
    quantity: number;
    baseUnitPrice: string | null;
    finalUnitPrice: string | null;
    lineTotal: string | null;
}

export interface PublicCheckout {
    status: 'PENDING' | 'READY';
    ready: boolean;
    expiresAt: string | null;
    currency: string | null;
    itemCount: number;
    items: PublicCheckoutItem[];
    totals: {
        subtotalBeforeDiscounts: string;
        discountTotal: string;
        subtotal: string;
        shippingTotal: string | null;
        taxTotal: string;
        grandTotal: string;
    };
    buyer: Record<string, string | null>;
    commercial: {
        deliveryType: 'PICKUP';
        shippingConfigured: false;
        pickupConfigured: boolean;
        pickup: null | {
            name: string;
            addressLine1: string;
            addressLine2: string | null;
            district: string;
            province: string;
            department: string;
            countryCode: string;
            postalCode: string | null;
            instructions: string | null;
        };
        paymentMethods: Array<'CASH'>;
        payment: null | {
            expectedMethod: 'CASH';
            terms: 'COD';
            depositType: 'NONE';
            depositValue: '0.0000';
        };
        orderCreationAvailable: false;
        restrictions: string[];
    };
}
