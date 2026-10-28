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
