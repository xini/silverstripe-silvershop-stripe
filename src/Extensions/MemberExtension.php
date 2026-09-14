<?php

declare(strict_types=1);

namespace Innoweb\SilvershopStripe\Extensions;

use Innoweb\SilvershopStripe\Model\CreditCard;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

class MemberExtension extends Extension
{
    private static array $db = [
        'StripeCustomerReference' => 'Varchar',
    ];
    
    private static array $has_one = [
        'DefaultCreditCard' => CreditCard::class,
    ];
    
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
