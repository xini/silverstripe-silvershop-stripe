<?php

declare(strict_types=1);

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
        return [];
    }

    public function getHttpMethod()
    {
        return 'GET';
    }

    public function getEndpoint()
    {
        if ($this->getCustomerReference() && $this->getCardReference()) {
            // Get card details
            return $this->endpoint . '/customers/' . $this->getCustomerReference()
                . '/payment_methods/' . $this->getCardReference();
        }

        return null;
    }
}
