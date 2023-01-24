<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use Craft;
use netlab\commercetwo\services\TwoHelper;
use netlab\commercetwo\two\omnipay\src\Gateway;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;

class CreateOrderResponse extends AbstractResponse implements RedirectResponseInterface
{
    private $statusCode;
    private $cart;

    public function __construct(RequestInterface $request, $data, $statusCode, $cart)
    {
        parent::__construct($request, $data);
        $this->statusCode = $statusCode;
    }

    public function isSuccessful()
    {
        return $this->statusCode === 201 && 'APPROVED' === $this->data->status;
    }

    public function isRedirect()
    {
        return $this->isSuccessful() && 'UNVERIFIED' === (string) $this->data->state;
    }

    public function isVerified() {
        return $this->isSuccessful() && 'VERIFIED' === (string) $this->data->state;
    }

    public function getRedirectUrl()
    {
        return $this->data->payment_url;
    }

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectData()
    {
        return null;
    }

    public function getTransactionReference()
    {
        return 'Two Inc.';
    }
}