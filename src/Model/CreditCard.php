<?php

namespace Innoweb\SilvershopStripe\Model;

use Innoweb\SilvershopStripe\Omnipay\Message\FetchCardRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Http\Client as OmnipayClient;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Security\Member;
use SilverStripe\View\ArrayData;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class CreditCard extends DataObject
{
    private static $table_name = 'CreditCard';

    private static $db = [
        'CardReference' => 'Varchar(50)',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    protected $card_details = null;

    public function getCardDetails()
    {
        if (!$this->card_details
            && $this->CardReference
            && $this->Member()
            && $this->Member()->StripeCustomerReference
        ) {
            try {
                // load data from API
                $data = [];

                $gatewayName = 'Stripe';
                $gatewayFactory = Injector::inst()->get(\Omnipay\Common\GatewayFactory::class);
                $gateway = $gatewayFactory->create($gatewayName);
                $parameters = GatewayInfo::getParameters($gatewayName);
                if (is_array($parameters)) {
                    $gateway->initialize($parameters);
                }

                $obj = new FetchCardRequest(new OmnipayClient(), SymfonyRequest::createFromGlobals());
                $fetchCardRequest = $obj->initialize(array_replace($gateway->getParameters(), $parameters));
                $fetchCardRequest->setCustomerReference($this->Member()->StripeCustomerReference);
                $fetchCardRequest->setCardReference($this->CardReference);

                $response = $fetchCardRequest->send();
                if ($response->isSuccessful()) {
                    $responseData = $response->getData();
                    $data = [
                        'Brand' => $responseData['brand'] ?? null,
                        'LastFourDigits' => $responseData['last4'] ?? null,
                        'ExpiryMonth' => $responseData['exp_month'] ?? null,
                        'ExpiryYear' => $responseData['exp_year'] ?? null,
                    ];
                    $this->card_details = ArrayData::create($data);
                }
            } catch (InvalidRequestException) {
            }
        }
        return $this->card_details;
    }

    public function getTitle() {
        if ($data = $this->getCardDetails()) {
            return $data->Brand . ' ****' . $data->LastFourDigits . ' ' . $data->ExpiryMonth . '/' . $data->ExpiryYear;
        }
        return null;
    }

    public function onAfterBuild()
    {
        parent::onAfterBuild();

        // check if fields exist
        $count = DB::query('SHOW COLUMNS FROM "CreditCard" LIKE \'LastFourDigits\'')->numRecords();
        if ($count > 0) {
            DB::query('ALTER TABLE "CreditCard" DROP COLUMN LastFourDigits, DROP COLUMN Brand, DROP COLUMN ExpMonth, DROP COLUMN ExpYear');
        }
    }
}
