<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use craft\commerce\elements\Order;
use netlab\commercetwo\CommerceTwo;
use yii\log\Logger;

class RefundRequest extends BaseRequest
{
    private $order;

    public function getData()
    {
        $this->order = Order::findOne(['id' => $this->getOrderId()]);
        if( !$this->order ) {
            throw new \Exception("Coudln't find the order");
        }
        $settings = CommerceTwo::getInstance()->getSettings();
        $data['twoOrderId'] = $this->order->twoOrderId;
        $data['language'] = $settings->language;
        $data['password'] = $this->getPassword();
        $data['amount'] = $this->order->getTotal();
        $data['currency'] = $this->order->getPaymentCurrency();
        $data['line_items'] = $this->getLineItems($this->order);
        $data['initiate_payment_to_buyer'] = true;
        return $data;
    }

    public function sendData($data)
    {
        try {
            $url = $this->getEndpoint()."/order/".$data['twoOrderId']."/refund?lang=".$data['language'];
            $httpResponse = $this->httpClient->request('POST', $url, [
                'Content-Type' => 'application/json',
                'X-API-KEY' => $data['password']
            ], json_encode([
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'initiate_payment_to_buyer' => $data['initiate_payment_to_buyer'],
                'line_items' => $data['line_items']
            ]));
        } catch (\Exception $e) {
            CommerceTwo::error("Error while sending refund order data to Two! Error: {$e->getMessage()}");
            throw $e;
        }
        return $this->response = new RefundResponse($this, json_decode($httpResponse->getBody()->getContents()), $httpResponse->getStatusCode(), $this->order);
    }
}