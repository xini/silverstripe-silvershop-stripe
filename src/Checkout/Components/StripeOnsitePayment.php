<?php

declare(strict_types=1);

namespace Innoweb\SilvershopStripe\Checkout\Components;

use Innoweb\SilvershopStripe\Forms\StripeField;
use Omnipay\Stripe\AbstractGateway;
use Omnipay\Stripe\Gateway;
use Omnipay\Stripe\PaymentIntentsGateway;
use SilverShop\Checkout\Checkout;
use SilverShop\Checkout\Component\OnsitePayment;
use SilverShop\Model\Order;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

/**
 * This component should only ever be used on SSL encrypted pages!
 */
class StripeOnsitePayment extends OnsitePayment
{
    use Injectable;
    use Extensible;
    use Configurable;

    private static int $radio_button_limit = 3;

    /**
     * @var bool - if for some reason the gateway is not actually stripe, fall back to OnsitePayment
     */
    protected bool $isStripe;

    /**
     * @var bool - do we use Payment Intents?
     */
    protected bool $isPaymentIntent;

    /**
     * @var \Omnipay\Common\AbstractGateway|AbstractGateway
     */
    protected $gateway;

    /**
     * @return \Omnipay\Common\AbstractGateway|AbstractGateway
     */
    protected function getGateway(Order $order)
    {
        if ($this->gateway === null) {
            $tempPayment = Payment::create([
                'Gateway' => Checkout::get($order)->getSelectedPaymentMethod(),
            ]);
            $service = PurchaseService::create($tempPayment);
            $this->gateway = $service->oGateway();
            $this->isStripe = ($this->gateway instanceof AbstractGateway);
            $this->isPaymentIntent = $this->gateway instanceof PaymentIntentsGateway;
        }

        return $this->gateway;
    }

    /**
     * @param \Omnipay\Common\AbstractGateway|Gateway $gateway
     */
    public function setGateway($gateway): static
    {
        $this->gateway = $gateway;
        $this->isStripe = ($this->gateway instanceof AbstractGateway);
        $this->isPaymentIntent = $this->gateway instanceof PaymentIntentsGateway;
        return $this;
    }

