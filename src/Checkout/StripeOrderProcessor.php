<?php

namespace Innoweb\SilvershopStripe\Checkout;

use Innoweb\SilvershopStripe\Model\CreditCard;
use SilverShop\Checkout\OrderProcessor;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Security\Security;
use SilverStripe\Core\Config\Config;
use Innoweb\SilvershopStripe\Checkout\Components\StripeOnsitePayment;

class StripeOrderProcessor extends OrderProcessor
{

    /**
     * Handle payment with Stripe's stored customer and credit card details
     *
     * @param string $gateway the gateway to use
     * @param array $gatewaydata the data that should be passed to the gateway
     * @param string $successUrl (optional) return URL for successful payments.
     *                            If left blank, the default return URL will be
     *                            used @see getReturnUrl
     * @param string $cancelUrl (optional) return URL for cancelled/failed payments
     *
     * @return ServiceResponse|null
     * @throws \SilverStripe\Omnipay\Exception\InvalidConfigurationException
     */
    public function makePayment($gateway, $gatewaydata = array(), $successUrl = null, $cancelUrl = null)
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

        $payment->setSuccessUrl($successUrl ? $successUrl : $this->getReturnUrl());

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
        $gatewaydata = $this->saveCustomerAndCard($service, $payment, $gatewaydata);

        // Initiate payment, get the result back
        try {
            $serviceResponse = $service->initiate($gatewaydata);
        } catch (\SilverStripe\Omnipay\Exception\Exception $ex) {
            // error out when an exception occurs
            $this->error($ex->getMessage());
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
     * store customer and credit card reference
     *
     * @param PaymentService $service
     * @param Payment $payment
     * @param array $gatewaydata
     * @return null
     */
    protected function saveCustomerAndCard($service, $payment, $gatewaydata)
    {
        if ($payment) {

            // only do this for Stripe
            if ($payment->Gateway != 'Stripe') {
                return $gatewaydata;
            }

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
                        'description' => $member->getName()
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
                if ($member->StripeCustomerReference) {
                    if (empty($gatewaydata['SavedCreditCardID']) || $gatewaydata['SavedCreditCardID'] == 'newcard') {

                        $request = $service->oGateway()->createCard([
                            'cardReference' => isset($gatewaydata['token']) ? $gatewaydata['token'] : '',
                            'customerReference' => $member->StripeCustomerReference,
                        ]);
                        $response = $request->send();
                        if ($response->isSuccessful()) {
                            // save card
                            $card = CreditCard::create();
                            $card->CardReference = $response->getCardReference();
                            $card->write();
                            // add card to member
                            $member->CreditCards()->add($card);
                            if (!$member->DefaultCreditCardID) {
                                $member->DefaultCreditCardID = $card->ID;
                            }
                            $member->write();
                            // add card to payment
                            $payment->SavedCreditCardID = $card->ID;
                            $payment->write();
                        } else {
                            $this->error($response->getMessage());
                        }

                    } else {
                        // this will have been validated in OnsitePaymentCheckoutComponent
                        $payment->SavedCreditCardID = $gatewaydata['SavedCreditCardID'];
                        $payment->write();
                    }
                }

                // update stripe data, replacing token with customer/card
                if ($member->StripeCustomerReference) {

                    // remove token already used for customer creation, replace with customer reference
                    unset($gatewaydata['token']);
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

    protected function getGatewayData($customData)
    {
        $data = parent::getGatewayData($customData);

        // add description
        $data['description'] = $data['firstName'] . ' ' . $data['lastName'] . ' | ';
        if ($this->order->BillingAddress()->Company) {
            $data['description'] .= (string) $this->order->BillingAddress()->Company . ' | ';
        }
        $data['description'] .= $data['email'] . ' | ' . $data['transactionId'] . ' ';

        $this->order->extend('updateGetGatewayData', $data);

        return $data;
    }

}
