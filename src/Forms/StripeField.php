<?php

namespace Innoweb\SilvershopStripe\Forms;

use SilverStripe\Forms\TextField;

/**
 * Encapsulates a stripe custom field with a data-stripe attribute and name and value suppressed.
 */
class StripeField extends TextField
{
    
    public function Field($properties = array())
    {
        return null;
    }
    
    public function getFieldHolderTemplate()
    {
        return 'StripeField_holder';
    }
    
}
