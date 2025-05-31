<?php

namespace Innoweb\SilvershopStripe\Extensions;

use Innoweb\SilvershopStripe\Model\CreditCard;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class MemberExtension extends DataExtension
{
    private static array $db = [
        'StripeCustomerReference' => 'Varchar',
    ];
    
    private static array $has_one = array(
        'DefaultCreditCard' => CreditCard::class,
    );
    
    private static array $has_many = [
        'CreditCards' => CreditCard::class,
    ];
    
    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName('StripeCustomerReference');
        $fields->removeByName('DefaultCreditCardID');
        $fields->removeByName('CreditCards');
    }
    
    public function updateMemberFormFields(FieldList $fields): void
    {
        $fields->removeByName('StripeCustomerReference');
        $fields->removeByName('DefaultCreditCardID');
        $fields->removeByName('CreditCards');
    }
    
}