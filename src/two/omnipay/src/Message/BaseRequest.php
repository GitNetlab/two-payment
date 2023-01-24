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
                    'discount' => (string)($lineItem->getDiscount() * -1),
                    'gross_amount' => (string)$lineItem->getTotal(),
                    'net_amount' => (string)($lineItem->getPurchasable()->getPrice() * $lineItem->qty + $lineItem->getDiscount()),
                    'quantity_unit' => 'pcs',
                    'tax_amount' => (string)($taxCategory ? $lineItem->getTax() : 0),
                    'tax_class_name' => $taxCategory ? $taxCategory->name :  'NO TAX',
                    'tax_rate' => (string)(count($taxRates) ? number_format($taxRates[0]->rate, 3) : 0),
                    'type' => 'PHYSICAL',
                    'unit_price' => (string)$lineItem->getPurchasable()->getPrice()
                ];
            }
            if( $cart->getTotalShippingCost() ) {
                $lineItems[] = [
                    'name' => 'Shipping',
                    'quantity' => 1,
                    'description' => 'Shipping fee',
                    'discount' => '0',
                    'gross_amount' => (string)$cart->getTotalShippingCost(),
                    'net_amount' => (string)$cart->getTotalShippingCost(),
                    'quantity_unit' => 'pcs',
                    'tax_amount' => '0',
                    'tax_class_name' => 'NO TAX',
                    'tax_rate' => '0',
                    'type' => 'SHIPPING_FEE',
                    'unit_price' => (string)$cart->getTotalShippingCost()
                ];
            }
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return $lineItems;
    }

    protected function getBillingAddress() : array {
        return [
            'city' => $this->cart->billingAddress->city,
            'country' => $this->cart->billingAddress->getCountryIso(),
            'organization_name' => json_decode($this->cart->twoCompany)->company_name,
            'postal_code' => $this->cart->billingAddress->zipCode,
            'street_address' => $this->cart->billingAddress->address1,
            'region' => $this->cart->billingAddress->getStateText()
        ];
    }

    protected function getShippingAddress() : array {
        return [
            'city' => $this->cart->shippingAddress->city,
            'country' => $this->cart->shippingAddress->getCountryIso(),
            'organization_name' => json_decode($this->cart->twoCompany)->company_name,
            'postal_code' => $this->cart->shippingAddress->zipCode,
            'street_address' => $this->cart->shippingAddress->address1,
            'region' => $this->cart->shippingAddress->getStateText()
        ];
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