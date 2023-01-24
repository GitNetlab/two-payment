<?php


namespace netlab\commercetwo\two;


use Craft;
use craft\commerce\elements\Order;
use craft\commerce\omnipay\base\OffsiteGateway;
use netlab\commercetwo\CommerceTwo;
use netlab\commercetwo\models\Settings;
use Omnipay\Common\AbstractGateway;
use netlab\commercetwo\two\omnipay\src\Gateway as OmnipayTwoGateway;

class TwoGateway extends OffsiteGateway {

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Two Inc.');
    }

    public function createGateway(): AbstractGateway {
        $pluginSettings = CommerceTwo::getInstance()->getSettings();
        $gateway = static::createOmnipayGateway($this->getGatewayClassName());
        $gateway->setMerchantId($pluginSettings->getMerchantId());

        if( $pluginSettings->environment !== 'live' ) {
            $gateway->setPassword( $pluginSettings->getTestApiKey() );
            $gateway->setTestMode(true);
        } else {
            $gateway->setPassword( $pluginSettings->getLiveApiKey() );
        }

        return $gateway;
    }

    protected function getGatewayClassName() : ?string {
        return '\\'.OmnipayTwoGateway::class;
    }
}
