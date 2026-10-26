export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    admin_locale: 'es-PE' | 'en';
}

export interface Tenant {
    id: string;
    name: string;
    slug: string;
}

export interface TenantMembership {
    role: 'OWNER' | 'ADMIN' | 'MEMBER';
    status: 'ACTIVE' | 'INVITED' | 'SUSPENDED' | 'REMOVED';
    permissions: string[];
}

export interface Store {
    id: string;
    name: string;
    slug: string;
    currency: string;
    timezone: string;
}

export interface PageProps extends Record<string, unknown> {
    auth: {
        user: User | null;
    };
    currentTenant: Tenant | null;
    currentTenantMembership: TenantMembership | null;
    currentStore: Store | null;
    unreadNotificationsCount: number;
    status?: string;
    localization: {
        locale: 'es-PE' | 'en';
        fallback: 'es-PE';
        supported: Array<'es-PE' | 'en'>;
    };
}
