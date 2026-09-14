<?php

declare(strict_types=1);

namespace Innoweb\SilvershopStripe\Extensions;

use Innoweb\SilvershopStripe\Model\CreditCard;
use SilverStripe\Core\Extension;

class PaymentExtension extends Extension
{
    private static array $db = [
        'StripePaymentIntentReference' => 'Varchar(255)'
    ];

    private static array $has_one = [
        'SavedCreditCard' => CreditCard::class,
    ];
}
