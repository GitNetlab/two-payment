<?php
/**
 * Commerce Two plugin for Craft CMS 4.x
 *
 * Two integration for Craft CMS
 *
 * @link      https://netlab.no
 * @copyright Copyright (c) 2022 Netlab
 */

namespace netlab\commercetwo;

use Craft;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\omnipay\base\Gateway;
use craft\commerce\services\Gateways;
use craft\db\Query;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Lightswitch;
use craft\fields\PlainText;
use craft\helpers\FieldHelper;
use craft\helpers\StringHelper;
use craft\models\FieldGroup;
use craft\models\FieldLayoutTab;
use craft\records\Field;
use craft\services\Fields;
use craft\services\Plugins;
use craft\events\PluginEvent;

use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use Monolog\Formatter\LineFormatter;
use netlab\commercetwo\services\TwoHelper;
use netlab\commercetwo\two\TwoGateway;
use netlab\commercetwo\models\Settings;
use netlab\commercetwo\variables\CommerceTwoVariable;
use Omnipay\Omnipay;
use Psr\Log\LogLevel;
use yii\base\BaseObject;
use yii\base\Event;
use yii\log\Logger;

use craft\commerce\omnipay\events\SendPaymentRequestEvent;


/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/4.x/extend/
 *
 * @author    Netlab
 * @package   CommerceTwo
 * @since     1.0.0
 *
 */
