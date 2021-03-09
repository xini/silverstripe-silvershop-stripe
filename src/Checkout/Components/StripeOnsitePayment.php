<?php

namespace Innoweb\SilvershopStripe\Checkout\Components;

use Innoweb\SilvershopStripe\Forms\StripeField;
use SilverShop\Checkout\Checkout;
use SilverShop\Checkout\Component\OnsitePayment;
use SilverShop\Model\Order;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Security\Member;
use SilverStripe\View\Requirements;
use SilverStripe\Security\Security;
use SilverStripe\Forms\OptionsetField;
use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\FormField;

/**
 * This component should only ever be used on SSL encrypted pages!
 */
class StripeOnsitePayment extends OnsitePayment
{
    use Injectable;
    use Extensible;
    use Configurable;

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
                $stripeField = StripeField::create('stripe', _t(static::class.'.CreditCard', 'Credit or debit card')),
                $tokenField = HiddenField::create('token', '', ''),
            ]
        );
        // load existing card selection field
        $existingCardField = $this->getExistingCardsField();
        if ($existingCardField) {
            $fields->unshift($existingCardField);
            $stripeField->setTitle(_t(static::class.'.NewCreditCard', 'New credit or debit card'));
        }

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
        Requirements::javascript('innoweb/silverstripe-silvershop-stripe:javascript/checkout.js');

        return $fields;
    }

    /**
     * @param Member $member
     * @return bool
     */
    protected function hasExistingCards(Member $member = null) {
        // don't show existing card fields
        if (!$this->config()->get('enable_saved_cards')) {
            return false;
        }
        if (!$member) $member = Security::getCurrentUser();
        return $member && $member->CreditCards()->exists();
    }

    /**
     * Allow choosing from an existing credit cards
     * @return FormField|null field
     */
    public function getExistingCardsField() {
        $member = Security::getCurrentUser();
        if ($this->hasExistingCards($member)) {
            $cardOptions = [];
            $cards = $member->CreditCards()->sort('Created', 'DESC');
            foreach ($cards as $card) {
                $cardOptions[$card->ID] = $card->getTitle();
            }
            $cardOptions['newcard'] = _t('OnsitePaymentCheckoutComponent.CreateNewCard', 'Create a new card');
            $fieldtype = count($cardOptions) > 3 ? DropdownField::class : OptionsetField::class;
            $label = _t("OnsitePaymentCheckoutComponent.ExistingCards", "Existing Credit Cards");
            return $fieldtype::create("SavedCreditCardID", $label,
                $cardOptions,
                $member->DefaultCreditCardID
            )->addExtraClass('existingCreditCards')->setValue($member->DefaultCreditCardID);
        }
        return null;
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
            return $this->hasExistingCards() ? ['SavedCreditCardID'] : [];
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

            // If existing card selected, check that it exists in $member->CreditCards
            $existingID = !empty($data['SavedCreditCardID']) ? (int)$data['SavedCreditCardID'] : 0;
            if ($existingID) {
                if (!Security::getCurrentUser() || !Security::getCurrentUser()->CreditCards()->byID($existingID)) {
                    $result = ValidationResult::create();
                    $result->error("Invalid card supplied", 'SavedCreditCardID');
                    throw new ValidationException($result);
                }
            }

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
