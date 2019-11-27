# SilverShop Stripe Support Module

Stripe uses a little different payment flow than other processors in that you have to do some clientside javascript work to set it up and you get a token back instead of credit card processing fields.

This module uses Omnipay's Stripe adapter but overrides SilverShop's default checkout component to inject the right JavaScript.

Based on [Mark Guinn's SS3 version](https://github.com/markguinn/silvershop-stripe), extended with saving of the token to the Member object for later use.

**This module is still work in progress. PRs welcome!**

## Requirements

* [SilverStripe CMS](https://github.com/silverstripe/silverstripe-cms) 4.*
* [SilverShop Core](https://github.com/silvershop/silvershop-core/) 3.*
* [Track members](https://github.com/gordonbanderson/silverstripe-track-member) 1.*
* [Omnipay Stripe](https://github.com/thephpleague/omnipay-stripe) 3.*
* [Guzzle 6 adapter](https://github.com/php-http/guzzle6-adapter) 1.*

uses [Stripe.js v3](https://stripe.com/docs/stripe-js) 

## Installation

```
composer require innoweb/silverstripe-silvershop-stripe
```

## Configuration

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
      apiKey: SECRET-KEY-FOR-YOUR-TEST-ACCOUNT
      publishableKey: PUBLISHABLE-KEY-FOR-TEST-ACCOUNT

---
Only:
  environment: 'live'
---
SilverStripe\Omnipay\GatewayInfo:
  Stripe:
    parameters:
      apiKey: SECRET-KEY-FOR-YOUR-LIVE-ACCOUNT
      publishableKey: PUBLISHABLE-KEY-FOR-LIVE-ACCOUNT
```

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
