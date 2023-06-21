<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use craft\commerce\elements\Order;
use netlab\commercetwo\CommerceTwo;
use Omnipay\Common\Exception\InvalidResponseException;
use yii\log\Logger;

class CaptureRequest extends BaseRequest
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
        return $data;
    }

    public function sendData($data)
    {
        try {
            $url = $this->getEndpoint()."/order/".$data['twoOrderId']."/fulfilled?lang=".$data['language'];
            $httpResponse = $this->httpClient->request('POST', $url, [
                'Content-Type' => 'application/json',
                'X-API-KEY' => $data['password']
            ]);
        } catch (\Exception $e) {
            CommerceTwo::error("Error while sending capture order data to Two! Error: {$e->getMessage()}");
            throw $e;
        }
        return $this->response = new CaptureResponse($this, json_decode($httpResponse->getBody()->getContents()), $httpResponse->getStatusCode(), $this->order);
    }
}
