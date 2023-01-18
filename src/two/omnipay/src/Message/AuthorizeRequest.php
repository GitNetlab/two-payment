<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use Craft;
use craft\commerce\controllers\CartController;
use craft\commerce\Plugin as Commerce;
use http\Exception\InvalidArgumentException;
use netlab\commercetwo\CommerceTwo;
use netlab\commercetwo\services\TwoHelper;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Common\Message\AbstractRequest;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use yii\base\Exception;
use yii\debug\models\search\Log;
use yii\log\Logger;

class AuthorizeRequest extends BaseRequest
{

    public function getData()
    {
        if(! $this->cart->twoOrderId ) {
            throw new \Exception("Order ID is not set on the order");
        }
        $data['id'] = $this->cart->twoOrderId;
        return $data;
    }

    public function sendData($data)
    {
        try {
            $url = $this->getEndpoint().'/order/'.$data['id'].'/confirm';
            $httpResponse = $this->httpClient->request('POST', $url, [
                'X-API-Key' => $this->getPassword()
            ]);
            return $this->response = new AuthorizeResponse($this, json_decode($httpResponse->getBody()->getContents()), $httpResponse->getStatusCode(), $this->cart);
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }
}
