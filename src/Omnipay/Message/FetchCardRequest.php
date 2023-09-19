<?php

/**
 * Stripe Fetch Credit Card Request.
 */
namespace Innoweb\SilvershopStripe\Omnipay\Message;

use Omnipay\Stripe\Message\AbstractRequest;

/**
 * Stripe Fetch Credit Card Request.
 *
 * @link https://stripe.com/docs/api/cards/retrieve#retrieve_card
 */
class FetchCardRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('customerReference');
        $this->validate('cardReference');
        return array();
    }

    public function getHttpMethod()
    {
        return 'GET';
    }

    public function getEndpoint()
    {
        if ($this->getCustomerReference() && $this->getCardReference()) {
            // Create a new card on an existing customer
            return $this->endpoint.'/customers/'.$this->getCustomerReference()
                .'/sources/'.$this->getCardReference();
        }
        return;
    }
}
