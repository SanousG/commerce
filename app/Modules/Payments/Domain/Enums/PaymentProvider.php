<?php

namespace App\Modules\Payments\Domain\Enums;

enum PaymentProvider: string
{
    case Manual = 'MANUAL';
    case None = 'NONE';
    case Stripe = 'STRIPE';
    case MercadoPago = 'MERCADO_PAGO';
    case Culqi = 'CULQI';
}
