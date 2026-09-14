<?php

declare(strict_types=1);

namespace Innoweb\SilvershopStripe\Checkout;

use Innoweb\SilvershopStripe\Checkout\Components\StripeOnsitePayment;
use Innoweb\SilvershopStripe\Model\CreditCard;
use Innoweb\SilvershopStripe\Omnipay\Message\AttachCardRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Http\Client as OmnipayClient;
use SilverShop\Checkout\OrderProcessor;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\Exception\Exception;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Security\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class StripeOrderProcessor extends OrderProcessor
{

    /**
     * Handle payment with Stripe's stored customer and credit card details
     *
     * @param  string $gateway     the gateway to use
     * @param  array  $gatewaydata the data that should be passed to the gateway
     * @param  string $successUrl  (optional) return URL for successful payments.
     *                             If left blank, the default return URL will be
     *                             used @see getReturnUrl
     * @param  string $cancelUrl   (optional) return URL for cancelled/failed payments
     * @throws InvalidConfigurationException
     */
    public function makePayment($gateway, $gatewaydata = [], $successUrl = null, $cancelUrl = null): ?ServiceResponse
    {
        // only do this for Stripe
        if (!in_array($gateway, ['Stripe', 'Stripe_PaymentIntents'])) {
            return parent::makePayment($gateway, $gatewaydata, $successUrl, $cancelUrl);
        }

        //create payment
        $payment = $this->createPayment($gateway);
        if (!$payment) {
            //errors have been stored.
            return null;
        }

        $payment->setSuccessUrl($successUrl ?: $this->getReturnUrl());

        // Explicitly set the cancel URL
        if ($cancelUrl) {
            $payment->setFailureUrl($cancelUrl);
        }

        // Create a payment service, by using the Service Factory. This will automatically choose an
        // AuthorizeService or PurchaseService, depending on Gateway configuration.
        // Set the user-facing success URL for redirects
        /**
         * @var ServiceFactory $factory
         */
        $factory = ServiceFactory::create();
        $service = $factory->getService($payment, ServiceFactory::INTENT_PAYMENT);

        $gatewaydata = $this->getGatewayData($gatewaydata);

        // save stripe customer and credit card, update data
        $gatewaydata = $this->saveCustomerAndCard($gateway, $service, $payment, $gatewaydata);

        // Initiate payment, get the result back
        try {
            $serviceResponse = $service->initiate($gatewaydata);
        } catch (Exception $exception) {
            // error out when an exception occurs
            $this->error($exception->getMessage());
            return null;
        }

        // Check if the service response itself contains an error
        if ($serviceResponse->isError()) {
            if ($opResponse = $serviceResponse->getOmnipayResponse()) {
                $this->error($opResponse->getMessage());
            } else {
                $this->error('An unspecified payment error occurred. Please check the payment messages.');
            }
        }

        // For an OFFSITE payment, serviceResponse will now contain a redirect
        // For an ONSITE payment, ShopPayment::onCaptured will have been called, which will have called completePayment

        return $serviceResponse;
    }

    /**
     * Store customer and credit card reference
     */
    protected function saveCustomerAndCard(string $gatewayName, PaymentService $service, Payment $payment, array $gatewaydata): array
    {
        if ($payment instanceof Payment
            && $gatewayName === 'Stripe_PaymentIntents'
            && Config::inst()->get(StripeOnsitePayment::class, 'enable_saved_cards')
        ) {
            // update member and credit card
            $member = Security::getCurrentUser();
            if (!$member) {
                $member = $this->order->Member();
            }

            if ($member && $member->exists()) {
                // create new customer object in Stripe and store reference
                if (!$member->StripeCustomerReference) {
                    $stripeData = [
                        'email' => $member->Email,
                        'description' => $member->getName(),
                    ];
                    $request = $service->oGateway()->createCustomer($stripeData);
                    $response = $request->send();
                    if ($response->isSuccessful()) {
                        $member->StripeCustomerReference = $response->getCustomerReference();
                        $member->write();
                    } else {
                        $this->error($response->getMessage());
                    }
                }

                // create new card if new one submitted
                if ($member->StripeCustomerReference && $member->CreditCards()->filter('CardReference', $gatewaydata['token'])->count() == 0) {
                    if (empty($gatewaydata['SavedCreditCardID']) || $gatewaydata['SavedCreditCardID'] == 'newcard') {
                        try {
                            $gatewayFactory = Injector::inst()->get(GatewayFactory::class);
                            $gateway = $gatewayFactory->create($gatewayName);
                            $parameters = GatewayInfo::getParameters($gatewayName);
                            if (is_array($parameters)) {
                                $gateway->initialize($parameters);
                            }

                            $obj = new AttachCardRequest(new OmnipayClient(), SymfonyRequest::createFromGlobals());
                            $attachCardRequest = $obj->initialize(array_replace($gateway->getParameters(), $parameters));
                            $attachCardRequest->setCustomerReference($member->StripeCustomerReference);
                            $attachCardRequest->setCardReference($gatewaydata['token']);

                            $response = $attachCardRequest->send();
                            if ($response->isSuccessful()) {
                                // save card
                                $card = CreditCard::get()->find('CardReference', $gatewaydata['token']);
                                if (!$card || !$card->exists()) {
                                    $card = CreditCard::create();
                                    $card->CardReference = $gatewaydata['token'];
                                    $card->write();
                                }

                                // add card to member
                                $member->CreditCards()->add($card);
                                if (!$member->DefaultCreditCardID) {
                                    $member->DefaultCreditCardID = $card->ID;
                                }

                                $member->write();
                                // add card to payment
                                $payment->SavedCreditCardID = $card->ID;
                                $payment->write();
                            }
                        } catch (InvalidRequestException) {
                        }
                    } else {
                        // this will have been validated in OnsitePaymentCheckoutComponent
                        $payment->SavedCreditCardID = CreditCard::get()->find('CardReference', $gatewaydata['SavedCreditCardID'])->ID;
                        $payment->write();
                    }
                }

                // update stripe data, replacing token with customer/card
                if ($member->StripeCustomerReference) {
                    // remove token already used for customer creation, replace with customer reference
                    $gatewaydata['customerReference'] = $member->StripeCustomerReference;

                    // add credit card reference for this payment if available
                    if ($card = $payment->SavedCreditCard()) {
                        $gatewaydata['cardReference'] = $card->CardReference;
                    }
                }
            }
        }

        return $gatewaydata;
    }

    protected function getGatewayData($customData): array
    {
        $data = parent::getGatewayData($customData);

        // add description
        $data['description'] = $data['firstName'] . ' ' . $data['lastName'] . ' | ';
        if (($address = $this->order->BillingAddress())
            && $address->exists()
            && $address->Company
        ) {
            $data['description'] .= $address->Company . ' | ';
        }

        $data['description'] .= $data['email'] . ' | ' . $data['transactionId'] . ' ';

        $this->order->extend('updateGetGatewayData', $data);

        return $data;
    }
}
