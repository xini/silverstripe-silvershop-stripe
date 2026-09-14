# SilverShop Stripe Support Module

Stripe uses a little different payment flow than other processors in that you have to do some clientside javascript work to set it up and you get a token back instead of credit card processing fields.

This module uses Omnipay's Stripe adapter but overrides SilverShop's default checkout component to inject the right JavaScript.

Based on [Mark Guinn's SS3 version](https://github.com/markguinn/silvershop-stripe), extended with saving of the token to the Member object for later use.

## Requirements

* [SilverStripe CMS](https://github.com/silverstripe/silverstripe-cms) 6
* [SilverShop Core](https://github.com/silvershop/silvershop-core/) 6
* [Omnipay Stripe](https://github.com/thephpleague/omnipay-stripe) 3

uses [Stripe.js v3](https://stripe.com/docs/stripe-js) 

## Installation

```
composer require innoweb/silverstripe-silvershop-stripe
```

## Configuration

### Payment Intents

Create a file at `app/_config/payment.yml` that looks something like the following:

```yaml
---
Name: payment
---
SilverStripe\Omnipay\Model\Payment:
  allowed_gateways:
    - 'Stripe_PaymentIntents'

SilverStripe\Omnipay\GatewayInfo:
  Stripe_PaymentIntents:
    failureUrl: '/checkout/summary'
    parameters:
      apiKey: '`STRIPE_SECRET_KEY`'
      publishableKey: '`STRIPE_PUBLISHABLE_KEY`'
```

Then set the API keys in your `.env` file:

```.dotenv
STRIPE_SECRET_KEY=sk_env_SECRET-KEY-FOR-YOUR-ACCOUNT
STRIPE_PUBLISHABLE_KEY=pk_env_PUBLISHABLE-KEY-FOR-ACCOUNT
```

If needed, the customer will be redirected to Stripe or his bank to verify the transaction via SCA or 3D-Secure. 

A custom failure URL can be specified here for when a payment fails (for example, the card was declined).

#### Saving credit cards

The `Stripe_PaymentIntents` gateway creates Stripe customers and cards when a payment is processed and stores their tokens in the Silverstripe database. 

To disable the storage of card tokens and the use of previously stored cards in the checkout process, add the following to your config:

```yaml
---
Name: app-stripe-config
After: silvershop-stripe-config
---
Innoweb\SilvershopStripe\Checkout\Components\StripeOnsitePayment:
  enable_saved_cards: false
```

This will disable storing creadit card tokens in the database and hide the field to select previsouly stored cards in the payment form. 

Stripe still records the card tokens on their platform in order to be able to process refunds etc. But there is no reference to the cards in the Silverstripe database. 

#### Selecting a previously stored card

To select an existing card, the cards are shown as radio buttons in the payment form.

When there are more than 3 cards stored for a user, the field is displayed as a dropdown.

You can change that threshold by adding the following to your config:

```
Innoweb\SilvershopStripe\Checkout\Components\StripeOnsitePayment:
  radio_button_limit: 100
```

#### Styling the card selection radio buttons

The radio button labels include a rudimentary HTML structure to allow styling. You can add and amend the following styles to your CSS:

```
.field.optionset.existingCreditCards label .cc {
    display: inline-flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    width: 100%;
    max-width: 20rem;
}
.field.optionset.existingCreditCards label .cc-brand {
    margin-right: auto;
}
```

### Stripe Charge (deprecated)

Create a file at `app/_config/payment.yml` that looks something like the following:

```
---
Name: payment
---
SilverStripe\Omnipay\Model\Payment:
  allowed_gateways:
    - 'Stripe'

SilverStripe\Omnipay\GatewayInfo:
  Stripe:
    parameters:
      apiKey: sk_test_SECRET-KEY-FOR-YOUR-TEST-ACCOUNT
      publishableKey: pk_test_PUBLISHABLE-KEY-FOR-TEST-ACCOUNT

---
Only:
  environment: 'live'
---
SilverStripe\Omnipay\GatewayInfo:
  Stripe:
    parameters:
      apiKey: sk_live_SECRET-KEY-FOR-YOUR-LIVE-ACCOUNT
      publishableKey: pk_live_PUBLISHABLE-KEY-FOR-LIVE-ACCOUNT
```

## License

BSD 3-Clause License, see [License](license.md)
