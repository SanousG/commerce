<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Cart\Application\FindCartByToken;
use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Cart\Domain\Models\Cart;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class PublicCartIdentity
{
    public function __construct(private readonly FindCartByToken $findCart) {}

    public function resolve(Request $request, PublicStoreContext $context): ?Cart
    {
        $store = $context->store();
        $publication = $context->publication();
        if ($store === null || $publication === null) {
            return null;
        }

        $token = $request->cookie($this->cookieName($publication->public_handle));
        if (! is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        try {
            $cart = $this->findCart->handle($store, $token);
        } catch (ModelNotFoundException) {
            return null;
        }

        return $cart->status === CartStatus::Active ? $cart : null;
    }

    public function cookie(Request $request, PublicStoreContext $context, Cart $cart): Cookie
    {
        $publication = $context->publication();
        abort_unless($publication !== null, 404);

        return Cookie::create($this->cookieName($publication->public_handle))
            ->withValue($cart->token)
            ->withExpires($cart->expires_at)
            ->withPath('/tienda/'.$publication->public_handle)
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public function cookieName(string $publicHandle): string
    {
        return 'sf_cart_'.substr(hash('sha256', $publicHandle), 0, 16);
    }
}
