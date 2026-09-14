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
class AttachCardRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('customerReference');
        $this->validate('cardReference');

        $data = [];
        if ($this->getCustomerReference()) {
            $data['customer'] = $this->getCustomerReference();
        }

        return $data;
    }

    public function getEndpoint()
    {
        if ($this->getCustomerReference() && $this->getCardReference()) {
            // Attach a card to an existing customer
            return $this->endpoint . '/payment_methods/' . $this->getCardReference() . '/attach';
        }

        return null;
    }
}
