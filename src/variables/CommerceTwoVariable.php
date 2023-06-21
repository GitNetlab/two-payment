<?php

namespace netlab\commercetwo\variables;

use Craft;
use craft\commerce\Plugin as Commerce;
use netlab\commercetwo\CommerceTwo;
use netlab\commercetwo\services\TwoHelper;
use yii\log\Logger;

class CommerceTwoVariable
{
    public function searchCompany(string $name, string $countryCode = 'no', int $limit = 30, int $offset = 0) : ?array
    {
        return TwoHelper::getInstance()->searchCompany($name, $countryCode, $limit, $offset);
    }

    public function getCompanyAddress(string|int $companyId, string $countryCode = 'no') : ?object
    {
        return TwoHelper::getInstance()->getCompanyAddress($companyId, $countryCode);
    }

    public function setCompanyOnCart(string $companyName, string|int $companyId, string $countryCode = 'no') : bool {
        try {
            $cart =  Commerce::getInstance()->getCarts()->getCart();
            $cart->setFieldValues(['twoCompany' => [
                'company_name' => $companyName,
                'country_prefix' => strtoupper($countryCode),
                'organization_number' => $companyId,
            ]]);
            return Craft::$app->elements->saveElement($cart);
        } catch (\Exception $e) {
            CommerceTwo::error("Error while setting company on cart! Error: {$e->getMessage()}");
            throw $e;
        }
    }
}