# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

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
