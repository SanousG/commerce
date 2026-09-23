<?php

namespace App\Modules\Checkout\Domain\Enums;

enum CheckoutDeliveryType: string
{
    case Shipping = 'SHIPPING';
    case Pickup = 'PICKUP';
}
