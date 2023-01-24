<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use craft\commerce\elements\Order;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;

class RefundResponse extends AbstractResponse implements RedirectResponseInterface
{
    private $statusCode;

    public function __construct(RequestInterface $request, $data, $statusCode, Order $order)
    {
        parent::__construct($request, $data);
        $this->statusCode = $statusCode;

        if( $this->isSuccessful() ) {
            $order->setFieldValue('twoCreditNoteUrl', $data->credit_note_url);
            $saved = \Craft::$app->elements->saveElement($order);
        }

    }

    public function isSuccessful()
    {
        return $this->statusCode === 201;
    }

    public function getTransactionReference()
    {
        return 'Two Inc.';
    }

    public function getCode()
    {
        return $this->statusCode;
    }
}