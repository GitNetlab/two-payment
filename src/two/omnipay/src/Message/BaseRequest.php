<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use netlab\commercetwo\CommerceTwo;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Common\Message\AbstractRequest;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use yii\log\Logger;

class BaseRequest extends AbstractRequest
{
    protected $cart;
    protected $liveEndpoint = 'https://api.two.inc/v1';
    protected $testEndpoint = 'https://sandbox.api.two.inc/v1';

    public function __construct(ClientInterface $httpClient, HttpRequest $httpRequest)
    {
        parent::__construct($httpClient, $httpRequest);
        $this->cart = Commerce::getInstance()->getCarts()->getCart();
    }

    public function getMerchantId()
    {
        return $this->getParameter('merchantId');
    }

    public function setMerchantId($value)
    {
        return $this->setParameter('merchantId', $value);
    }

    public function getPassword()
    {
        return $this->getParameter('password');
    }

    public function setPassword($value)
    {
        return $this->setParameter('password', $value);
    }

    public function getOrderId() {
        return $this->getParameter('twoOrderId');
    }

    public function setOrderId($value) {
        return $this->setParameter('twoOrderId', $value);
    }

    protected function getLineItems(Order $cart) {
        $lineItems = [];
        try {
            foreach ($cart->getLineItems() as $lineItem) {
                $taxCategory = false;
                $taxRates = [];
                if( $lineItem->getIsTaxable() ) {
                    $taxCategory = $lineItem->getTaxCategory();
                    $taxRates = $taxCategory->getTaxRates();
                }
                $lineItems[] = [
                    'name' => $lineItem->getDescription(),
                    'quantity' => (float)$lineItem->qty,
                    'description' => $lineItem->getDescription() . ' - '. $lineItem->getSku(),
                    'gross_amount' => (string)$lineItem->getTotal(),
                    'net_amount' => (string)($lineItem->getTotal() - $lineItem->getTax()),
                    'discount' => (string)($lineItem->getPrice() * $lineItem->qty - ($lineItem->getTotal() - $lineItem->getTax())),
                    'quantity_unit' => 'pcs',
                    'tax_amount' => (string)($taxCategory ? $lineItem->getTax() : 0),
                    'tax_class_name' => $taxCategory ? $taxCategory->name :  'NO TAX',
                    'tax_rate' => (string)(count($taxRates) ? number_format($taxRates[0]->rate, 3) : 0),
                    'type' => 'PHYSICAL',
                    'unit_price' => (string)$lineItem->getSalePrice(),
                ];
            }
            if( $cart->getTotalShippingCost() ) {
                $lineItems[] = [
                    'name' => 'Shipping',
                    'quantity' => 1,
                    'description' => 'Shipping fee',
                    'gross_amount' => (string)$cart->getTotalShippingCost(),
                    'net_amount' => (string)$cart->getTotalShippingCost(),
                    'discount' => '0',
                    'quantity_unit' => 'pcs',
                    'tax_amount' => '0',
                    'tax_class_name' => 'NO TAX',
                    'tax_rate' => '0',
                    'type' => 'SHIPPING_FEE',
                    'unit_price' => (string)$cart->getTotalShippingCost()
                ];
            }
        } catch (\Exception $e) {
            CommerceTwo::error("Error while creating line item data in BaseRequest! Error: {$e->getMessage()}");
            throw $e;
        }
        return $lineItems;
    }

    protected function getBillingAddress() : array {
        $address = [
            'city' => $this->cart->billingAddress->getLocality(),
            'country' => $this->cart->billingAddress->getCountryCode(),
            'organization_name' => json_decode($this->cart->twoCompany)->company_name,
            'postal_code' => $this->cart->billingAddress->getPostalCode(),
            'street_address' => $this->cart->billingAddress->getAddressLine1(),
            'region' => $this->cart->billingAddress->getLocality()
        ];

        if( !empty($this->cart->billingAddress->getAdministrativeArea()) && !empty($this->cart->billingAddress->getCountryCode()) ) {
            $state = \Craft::$app->getAddresses()->subdivisionRepository->get($this->cart->billingAddress->getAdministrativeArea(), [$this->cart->billingAddress->getCountryCode()]);
            $address['region'] = $state->getName();
        }
        return $address;
    }

    protected function getShippingAddress() : array {
        $address = [
            'city' => $this->cart->shippingAddress->getLocality(),
            'country' => $this->cart->shippingAddress->getCountryCode(),
            'organization_name' => json_decode($this->cart->twoCompany)->company_name,
            'postal_code' => $this->cart->shippingAddress->getPostalCode(),
            'street_address' => $this->cart->shippingAddress->getAddressLine1(),
            'region' => $this->cart->shippingAddress->getLocality()
        ];

        if( !empty($this->cart->shippingAddress->getAdministrativeArea()) && !empty($this->cart->shippingAddress->getCountryCode()) ) {
            $state = \Craft::$app->getAddresses()->subdivisionRepository->get($this->cart->shippingAddress->getAdministrativeArea(), [$this->cart->shippingAddress->getCountryCode()]);
            $address['region'] = $state->getName();
        }
        return $address;
    }

    public function getEndpoint() {
        return $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint;
    }

    public function sendData($data)
    {
        // TODO: Implement sendData() method.
    }

    public function getData()
    {
        // TODO: Implement getData() method.
    }
}