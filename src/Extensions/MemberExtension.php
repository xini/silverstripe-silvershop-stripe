<?php

namespace Innoweb\SilvershopStripe\Extensions;

use Innoweb\SilvershopStripe\Model\CreditCard;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class MemberExtension extends DataExtension
{
    private static $db = [
        'StripeCustomerReference' => 'Varchar',
    ];
    
    private static $has_one = array(
        'DefaultCreditCard' => CreditCard::class,
    );
    
    private static $has_many = [
        'CreditCards' => CreditCard::class,
    ];
    
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('StripeCustomerReference');
    }
    
}