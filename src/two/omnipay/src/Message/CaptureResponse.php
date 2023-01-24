<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;

class CaptureResponse extends AbstractResponse implements RedirectResponseInterface
{
    private $statusCode;

    public function __construct(RequestInterface $request, $data, $statusCode, Order $order)
    {
        parent::__construct($request, $data);
        $this->statusCode = $statusCode;

        if( $this->isSuccessful() ) {
            $order->setFieldValues([
                'twoOrderStatus' => $data->status,
                'twoOrderState' => $data->state,
                'twoInvoiceUrl' => $data->invoice_url
            ]);
            $saved = \Craft::$app->elements->saveElement($order);
            if(!$saved) {
                throw new \Exception("Unable to save cart object: ". json_encode($order->getErrors()));
            }
        }
    }

    public function isSuccessful()
    {
        return $this->statusCode === 200;
    }

    public function getCode()
    {
        return $this->statusCode;
    }

    public function getTransactionReference()
    {
        return 'Two Inc.';
    }

}