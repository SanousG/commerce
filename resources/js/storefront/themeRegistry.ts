import type { Component } from 'vue';
import EssentialCatalog from './themes/essential/pages/Catalog.vue';
import EssentialCart from './themes/essential/pages/Cart.vue';
import EssentialCheckout from './themes/essential/pages/Checkout.vue';
import EssentialHome from './themes/essential/pages/Home.vue';
import EssentialProduct from './themes/essential/pages/Product.vue';

export const storefrontThemes = {
    essential: {
        status: 'available',
        type: 'default',
        pages: { Home: EssentialHome, Catalog: EssentialCatalog, Product: EssentialProduct, Cart: EssentialCart, Checkout: EssentialCheckout },
    },
} as const;

export type StorefrontPage = keyof (typeof storefrontThemes)['essential']['pages'];

export function resolveStorefrontPage(theme: string, page: StorefrontPage): Component {
    const resolved = theme === 'essential' ? storefrontThemes.essential : storefrontThemes.essential;
    return resolved.pages[page];
}
