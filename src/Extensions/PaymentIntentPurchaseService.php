<?php


namespace Innoweb\SilvershopStripe\Extensions;


use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;

class PaymentIntentPurchaseService extends Extension
{
    /**
     * Adds required data
     *
     * @param array $data
     */
    public function onBeforePurchase(array &$data)
    {
        $payment = $this->owner->getPayment();

        /** @var Order $order */
        $order = $payment->Order();

        if ($payment->Gateway === 'Stripe_PaymentIntents') {
            $data['paymentMethod'] = $data['token'];
            unset($data['token']);
            $data['confirm'] = true;

			// Get the config values for the failure url (if specified)
			$stripeConfig = Config::inst()->get(GatewayInfo::class, 'Stripe_PaymentIntents');
			
			if(isset($stripeConfig['failureUrl']))
				$payment->setFailureUrl($stripeConfig['failureUrl']);
        }


    }

    public function onAfterSendPurchase(RequestInterface $request,ResponseInterface $response)
    {
        $payment = $this->owner->getPayment();

        if ($response instanceof \Omnipay\Stripe\Message\PaymentIntents\Response) {
            // Store the Payment Intent reference for later...
            $payment->StripePaymentIntentReference = $response->getPaymentIntentReference();
            $payment->write();
        }
    }

    public function onBeforeCompletePurchase(array &$data = [])
    {
        // Hack to get the payment, as silverstripe-omnipay doesn't currently
        // provide a getPayment() method in PaymentService
        $reflectionProperty = new \ReflectionProperty($this->owner::class, 'payment');
        $reflectionProperty->setAccessible(true);

        $payment = $reflectionProperty->getValue($this->owner);
        if ($payment->StripePaymentIntentReference) {
            // Pass the Payment Intent reference with the transaction data to Stripe
            $data['paymentIntentReference'] = $payment->StripePaymentIntentReference;
        }
    }
}
