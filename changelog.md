# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [3.0.0]

* upgrade to Silverstripe 6 and PHP 8.5

## [2.1.1]

* fix js form submission to include generated token (messing with form validation though, meaning that the payment form needs to be on its own checkout step)

## [2.1.0]

* remove defunct saving of credit cards for old Stripe Charge ('Stripe') gateway
* fix saving of credit cards for new Stripe PaymentIntents ('Stripe_PaymentIntents') gateway
* add HTML structure to saved card radio buttons to make them stylable
* make `StripeOnsitePayment::radio_button_limit` configurable
* run rector and linter

## [2.0.0]

* Upgrade to Silverstripe 5 and PHP 8.3
* Removal of suilven/silverstripe-track-member dependency
* Add types (thanks to @AntonyThorpe)

## [1.0.0]

* Payment Intents integration, thanks @BettinaMaria98 and @wernerkrauss

## [1.0.0-beta6]

* catch omnipay exeption when deleting a user that is not linked to a stripe user and has no credit card data

## [1.0.0-beta5]

* remove credit card details from database, load via API instead

## [1.0.0-beta4]

* remove obsolete guzzle dependency

## [1.0.0-beta3]

* add gateway info config to make sure Stripe is handled as onsite gateway
* switch to stable silvershop release

## [1.0.0-beta2]

* fix readme
* Updated StripeOrderProcessor to help match payments made in SilverShop with those in Stripe

## [1.0.0-beta1]

* initial release for testing purposes
