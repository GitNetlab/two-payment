<?php

namespace netlab\commercetwo\models;

use craft\base\Model;
use craft\helpers\App;
use craft\behaviors\EnvAttributeParserBehavior;

class Settings extends Model
{
    public ?string $merchantId = null;
    public ?string $testApiKey = null;
    public ?string $liveApiKey = null;
    public string $environment = 'test';
    public string $language = 'en_US';

    public function getMerchantId() :?string {
        return App::parseEnv($this->merchantId);
    }

    public function getTestApiKey() :?string {
        return App::parseEnv($this->testApiKey);
    }

    public function getLiveApiKey() :?string {
        return App::parseEnv($this->liveApiKey);
    }

    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['merchantId', 'testApiKey', 'liveApiKey'],
            ],
        ];
    }

    public function rules() : array
    {
        return [
            [['merchantId', 'environment', 'language', 'testApiKey', 'liveApiKey'], 'required'],
        ];
    }

}