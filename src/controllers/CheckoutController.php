<?php

namespace netlab\commercetwo\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use GuzzleHttp\Exception\GuzzleException;
use netlab\commercetwo\CommerceTwo;
use netlab\commercetwo\services\TwoHelper;
use craft\web\Controller;
use yii\log\Logger;
use yii\web\Response;

class CheckoutController extends Controller
{
    protected $allowAnonymous = ['index', 'company-search', 'company-address', 'is-company-allowed-for-payment', 'set-company-on-cart', 'set-customer-addresses'];

    public function actionIndex()
    {
        $cart = Commerce::getInstance()->getCarts()->getCart();
        $helper = TwoHelper::getInstance();
        $twoOrder = $helper->getOrder($cart);

        $cart->setAttributes([
            'twoOrderStatus'    => $twoOrder->status,
            'twoOrderState'     => $twoOrder->state,
            'twoInvoiceUrl'     => $twoOrder->invoice_url
        ]);

        $cart_saved = Craft::$app->elements->saveElement($cart);
        if(!$cart_saved) {
            throw new \Exception("Unable to save cart object: ". json_encode($cart->getErrors()));
        }

        if( 'VERIFIED' === $twoOrder->state && 'APPROVED' === $twoOrder->status ) {
            $confirmed = $helper->confirmOrder($cart);

            if( $confirmed ) {
                $autoCapture = Craft::$app->session->get('autoCapture', 0);
                if( $autoCapture ) {
                    Craft::$app->session->remove('autoCapture');
                    $capture = $helper->updateOrder($cart);
                }
                return $this->response->redirect($cart->returnUrl);
            } else {
                CommerceTwo::log("Couldn't confirm order at TWO. Order ID: ". $cart->getId(), Logger::LEVEL_ERROR);
                exit();
            }
        } else {
            CommerceTwo::log("Unexpected order status for order ID: ". $cart->getId(), Logger::LEVEL_ERROR);
            exit();
        }
    }

