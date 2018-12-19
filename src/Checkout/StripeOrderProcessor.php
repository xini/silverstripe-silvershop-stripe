<?php

namespace Innoweb\SilvershopStripe\Checkout;

use Innoweb\SilvershopStripe\Model\CreditCard;
use Psr\Log\LoggerInterface;
use SilverShop\Checkout\OrderProcessor;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Security\Security;

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
        Injector::inst()->get(LoggerInterface::class)->debug('StripeOrderProcessor called: '.$gateway);
        // only do this for Stripe
        if ($gateway != 'Stripe') {
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
        
        $data = $this->getGatewayData($gatewaydata);
        
        // save stripe customer and credit card
        $this->saveCustomerAndCard($service, $payment, $data);

        // Initiate payment, get the result back
        try {
            $serviceResponse = $service->initiate($data);
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
     * @param array $data
     * @return null
     */
    protected function saveCustomerAndCard($service, $payment, $data)
    {
        Injector::inst()->get(LoggerInterface::class)->debug('called');
        if ($payment) {
            Injector::inst()->get(LoggerInterface::class)->debug('payment found');
            
            // only do this for Stripe
            if ($payment->Gateway != 'Stripe') {
                return;
            }
            Injector::inst()->get(LoggerInterface::class)->debug('is stripe');
            
            // update member and credit card
            if (($member = (Security::getCurrentUser() || $this->order->Member()) && $member->exists()) {
                
                Injector::inst()->get(LoggerInterface::class)->debug('member found');
                
                // create new customer object in Stripe and store reference
                if (!$member->StripeCustomerReference) {
                    $request = $service->oGateway()->createCustomer(array_merge(
                        array_intersect( // only submit the following fields
                            $data, 
                            [
                                'email',
                            ]
                        ),
                        [ // add the following custom fields
                            'description' => $member->getName()
                        ]
                    ));
                    $response = $request->send();
                    if ($response->isSuccessful()) {
                        $member->StripeCustomerReference = $response->getCustomerReference();
                        $member->write();
                        Injector::inst()->get(LoggerInterface::class)->debug('customer reference written');
                    } else {
                        $this->error($response->getMessage());
                        Injector::inst()->get(LoggerInterface::class)->debug('error: '.$response->getMessage());
                    }
                }
                
                // create new card if new one submitted
                if ($member->StripeCustomerReference && (empty($data['SavedCreditCardID']) || $data['SavedCreditCardID'] == 'newcard')) {
                    
                    Injector::inst()->get(LoggerInterface::class)->debug('new card');
                    
                    $request = $service->oGateway()->createCard([
                        'cardReference' => isset($data['token']) ? $data['token'] : '',
                        'customerReference' => $member->StripeCustomerReference,
                    ]);
                    $response = $request->send();
                    if ($response->isSuccessful()) {
                        // save card
                        $card = CreditCard::create();
                        $card->CardReference = $response->getCardReference();
                        if ($responseData = $response->getData()) {
                            $card->LastFourDigits = isset($responseData['last4']) ? $responseData['last4'] : null;
                            $card->Brand = isset($responseData['brand']) ? $responseData['brand'] : null;
                            $card->ExpMonth = isset($responseData['exp_month']) ? $responseData['exp_month'] : null;
                            $card->ExpYear = isset($responseData['exp_year']) ? $responseData['exp_year'] : null;
                        }
                        $card->write();
                        Injector::inst()->get(LoggerInterface::class)->debug('card written');
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
                        Injector::inst()->get(LoggerInterface::class)->debug('error: '.$response->getMessage());
                    }
                }
                
            }
            
            if (isset($data['SavedCreditCardID']) && $data['SavedCreditCardID'] !== 'newcard') {
                // this will have been validated in OnsitePaymentCheckoutComponent
                $payment->SavedCreditCardID = $data['SavedCreditCardID'];
                $payment->write();
            }
        }
    }
    
    protected function getGatewayData($customData)
    {
        $data = parent::getGatewayData($customData);
        
        // add description
        $data['description'] = 'Payment by '.$data['firstName'].' '.$data['lastName'].' ('.$data['email'].')';
        
        return $data;
    }

}
