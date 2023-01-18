<?php

namespace netlab\commercetwo\services;

use Craft;
use craft\base\Component;
use craft\commerce\Plugin;
use craft\commerce\Plugin as Commerce;
use GuzzleHttp\Client;
use netlab\commercetwo\CommerceTwo;
use netlab\commercetwo\two\omnipay\src\Gateway;
use phpDocumentor\Reflection\Types\Object_;
use yii\log\Logger;
use craft\commerce\elements\Order;

/**
 *
 */
class TwoHelper extends Component
{
    /**
     * @var
     */
    private static $defaultInstance;
    /**
     * @var string
     */
    private $liveEndpoint = 'https://api.two.inc/v1';
    /**
     * @var string
     */
    private $testEndpoint = 'https://sandbox.api.two.inc/v1';
    /**
     * @var string
     */
    private $endpoint;
    /**
     * @var mixed
     */
    private $password;
    /**
     * @var bool|\craft\base\Model|null
     */
    private $pluginSettings;
    /**
     * @var Client
     */
    private $client;

    /**
     * @return TwoHelper|null
     */
    public static function getInstance() : ?TwoHelper {
        if(!self::$defaultInstance) {
            self::$defaultInstance = new TwoHelper();
        }
        return self::$defaultInstance;
    }

    /**
     * @param $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->pluginSettings = CommerceTwo::getInstance()->getSettings();
        $this->client = new Client();
        $this->endpoint = $this->pluginSettings->environment !== 'live' ? $this->testEndpoint : $this->liveEndpoint;
        $this->password = $this->pluginSettings->environment !== 'live' ? $this->pluginSettings->testApiKey : $this->pluginSettings->liveApiKey;
    }

    // https://api-docs.two.inc/openapi/search-api/#operation/get-search-company-name
    /**
     * @param string $name
     * @param string $countryCode
     * @param int $limit
     * @param int $offset
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchCompany(string $name, string $countryCode = 'no', int $limit = 30, int $offset = 0) : ?array {
        try {
            $availableCountryCodes = ['no', 'gb', 'se'];
            if(!in_array($countryCode, $availableCountryCodes)) {
                throw new \Exception("Available country codes: ". implode(',', $availableCountryCodes));
            }

            $limit = $limit > 100 || $limit < 0 ? 30 : $limit;
            $offset = $offset < 0 ? 0 : $offset;

            $httpResponse = $this->client->request('GET', "https://$countryCode.search.two.inc/search?limit=$limit&offset=$offset&q=$name");
            if( $httpResponse->getStatusCode() === 200 ) {
                $responseBody = json_decode($httpResponse->getBody()->getContents());
                return $responseBody->data->items;
            } else {
                throw new \Exception("Request failed with status code: " . $httpResponse->getStatusCode());
            }
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    // https://api-docs.two.inc/openapi/checkout-api/#tag/Company
    /**
     * @param string $companyId
     * @param string $countryCode
     * @return object|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     */
    public function getCompanyAddress(string $companyId, string $countryCode) : ?object {
        try {
            $availableCountryCodes = ['no', 'gb', 'se'];
            if(!in_array($countryCode, $availableCountryCodes)) {
                throw new \Exception("Available country codes: ". implode(',', $availableCountryCodes));
            }

            $httpResponse = $this->client->request('GET', "$this->endpoint/$countryCode/company/$companyId/address", [
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            if( $httpResponse->getStatusCode() === 200 ) {
                return json_decode($httpResponse->getBody()->getContents())->address;
            } else {
                throw new \Exception("Request failed with status code: " . $httpResponse->getStatusCode());
            }
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    // Create order intent request
    // https://api-docs.two.inc/openapi/checkout-api/#operation/post-order_intent
    /**
     * @param Order $cart
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createOrderIntent(Order $cart, bool $saveCart = true, $buyer = null) {
        try {
            $data = $this->createOrderIntentBody($cart, $buyer);

            $httpResponse = $this->client->request('POST', "$this->endpoint/order_intent", [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($data)
            ]);

            $intent = json_decode($httpResponse->getBody()->getContents());

            if( !$saveCart ) {
                return $intent;
            }

            $cart->setAttributes(['twoIsEnabledForCheckout' => $intent->approved]);

            $cart_saved = Craft::$app->elements->saveElement($cart);
            if(!$cart_saved) {
                throw new \Exception("Unable to save cart object: ". json_encode($cart->getErrors()));
            }

            return $intent->approved;
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    // Create order request
    // https://api-docs.two.inc/openapi/checkout-api/#operation/post-order!in=header&path=X-API-Key&t=request
    /**
     * @param Order $cart
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createOrder(Order $cart) {
        try {
            $data = $this->createOrderBody($cart);

            $httpResponse = $this->client->request('POST', "$this->endpoint/order", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $this->password
                ],
                'body' => json_encode($data)
            ]);

            $createOrderResponse = json_decode($httpResponse->getBody()->getContents());

            $cart->setAttributes([
                'twoOrderId'        => $createOrderResponse->id,
                'twoOrderStatus'    => $createOrderResponse->status,
                'twoOrderState'     => $createOrderResponse->state,
                'twoInvoiceUrl'     => $this->getEndpoint() . '/invoice/' . $createOrderResponse->id . '/pdf'
            ]);

            $cart_saved = Craft::$app->elements->saveElement($this->cart);
            if(!$cart_saved) {
                throw new \Exception("Unable to save cart object: ". json_encode($this->cart->getErrors()));
            }

            return $createOrderResponse->status === 'APPROVED';
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    // Capture request
    // https://api-docs.two.inc/openapi/checkout-api/#operation/post-order-id-confirm
    /**
     * @param Order $cart
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function updateOrder(Order $cart) : bool {
        try {
            // There are 2 routes Full Capture and Partial Capture, we only support full for now - for partial we need more fields in the request body, check docs
            $resp = $this->client->request('POST', "$this->endpoint/order/$cart->twoOrderId/fulfilled?lang=".$this->pluginSettings->language, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => $this->password
                ]
            ]);

            if( $resp->getStatusCode() === 200 ) {
                $responseBody = json_decode($resp->getBody()->getContents());
                $cart->setAttributes([
                    'twoOrderStatus' => $responseBody->status,
                    'twoOrderState' => $responseBody->state,
                    'twoInvoiceUrl' => $responseBody->invoice_url
                ]);

                $cart_saved = Craft::$app->elements->saveElement($cart);
                if(!$cart_saved) {
                    throw new \Exception("Unable to save cart object: ". json_encode($cart->getErrors()));
                }
                
                // Set capture transaction
                $transaction = Plugin::getInstance()->transactions->createTransaction($cart);
                $transaction->status = \craft\commerce\records\Transaction::STATUS_SUCCESS;
                $transaction->type = \craft\commerce\records\Transaction::TYPE_CAPTURE;
                $transaction->response = $responseBody;
                $transaction->reference = 'Two Inc.';
                Plugin::getInstance()->transactions->saveTransaction($transaction);
                $cart->updateOrderPaidInformation();
                
                return true;
            } else {
                throw new \Exception("Wrong status code from Two! ". json_encode( $resp->getBody()->getContents() ));
            }
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return false;
    }

    // Authorize request
    // https://api-docs.two.inc/openapi/checkout-api/#operation/post-order-id-confirm
    /**
     * @param Order $cart
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     * @throws \craft\commerce\errors\TransactionException
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function confirmOrder(Order $cart) : bool {
        try {
            if(! $cart->twoOrderId ) {
                throw new \Exception("Order ID is not set on the order");
            }

            $resp = $this->client->request('POST', "$this->endpoint/order/$cart->twoOrderId/confirm", [
                'headers' => [
                    'X-API-KEY' => $this->password
                ]
            ]);

            if( in_array($resp->getStatusCode(), [200, 202]) ) {
                $responseBody = json_decode($resp->getBody()->getContents());
                $cart->setAttributes([
                    'twoOrderStatus' => $responseBody->status,
                    'twoOrderState' => $responseBody->state
                ]);

                $cart_saved = Craft::$app->elements->saveElement($cart);

                if(!$cart_saved) {
                    throw new \Exception("Unable to save cart object: ". json_encode($cart->getErrors()));
                }

                // Set authorize transaction
                $transaction = Plugin::getInstance()->transactions->createTransaction($cart);
                $transaction->status = \craft\commerce\records\Transaction::STATUS_SUCCESS;
                $transaction->type = \craft\commerce\records\Transaction::TYPE_AUTHORIZE;
                $transaction->response = $responseBody;
                $transaction->reference = 'Two Inc.';
                Plugin::getInstance()->transactions->saveTransaction($transaction);
                $cart->updateOrderPaidInformation();
                return true;
            } else {
                throw new \Exception("Wrong status code from Two! ". json_encode( $resp->getBody()->getContents() ));
            }
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return false;
    }

    // Get order
    // https://api-docs.two.inc/openapi/checkout-api/#operation/get-order
    /**
     * @param Order $cart
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getOrder(Order $cart) {
        try {
            if(! $cart->twoOrderId ) {
                throw new \Exception("Order ID is not set on the order");
            }

            $resp = $this->client->request('GET', "$this->endpoint/order/$cart->twoOrderId", [
                'headers' => [
                    'X-API-KEY' => $this->password
                ]
            ]);

            if( $resp->getStatusCode() === 200 ) {
                return json_decode($resp->getBody()->getContents());
            } else {
                throw new \Exception("Wrong status code from Two! ". json_encode( $resp->getBody()->getContents() ));
            }
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return false;
    }

    /**
     * @param Order $cart
     * @param $data
     * @return void
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function saveOrderDataToCart(Order $cart, $data) {
        $cart->setAttributes([
            'twoOrderId'        => $data->id,
            'twoOrderStatus'    => $data->status,
            'twoOrderState'     => $data->state
        ]);

        $cart_saved = Craft::$app->elements->saveElement($cart);
        if(!$cart_saved) {
            throw new \Exception("Unable to save cart object: ". json_encode($cart->getErrors()));
        }
    }

    public function getBuyerObject(Order $cart) : array {
        return [
            'company' => (array)json_decode($cart->twoCompany),
            'representative' => [
                'email' => $cart->getEmail(),
                'first_name' => $cart->billingAddress->firstName ?? 'John',
                'last_name' => $cart->billingAddress->lastName ?? 'Doe',
                'phone_number' => $cart->billingAddress->phone ?? '1234567'
            ]
        ];
    }

    /**
     * @param Order $cart
     * @return array
     * @throws \Exception
     */
    private function createOrderIntentBody(Order $cart, $buyer = null) : array {
        $data = [];
        try {
            $data['buyer'] = $buyer ?: $this->getBuyerObject($cart);
            $data['currency'] = $cart->getPaymentCurrency();
            $data['gross_amount'] = (string)$cart->getTotal();
            $data['line_items'] = $this->getLineItems($cart);
            $data['merchant_id'] = (string)$this->pluginSettings->merchantId;
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        } finally {
            return $data;
        }
    }

    /**
     * @param Order $cart
     * @return array
     * @throws \Exception
     */
    private function createOrderBody(Order $cart) : array {
        $data = [];
        try {
            $data['buyer'] = $this->getBuyerObject($cart);
            $data['currency'] = $cart->getPaymentCurrency();
            $data['gross_amount'] = (string)$cart->getTotal();
            $data['net_amount'] = (string)($cart->getTotal() - ($cart->totalTax + $cart->totalDiscount + $cart->totalShippingCost));
            $data['tax_amount'] = (string)$cart->getTotalTax();
            $data['line_items'] = $this->getLineItems($cart);
            $data['invoice_type'] = 'DIRECT_INVOICE';
            $data['merchant_id'] = $this->pluginSettings->merchantId;;
            $data['merchant_order_id'] = (string)$cart->getId();
            $data['merchant_urls'] = [
                'merchant_cancel_order_url' => $cart->cancelUrl,
                'merchant_confirmation_url' => Craft::$app->sites->currentSite->getBaseUrl() . 'commerce-two/return',
                'merchant_edit_order_url' => $cart->cancelUrl,
                'merchant_invoice_url' => $cart->returnUrl,
                'merchant_order_verification_failed_url' => Craft::$app->sites->currentSite->getBaseUrl() . 'commerce-two/return',
            ];
            $data['billing_address'] = $this->getBillingAddress($cart);
            $data['shipping_address'] = $this->getShippingAddress($cart);
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return $data;
    }

    /**
     * @param Order $cart
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    private function getLineItems(Order $cart) : array {
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
                    'gross_amount' => (string)$lineItem->getTotal(),
                    'net_amount' => (string)($lineItem->getPurchasable()->getPrice() * $lineItem->qty),
                    'quantity_unit' => 'pcs',
                    'tax_amount' => (string)($taxCategory ? $lineItem->getTax() : 0),
                    'tax_class_name' => $taxCategory ? $taxCategory->name :  'NO TAX',
                    'tax_rate' => (string)(count($taxRates) ? number_format($taxRates[0]->rate, 3) : 0),
                    'type' => 'PHYSICAL',
                    'unit_price' => (string)$lineItem->getPurchasable()->getPrice()
                ];
            }
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return $lineItems;
    }

    /**
     * @param Order $cart
     * @return array
     */
    private function getBillingAddress(Order $cart) : array {
        return [
            'city' => $cart->billingAddress->city,
            'country' => $cart->billingAddress->getCountryIso(),
            'organization_name' => json_decode($cart->twoCompany)->company_name,
            'postal_code' => $cart->billingAddress->zipCode,
            'street_address' => $cart->billingAddress->address1,
            'region' => $cart->billingAddress->getStateText()
        ];
    }

    /**
     * @param Order $cart
     * @return array
     */
    private function getShippingAddress(Order $cart) : array {
        return [
            'city' => $cart->shippingAddress->city,
            'country' => $cart->shippingAddress->getCountryIso(),
            'organization_name' => json_decode($cart->twoCompany)->company_name,
            'postal_code' => $cart->shippingAddress->zipCode,
            'street_address' => $cart->shippingAddress->address1,
            'region' => $cart->shippingAddress->getStateText()
        ];
    }

}