    /**
     * Get form fields for manipulating the current order,
     * according to the responsibility of this component.
     */
    public function getFormFields(Order $order): FieldList
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::getFormFields($order);
        }

        // Generate the standard set of fields and allow it to be customised
        $fields = FieldList::create(
            [
                $stripeField = StripeField::create('stripe', _t(static::class . '.CreditCard', 'Credit or debit card')),
                $tokenField = HiddenField::create('token', '', ''),
            ]
        );
        // load existing card selection field
        $existingCardField = $this->getExistingCardsField();
        if ($existingCardField !== null) {
            $fields->unshift($existingCardField);
            $stripeField->setTitle(_t(static::class . '.NewCreditCard', 'New credit or debit card'));
        }

        $this->extend('updateFormFields', $fields);

        // Generate a basic config and allow it to be customised
        $configName = $this->isPaymentIntent ? 'Stripe_PaymentIntents' : 'Stripe';
        $stripeConfig = Config::inst()->get(GatewayInfo::class, $configName);
        $jsConfig = [
            'formID'        => 'PaymentForm_PaymentForm',
            'stripeField'   => 'PaymentForm_PaymentForm_' . $stripeField->getName(),
            'tokenField'    => 'PaymentForm_PaymentForm_' . $tokenField->getName(),
            'submitButton'  => 'PaymentForm_PaymentForm_action_submitpayment',
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
        if ($this->isPaymentIntent) {
            Requirements::javascript('innoweb/silverstripe-silvershop-stripe:javascript/checkout_paymentintents.js');
        }

        if (!$this->isPaymentIntent) {
            Requirements::javascript('innoweb/silverstripe-silvershop-stripe:javascript/checkout.js');
        }

        return $fields;
    }

    protected function hasExistingCards(?Member $member = null): bool
    {
        if (!$this->isPaymentIntent) {
            return false;
        }

        if (Config::inst()->get(self::class, 'enable_saved_cards') === false) {
            return false;
        }

        if (!$member instanceof Member) {
            $member = Security::getCurrentUser();
        }

        return $member && $member->CreditCards()->exists();
    }

    /**
     * Allow choosing from an existing credit cards
     */
    public function getExistingCardsField(): DropdownField|OptionsetField|null
    {
        $member = Security::getCurrentUser();
        if ($this->hasExistingCards($member)) {
            $cardOptions = [];
            $cards = $member->CreditCards()->sort('Created', 'DESC');
            $limit = Config::inst()->get(self::class, 'radio_button_limit');
            $fieldtype = $cards->count() > $limit ? DropdownField::class : OptionsetField::class;
            $optionText = '<span class="cc"><span class="cc-brand">%s</span><span class="cc-number">****%s</span><span class="cc-expiry">%s</span></span>';
            foreach ($cards as $card) {
                if ($fieldtype == OptionsetField::class) {
                    if ($data = $card->getCardDetails()) {
                        $cardOptions[$card->CardReference] = DBField::create_field(
                            'HTMLFragment',
                            sprintf($optionText, $data->getField('Brand'), $data->getField('LastFourDigits'), $data->getField('ExpiryMonth') . '/' . $data->getField('ExpiryYear'))
                        );
                    } else {
                        $cardOptions[$card->CardReference] = _t('OnsitePaymentCheckoutComponent.CardDataCouldNotBeLoaded', 'Card data could not be loaded');
                    }
                } else {
                    $cardOptions[$card->CardReference] = $card->getTitle();
                }
            }

            $cardOptions['newcard'] = _t('OnsitePaymentCheckoutComponent.UseNewCard', 'Use a new card');
            $label = _t(
                "OnsitePaymentCheckoutComponent.ChooseACreditCard",
                "Choose a credit card"
            );
            $defaultCard = $member->DefaultCreditCard();
            $field = $fieldtype::create(
                "SavedCreditCardID",
                $label,
                $cardOptions,
                $defaultCard ? $defaultCard->CardReference : 'newcard'
            )->addExtraClass('existingCreditCards')
            ->setValue($defaultCard ? $defaultCard->CardReference : 'newcard');

            $this->extend('updateExistingCardsField', $field);

            return $field;
        }

        return null;
    }

    /**
     * Get the data fields that are required for the component
     */
    public function getRequiredFields(Order $order): array
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::getRequiredFields($order);
        }

        return $this->hasExistingCards() ? ['SavedCreditCardID'] : [];
    }

    /**
     * Is this data valid for saving into an order?
     * This function should never rely on form.
     *
     * @throws ValidationException
     */
    public function validateData(Order $order, array $data): bool
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::validateData($order, $data);
        }

        // If existing card selected, check that it exists in $member->CreditCards
        $existingID = empty($data['SavedCreditCardID']) ? 0 : (int)$data['SavedCreditCardID'];
        if ($existingID !== 0 && (!Security::getCurrentUser() || !Security::getCurrentUser()->CreditCards()->byID($existingID))) {
            $result = ValidationResult::create();
            $result->error("Invalid card supplied", 'SavedCreditCardID');
            throw ValidationException::create($result);
        }

        // NOTE: Stripe will validate clientside and if for some reason that falls through
        // it will fail on payment and give an error then. It would be a lot of work to get
        // the token to be namespaced so it could be passed here and there would be no point.
        return true;
    }

    /**
     * Get required data out of the model.
     *
     * @param Order $order order to get data from.
     *
     * @return array        get data from model(s)
     */
    public function getData(Order $order): array
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::getData($order);
        }

        return [];
    }

    /**
     * Set the model data for this component.
     *
     * This function should never rely on form.
     *
     * @param array $data  data to be saved into order object
     * @return Order the updated order
     */
    public function setData(Order $order, array $data): Order
    {
        $this->getGateway($order);
        if (!$this->isStripe) {
            return parent::setData($order, $data);
        }

        return $order;
    }
}
