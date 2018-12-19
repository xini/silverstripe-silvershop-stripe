<?php

namespace Innoweb\SilvershopStripe\Extensions;

use Innoweb\SilvershopStripe\Model\CreditCard;
use SilverStripe\ORM\DataExtension;

class PaymentExtension extends DataExtension
{
    
    private static $has_one = [
        'SavedCreditCard' => CreditCard::class,
    ];
}