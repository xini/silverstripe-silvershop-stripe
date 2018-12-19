<?php

namespace Innoweb\SilvershopStripe\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class CreditCard extends DataObject
{
    
    private static $table_name = 'CreditCard';
    
    private static $db = [
        'CardReference' => 'Varchar(50)',
        'LastFourDigits' => 'Varchar(4)',
        'Brand' => 'Varchar(50)',
        'ExpMonth' => 'Int',
        'ExpYear' => 'Int',
    ];
    
    private static $has_one = [
        'Member' => Member::class,
    ];
}