    /** Company lookup
     * @return Response
     * @throws GuzzleException
     */
    public function actionCompanySearch() {
        try {
            $this->requirePostRequest();
            $companyName = $this->request->getParam('companyName', null);
            $countryCode = $this->request->getParam('countryCode', 'no');
            $offset = $this->request->getParam('offset', 0);
            $limit = $this->request->getParam('limit', 30);

            if( !$companyName || strlen($companyName) < 3 ) {
                throw new \Exception("companyName field error");
            }
            $result = TwoHelper::getInstance()->searchCompany($companyName, $countryCode, $limit, $offset);
            return $this->asJson(['success' => true, 'items' => $result]);
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Company address lookup
     * @return Response
     * @throws GuzzleException
     */
    public function actionCompanyAddress() {
        try {
            $this->requirePostRequest();
            $companyId = $this->request->getParam('companyId', null);
            $countryCode = $this->request->getParam('countryCode', 'no');

            if( !$companyId ) {
                throw new \Exception("companyId field is required");
            }
            $result = TwoHelper::getInstance()->getCompanyAddress($companyId, $countryCode);
            return $this->asJson(['success' => true, 'address' => $result]);
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Checks whether a company is allowed to use the payment gateway
     * @return Response
     * @throws GuzzleException
     * @throws \Throwable
     */
    public function actionIsCompanyAllowedForPayment() {
        try {
            $this->requirePostRequest();
            $companyId = $this->request->getParam('companyId', null);
            $countryCode = $this->request->getParam('countryCode', 'no');
            $companyName = $this->request->getParam('companyName', null);

            if( !$companyId || !$companyName ) {
                throw new \Exception("Required fields missing");
            }

            $cart =  Commerce::getInstance()->getCarts()->getCart();
            $intent = TwoHelper::getInstance()->createOrderIntent($cart, false, [
                'company' => [
                    'company_name' => $companyName,
                    'country_prefix' => strtoupper($countryCode),
                    'organization_number' => (string)$companyId
                ], 'representative' => [
                    'email' => 'john.doe@example.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'phone_number' => '+4712312312'
                ]
            ]);
            return $this->asJson(['success' => true, 'approved' => $intent->approved, 'decline_reason' => $intent->decline_reason]);
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actionSetCompanyOnCart() {
        try {
            $this->requirePostRequest();
            $companyId = $this->request->getParam('companyId', null);
            $countryCode = $this->request->getParam('countryCode', 'no');
            $companyName = $this->request->getParam('companyName', null);
            $enabledForCheckout = $this->request->getParam('enabledForCheckout', 0);

            if( !$companyId || !$companyName || !$countryCode ) {
                throw new \Exception("Required fields missing");
            }

            if( !$enabledForCheckout ) {
                throw new \Exception("This company is not allowed for checkout");
            }

            $cart =  Commerce::getInstance()->getCarts()->getCart();
            $cart->setAttributes([
                'twoCompany', json_encode([
                    'company_name' => $companyName,
                    'country_prefix' => strtoupper($countryCode),
                    'organization_number' => (string)$companyId
                ]),
                'twoIsEnabledForCheckout' => $enabledForCheckout
            ]);
            $saved = Craft::$app->elements->saveElement($cart);
            return $this->asJson(['success' => $saved]);
        } catch (\Exception $e) {
            CommerceTwo::log($e->getMessage(), Logger::LEVEL_ERROR);
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Sets billing and shipping addresses on the cart and customer objects
     * Two requires international phone number format, make sure you provide a valid one
     * @return Response
     * @throws \Throwable
     */
    public function actionSetCustomerAddresses() {
        try {
            $this->requirePostRequest();

            $cart = Commerce::getInstance()->getCarts()->getCart();

            $firstName = $this->request->getBodyParam('firstName', false);
            $lastName = $this->request->getBodyParam('lastName', false);
            $phone = $this->request->getBodyParam('phone', false);
            $email = $this->request->getBodyParam('email', false);
            $billingSameAsShipping = $this->request->getBodyParam('billingSameAsShipping', false);
            $shippingSameAsBilling = $this->request->getBodyParam('shippingSameAsBilling', false);

            $billingCountryISO = $this->request->getBodyParam('billingCountryISO', 'NO');
            $billingCity = $this->request->getBodyParam('billingCity', false);
            $billingAddress = $this->request->getBodyParam('billingAddress', false);
            $billingAddress2 = $this->request->getBodyParam('billingAddress2', false);
            $billingZipCode = $this->request->getBodyParam('billingZipCode', false);

            $shippingCountryISO = $this->request->getBodyParam('shippingCountryISO', 'NO');
            $shippingCity = $this->request->getBodyParam('shippingCity', false);
            $shippingAddress = $this->request->getBodyParam('shippingAddress', false);
            $shippingAddress2 = $this->request->getBodyParam('shippingAddress2', false);
            $shippingZipCode = $this->request->getBodyParam('shippingZipCode', false);


            $billingCountry = \craft\commerce\Plugin::getInstance()
                ->getCountries()
                ->getCountryByIso($billingCountryISO);

            $shippingCountry = \craft\commerce\Plugin::getInstance()
                ->getCountries()
                ->getCountryByIso($shippingCountryISO);

            $billingData = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'city' => $billingCity,
                'address1' => $billingAddress,
                'address2' => $billingAddress2,
                'zipCode' => $billingZipCode,
                'phone' => $phone,
                'countryId' => $billingCountry->id,
            ];

            $shippingData = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'city' => $shippingCity,
                'address1' => $shippingAddress,
                'address2' => $shippingAddress2,
                'zipCode' => $shippingZipCode,
                'phone' => $phone,
                'countryId' => $shippingCountry->id,
            ];

            $commerce = craft\commerce\Plugin::getInstance();
            $customer = $commerce->getCustomers()->getCustomer();
            $primaryBilling = $customer->getPrimaryBillingAddress();
            $primaryShipping = $customer->getPrimaryShippingAddress();

            if( $cart->getEmail() !== $email ) {
                $cart->setEmail($email);
            }

            if( $primaryBilling ) {
                $primaryBilling->setAttributes($billingSameAsShipping ? $shippingData : $billingData);
                $cart->setBillingAddress($primaryBilling);
            } else {
                $cart->setBillingAddress($billingSameAsShipping ? $shippingData : $billingData);
            }

            if( $primaryShipping ) {
                $primaryShipping->setAttributes($shippingSameAsBilling ? $billingData : $shippingData);
                $cart->setShippingAddress($primaryShipping);
            } else {
                $cart->setShippingAddress($shippingSameAsBilling ? $billingData : $shippingData);
            }

            $saved = Craft::$app->elements->saveElement($cart);
            return $this->asJson(['success' => $saved]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}