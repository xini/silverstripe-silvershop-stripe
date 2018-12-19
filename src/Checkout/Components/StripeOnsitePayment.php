<?php

namespace Innoweb\SilvershopStripe\Checkout\Components;

use Innoweb\SilvershopStripe\Forms\StripeField;
use SilverShop\Checkout\Checkout;
use SilverShop\Checkout\Component\OnsitePayment;
use SilverShop\Model\Order;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\View\Requirements;

/**
 * This component should only ever be used on SSL encrypted pages!
 */
class StripeOnsitePayment extends OnsitePayment
{
    use Injectable;
    use Extensible;
    
    /** @var bool - if for some reason the gateway is not actually stripe, fall back to OnsitePayment */
    protected $isStripe;
    
    /** @var \Omnipay\Common\AbstractGateway|\Omnipay\Stripe\Gateway */
    protected $gateway;
    
    /**
     * @param Order $order
     *
     * @return \Omnipay\Common\AbstractGateway|\Omnipay\Stripe\Gateway
     */
    protected function getGateway($order)
    {
        if (!isset($this->gateway)) {
            $tempPayment = new Payment(
                [
                    'Gateway' => Checkout::get($order)->getSelectedPaymentMethod(),
                ]
            );
            $service = PurchaseService::create($tempPayment);
            $this->gateway = $service->oGateway();
            $this->isStripe = ($this->gateway instanceof \Omnipay\Stripe\Gateway);
        }
        
        return $this->gateway;
    }
    
    /**
     * @param \Omnipay\Common\AbstractGateway|\Omnipay\Stripe\Gateway $gateway
     * @return $this
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        $this->isStripe = ($this->gateway instanceof \Omnipay\Stripe\Gateway);
        return $this;
    }
    
    /**
     * Get form fields for manipulating the current order,
     * according to the responsibility of this component.
     *
     * @param Order $order
     *
     * @return FieldList
     */
    public function getFormFields(Order $order)
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::getFormFields($order);
        }
        
        // Generate the standard set of fields and allow it to be customised
        $fields = FieldList::create(
            [
                $stripeField = StripeField::create(
                    'stripe', 
                    _t(static::class.'.CreditCard', 'Credit or debit card')
                ),
                
                $tokenField = HiddenField::create('token', '', ''),
            ]
        );
        $this->extend('updateFormFields', $fields);
        
        // Generate a basic config and allow it to be customised
        $stripeConfig = Config::inst()->get(GatewayInfo::class, 'Stripe');
        $jsConfig = [
            'formID'        => 'PaymentForm_PaymentForm',
            'stripeField'   => 'PaymentForm_PaymentForm_' . $stripeField->getName(),
            'tokenField'    => 'PaymentForm_PaymentForm_' . $tokenField->getName(),
            'key'           => isset($stripeConfig['parameters']) && isset($stripeConfig['parameters']['publishableKey'])
                                ? $stripeConfig['parameters']['publishableKey']
                                : '',
        ];
        $this->extend('updateStripeConfig', $jsConfig);
        
        if (empty($jsConfig['key'])) {
            user_error('Publishable key was not set. Should be in GatewayInfo.Stripe.parameters.publishableKey.');
        }
        
        // Finally, add the javascript to the page
        Requirements::customScript("window.StripeConfig = " . json_encode($jsConfig), 'StripeJS');
        Requirements::javascript('https://js.stripe.com/v3/');
        Requirements::javascript('resources/innoweb-silvershop-stripe/javascript/checkout.js');
        
        return $fields;
    }

    /**
     * Get the data fields that are required for the component.
     *
     * @param  Order $order [description]
     *
     * @return array        required data fields
     */
    public function getRequiredFields(Order $order)
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::getRequiredFields($order);
        } else {
            return [];
        }
    }

    /**
     * Is this data valid for saving into an order?
     *
     * This function should never rely on form.
     *
     * @param Order $order
     * @param array $data data to be validated
     *
     * @throws ValidationException
     * @return boolean the data is valid
     */
    public function validateData(Order $order, array $data)
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::validateData($order, $data);
        } else {
            // NOTE: Stripe will validate clientside and if for some reason that falls through
            // it will fail on payment and give an error then. It would be a lot of work to get
            // the token to be namespaced so it could be passed here and there would be no point.
            return true;
        }
    }

    /**
     * Get required data out of the model.
     *
     * @param  Order $order order to get data from.
     *
     * @return array        get data from model(s)
     */
    public function getData(Order $order)
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::getData($order);
        } else {
            return [];
        }
    }

    /**
     * Set the model data for this component.
     *
     * This function should never rely on form.
     *
     * @param Order $order
     * @param array $data data to be saved into order object
     *
     * @throws Exception
     * @return Order the updated order
     */
    public function setData(Order $order, array $data)
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::setData($order, $data);
        } else {
            return [];
        }
    }
}
