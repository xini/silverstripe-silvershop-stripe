<?php

namespace Innoweb\SilvershopStripe\Extensions;

use Innoweb\SilvershopStripe\Model\CreditCard;
use SilverStripe\ORM\DataExtension;

class PaymentExtension extends DataExtension
{
    private static $db = [
        'StripePaymentIntentReference' => 'Varchar(255)'
    ];

    private static $has_one = [
        'SavedCreditCard' => CreditCard::class,
    ];
}
