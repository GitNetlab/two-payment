<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use netlab\commercetwo\CommerceTwo;
use netlab\commercetwo\services\TwoHelper;
use Omnipay\Common\Http\ClientInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use yii\log\Logger;


class CreateOrderRequest extends BaseRequest
{

    public function getData()
    {
        $data = [];
        try {
            $data['buyer'] = TwoHelper::getInstance()->getBuyerObject($this->cart);
            $data['currency'] = $this->cart->getPaymentCurrency();
            $data['gross_amount'] = (string)$this->cart->getTotal();
            $data['net_amount'] = (string)($this->cart->getTotal() - $this->cart->totalTax);
            $data['tax_amount'] = (string)$this->cart->getTotalTax();
            $data['line_items'] = $this->getLineItems($this->cart);
            $data['invoice_type'] = 'DIRECT_INVOICE';
            $data['merchant_id'] = $this->getMerchantId();
            $data['merchant_order_id'] = (string)$this->cart->getId();
            $data['merchant_urls'] = [
                'merchant_cancel_order_url' => $this->cart->cancelUrl,
                'merchant_confirmation_url' => Craft::$app->sites->currentSite->getBaseUrl() . 'commerce-two/return',
                'merchant_edit_order_url' => $this->cart->cancelUrl,
                'merchant_invoice_url' => $this->cart->returnUrl,
                'merchant_order_verification_failed_url' => Craft::$app->sites->currentSite->getBaseUrl() . 'commerce-two/return',
            ];
            $data['billing_address'] = $this->getBillingAddress();
            $data['shipping_address'] = $this->getShippingAddress();
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return $data;
    }

    public function sendData($data)
    {
        try {
            $httpResponse = $this->httpClient->request('POST', $this->getEndpoint(). "/order", [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->getPassword()
            ], json_encode($data));
            return $this->response = new CreateOrderResponse($this, json_decode($httpResponse->getBody()->getContents()), $httpResponse->getStatusCode(), $this->cart);
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }
}