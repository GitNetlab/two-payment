<?php

namespace netlab\commercetwo\two\omnipay\src;

use craft\commerce\Plugin as Commerce;
use netlab\commercetwo\services\TwoHelper;
use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'Two Inc.';
    }

    public function getDefaultParameters()
    {
        return array(
            'merchantId' => '',
            'password' => '',
            'testMode' => false
        );
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

    public function purchase(array $parameters = array())
    {
        if( \Craft::$app->request->isCpRequest ) {
            throw new \Exception("Authorizing from admin is unsupported");
        }

        \Craft::$app->session->set('autoCapture', 1);

        $createOrderResponse = $this->createRequest('netlab\commercetwo\two\omnipay\src\Message\CreateOrderRequest', $parameters)->send();

        if( $createOrderResponse->isSuccessful() ) {
            $cart =  Commerce::getInstance()->getCarts()->getCart();
            $helper = TwoHelper::getInstance();
            $data = $createOrderResponse->getData();
            $helper->saveOrderDataToCart($cart, $data);

            if( $createOrderResponse->isVerified() ) {
                return $this->createRequest('netlab\commercetwo\two\omnipay\src\Message\AuthorizeRequest', $parameters);
            } else if( $createOrderResponse->isRedirect() ) {
                return $createOrderResponse->redirect();
            } else {
                throw new \Exception("Unknown order state");
            }
        }
        throw new \Exception("Couldn't create order.");
    }

    public function capture(array $parameters = array())
    {
        return $this->createRequest('netlab\commercetwo\two\omnipay\src\Message\CaptureRequest', $parameters);
    }

    public function refund(array $parameters = array())
    {
        return $this->createRequest('netlab\commercetwo\two\omnipay\src\Message\RefundRequest', $parameters);
    }

    public function authorize(array $parameters = array())
    {
        if( \Craft::$app->request->isCpRequest ) {
            throw new \Exception("Authorizing from admin is unsupported");
        }

        $createOrderResponse = $this->createRequest('netlab\commercetwo\two\omnipay\src\Message\CreateOrderRequest', $parameters)->send();

        if( $createOrderResponse->isSuccessful() ) {
            $cart =  Commerce::getInstance()->getCarts()->getCart();
            $helper = TwoHelper::getInstance();
            $data = $createOrderResponse->getData();
            $helper->saveOrderDataToCart($cart, $data);

            if( $createOrderResponse->isVerified() ) {
                return $this->createRequest('netlab\commercetwo\two\omnipay\src\Message\AuthorizeRequest', $parameters);
            } else if( $createOrderResponse->isRedirect() ) {
                return $createOrderResponse->redirect();
            } else {
                throw new \Exception("Unknown order state");
            }
        }
        throw new \Exception("Couldn't create order.");
    }
}
