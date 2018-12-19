# SilverShop Stripe Support Module

Stripe uses a little different payment flow than other processors in that you have to do some clientside javascript work to set it up and you get a token back instead of credit card processing fields.

This module uses Omnipay's Stripe adapter but overrides SilverShop's default checkout component to inject the right JavaScript.

Based on [Mark Guinn's SS3 version](https://github.com/markguinn/silvershop-stripe), extended with saving of the token to the Member object for later use.

## Requirements

* [SilverStripe CMS](https://github.com/silverstripe/silverstripe-cms) 4.*
* [SilverShop Core](https://github.com/silvershop/silvershop-core/) master
* [Track members](https://github.com/gordonbanderson/silverstripe-track-member) 1.*
* [Omnipay Stripe](https://github.com/thephpleague/omnipay-stripe) 3.*
* [Guzzle 6 adapter](https://github.com/php-http/guzzle6-adapter) 1.*

uses [Stripe.js v3](https://stripe.com/docs/stripe-js) 

## Installation

```
composer require innoweb/silvershop-stripe
```

Then create a file at `app/_config/payment.yml` that looks something like the following:

```
---
Name: payment
---
Payment:
  allowed_gateways:
    - 'Stripe'

GatewayInfo:
  Stripe:
    parameters:
      apiKey: SECRET-KEY-FOR-YOUR-TEST-ACCOUNT
      publishableKey: PUBLISHABLE-KEY-FOR-TEST-ACCOUNT

---
Only:
  environment: 'live'
---
GatewayInfo:
  Stripe:
    parameters:
      apiKey: SECRET-KEY-FOR-YOUR-LIVE-ACCOUNT
      publishableKey: PUBLISHABLE-KEY-FOR-LIVE-ACCOUNT
```

## License

BSD 3-Clause License, see [License](license.md)
