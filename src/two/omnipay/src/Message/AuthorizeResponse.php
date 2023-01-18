<?php

namespace netlab\commercetwo\two\omnipay\src\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;

class AuthorizeResponse extends AbstractResponse implements RedirectResponseInterface
{

    private $statusCode;

    public function __construct(RequestInterface $request, $data, $statusCode, $cart)
    {
        parent::__construct($request, $data);
        $this->statusCode = $statusCode;

        if( $this->isSuccessful() ) {
            $cart->setAttributes([
                'twoOrderStatus' => $data->status,
                'twoOrderState' => $data->state,
                'twoInvoiceUrl' => $data->invoice_url
            ]);

            $cart_saved = Craft::$app->elements->saveElement($this->cart);
            if(!$cart_saved) {
                throw new \Exception("Unable to save cart object: ". json_encode($this->cart->getErrors()));
            }
        }
    }

    public function isSuccessful()
    {
        return in_array($this->statusCode, [200, 202]);
    }
}