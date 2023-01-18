<?php

namespace netlab\commercetwo\models;

use craft\base\Model;

class Settings extends Model
{
    public string $merchantId = 'YOUR MERCHANT ID';
    public string $environment = 'test';
    public string $language = 'en_US';
    public string $testApiKey = 'TEST API KEY';
    public string $liveApiKey = 'LIVE API KEY';


    public function rules() : array
    {
        return [
            [['merchantId', 'environment', 'language', 'testApiKey', 'liveApiKey'], 'required'],
        ];
    }
        
}