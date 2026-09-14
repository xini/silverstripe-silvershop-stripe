<?php

declare(strict_types=1);

namespace Innoweb\SilvershopStripe\Extensions;

use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Stripe\Message\PaymentIntents\Response;
use ReflectionProperty;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\GatewayInfo;

class PaymentIntentPurchaseService extends Extension
{
    /**
     * Adds required data
     */
    public function onBeforePurchase(array &$data): void
    {
        $payment = $this->getOwner()->getPayment();

        // @var Order $order
        $payment->Order();

        if ($payment->Gateway === 'Stripe_PaymentIntents') {
            $data['paymentMethod'] = $data['token'];
            unset($data['token']);
            $data['confirm'] = true;

            // Get the config values for the failure url (if specified)
            $stripeConfig = Config::inst()->get(GatewayInfo::class, 'Stripe_PaymentIntents');

            if (isset($stripeConfig['failureUrl'])) {
                $payment->setFailureUrl($stripeConfig['failureUrl']);
            }
        }
    }

    public function onAfterSendPurchase(RequestInterface $request, ResponseInterface $response): void
    {
        $payment = $this->getOwner()->getPayment();

        if ($response instanceof Response) {
            // Store the Payment Intent reference for later...
            $payment->StripePaymentIntentReference = $response->getPaymentIntentReference();
            $payment->write();
        }
    }

    public function onBeforeCompletePurchase(array &$data = []): void
    {
        // Hack to get the payment, as silverstripe-omnipay doesn't currently
        // provide a getPayment() method in PaymentService
        $reflectionProperty = new ReflectionProperty($this->getOwner()::class, 'payment');

        $payment = $reflectionProperty->getValue($this->getOwner());
        if ($payment->StripePaymentIntentReference) {
            // Pass the Payment Intent reference with the transaction data to Stripe
            $data['paymentIntentReference'] = $payment->StripePaymentIntentReference;
        }
    }
}