class CommerceTwo extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * CommerceTwo::$plugin
     *
     * @var CommerceTwo
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * Set to `true` if the plugin should have a settings view in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSection = false;

    // Public Methods
    // =========================================================================

    public static function log($message, $level = Logger::LEVEL_INFO){
        Craft::getLogger()->log($message, $level, 'commerce-two');
    }
    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * CommerceTwo::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                    $this->createFields();
                }
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_UNINSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ( $event->plugin === $this ) {
                    // We are uninstalled
                    $this->removeFields();
                }
            }
        );

        Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES,  function(RegisterComponentTypesEvent $event) {
            $event->types[] = TwoGateway::class;
        });

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['commerce-two/return/'] = 'commerce-two/checkout';
                $event->rules['commerce-two/company-search/'] = 'commerce-two/checkout/company-search';
                $event->rules['commerce-two/company-address/'] = 'commerce-two/checkout/company-address';
                $event->rules['commerce-two/company-check/'] = 'commerce-two/checkout/is-company-allowed-for-payment';
                $event->rules['commerce-two/set-company/'] = 'commerce-two/checkout/set-company-on-cart';
                $event->rules['commerce-two/set-customer-addresses/'] = 'commerce-two/checkout/set-customer-addresses';
            }
        );


        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                $variable = $event->sender;
                $variable->set('twoPayment', CommerceTwoVariable::class);
            }
        );

        /**
         * Logging in Craft involves using one of the following methods:
         *
         * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
         * Craft::info(): record a message that conveys some useful information.
         * Craft::warning(): record a warning message that indicates something unexpected has happened.
         * Craft::error(): record a fatal error that should be investigated as soon as possible.
         *
         * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
         *
         * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
         * the category to the method (prefixed with the fully qualified class name) where the constant appears.
         *
         * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
         * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
         *
         * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
         */
        Craft::info(
            Craft::t(
                'commerce-two',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

    }

    // Protected Methods
    // =========================================================================

    protected function createSettingsModel() : ?\craft\base\Model
    {
        return new Settings();
    }

    protected function settingsHtml() : ?string
    {
        return \Craft::$app->getView()->renderTemplate(
            'commerce-two/settings',
            [ 'settings' => $this->getSettings() ]
        );
    }

    private string $fieldGroupName = 'Two Inc.';
    private array $fields = [
        [
            'name' => 'Order ID',
            'handle' => 'twoOrderId',
            'maxLength' => 64,
            'type' => 'text'
        ],
        [
            'name' => 'Company object',
            'handle' => 'twoCompany',
            'maxLength' => 256,
            'type' => 'text'
        ],
        [
            'name' => 'Invoice url',
            'handle' => 'twoInvoiceUrl',
            'maxLength' => 512,
            'type' => 'text'
        ],
        [
            'name' => 'Credit note url',
            'handle' => 'twoCreditNoteUrl',
            'maxLength' => 512,
            'type' => 'text'
        ],
        [
            'name' => 'Order status',
            'handle' => 'twoOrderStatus',
            'maxLength' =>  32,
            'type' => 'text'
        ],
        [
            'name' => 'Order state',
            'handle' => 'twoOrderState',
            'maxLength' =>  32,
            'type' => 'text'
        ],
        [
            'name' => 'Enabled for checkout',
            'handle' => 'twoIsEnabledForCheckout',
            'type' => 'bool'
        ],
    ];
    private function createFields() {

        $fieldGroup = \craft\records\FieldGroup::findOne(['name' => $this->fieldGroupName]);

        if( !$fieldGroup ) {
            $fieldGroup = new FieldGroup([
                'name' => $this->fieldGroupName
            ]);
            Craft::$app->fields->saveGroup($fieldGroup);
        }

        $fields = [];

        foreach ($this->fields as $field) {
            $existing = Field::findOne(['handle' => $field['handle']]);

            if( $existing ) {
                continue;
            }

            if( $field['type'] === 'text' ) {
                $newField = new PlainText([
                    'groupId' => $fieldGroup['id'],
                    'name' => $field['name'],
                    'handle' => $field['handle'],
                    'required' => false,
                    'charLimit' => $field['maxLength'],
                    'multiline' => false,
                    'uid' => StringHelper::UUID()
                ]);
            } else if( $field['type'] === 'bool' ) {
                $newField = new Lightswitch([
                    'groupId' => $fieldGroup['id'],
                    'name' => $field['name'],
                    'handle' => $field['handle'],
                    'required' => false,
                    'uid' => StringHelper::UUID(),
                ]);
            } else {
                continue;
            }

            Craft::$app->fields->saveField($newField);
            $fields[] = $newField;
        }

        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $currentTabs = $fieldLayout->getTabs();

        $tab = false;

        foreach ($currentTabs as $cTab) {
            if( $cTab->name === $this->fieldGroupName ) {
                $tab = $cTab;
            }
        }

        if( !$tab ) {
            $tab = new FieldLayoutTab();
            $tab->name = $this->fieldGroupName;
            $tab->setLayout($fieldLayout);
        }

        if( empty($tab->getElements() ) && count($fields) ) {
            $elements = array_map(function($field) {
                return [
                    'type' => CustomField::class,
                    'fieldUid' => $field->uid,
                    'required' => false,

                ];
            }, $fields);

            $tab->setElements($elements);
            $tab->sortOrder = count($currentTabs);
            $fieldLayout->setTabs(array_merge($currentTabs,[$tab]));

            Craft::$app->fields->saveLayout($fieldLayout);
        }
    }
    private function removeFields() {

        // remove field layout tab from order
        $order = new Order();
        $fieldLayout = $order->getFieldLayout();
        $currentTabs = $fieldLayout->getTabs();

        $tab = false;

        foreach ($currentTabs as $key => $cTab) {
            if( $cTab->name === $this->fieldGroupName ) {
                unset($currentTabs[$key]);
                break;
            }
        }

        $fieldLayout->setTabs($currentTabs);
        Craft::$app->fields->saveLayout($fieldLayout);

        // remove fields
        $fieldService = new Fields();
        foreach ($this->fields as $fieldData) {
            $fieldRecord = Field::findOne(['handle' => $fieldData['handle']]);
            if( $fieldRecord ) {
                $fieldService->deleteFieldById($fieldRecord->id);
            }
        }

        // remove field group
        $fieldGroup = \craft\records\FieldGroup::findOne(['name' => $this->fieldGroupName]);
        if( $fieldGroup ) {
            $fieldGroup->delete();
        }
    }

}
