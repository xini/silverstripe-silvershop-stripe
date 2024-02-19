# SilverShop Stripe Support Module

Stripe uses a little different payment flow than other processors in that you have to do some clientside javascript work to set it up and you get a token back instead of credit card processing fields.

This module uses Omnipay's Stripe adapter but overrides SilverShop's default checkout component to inject the right JavaScript.

Based on [Mark Guinn's SS3 version](https://github.com/markguinn/silvershop-stripe), extended with saving of the token to the Member object for later use.

## Requirements

* [SilverStripe CMS](https://github.com/silverstripe/silverstripe-cms) 4.*
* [SilverShop Core](https://github.com/silvershop/silvershop-core/) 3.*
* [Track members](https://github.com/gordonbanderson/silverstripe-track-member) 1.*
* [Omnipay Stripe](https://github.com/thephpleague/omnipay-stripe) 3.*

uses [Stripe.js v3](https://stripe.com/docs/stripe-js) 

## Installation

```
composer require innoweb/silverstripe-silvershop-stripe
```

## Configuration
### Payment Intents
Create a file at `app/_config/payment.yml` that looks something like the following:

```
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
      apiKey: sk_test_SECRET-KEY-FOR-YOUR-TEST-ACCOUNT
      publishableKey: pk_test_PUBLISHABLE-KEY-FOR-TEST-ACCOUNT

---
Only:
  environment: 'live'
---
SilverStripe\Omnipay\GatewayInfo:
  Stripe_PaymentIntents:
	failureUrl: '/checkout/summary'
    parameters:
      apiKey: sk_live_SECRET-KEY-FOR-YOUR-LIVE-ACCOUNT
      publishableKey: pk_live_PUBLISHABLE-KEY-FOR-LIVE-ACCOUNT
```

If needed, the customer will be redirected to Stripe or his bank to verify the transaction via SCA or 3D-Secure. 

A custom failure URL can be specified here for when a payment fails (for example, the card was declined).

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


## Saving cards

The module creates Stripe customers and cards when a payment is processed. To disable the use of previously stored cards in the checkout process, add the following to your config:

```
---
Name: app-stripe-config
After: silvershop-stripe-config
---
Innoweb\SilvershopStripe\Checkout\Components\StripeOnsitePayment:
  enable_saved_cards: false
```

This will hide the field to select previsouly stored cards in th epayment form. The card tokens will still be stored in the background in order to be able to process refunds and future manual payments.

## License

BSD 3-Clause License, see [License](license.md)
