<?php
/**
 * Elasticsearch plugin for Craft CMS 3.x
 *
 * Bring the power of Elasticsearch to you Craft 3 CMS project
 *
 * @link      https://www.lahautesociete.com
 * @copyright Copyright (c) 2018 Alban Jubert
 */

namespace lhs\elasticsearch;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\elements\Entry;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\Application;
use craft\web\Controller;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use lhs\elasticsearch\jobs\IndexElement;
use lhs\elasticsearch\models\Settings;
use lhs\elasticsearch\services\Elasticsearch as ElasticsearchService;
use lhs\elasticsearch\utilities\ElasticsearchUtilities;
use lhs\elasticsearch\variables\ElasticsearchVariable;
use yii\base\Event;
use yii\elasticsearch\Connection;
use yii\elasticsearch\DebugPanel;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Alban Jubert
 * @package   Elasticsearch
 * @since     1.0.0
 *
 * @property  services\Elasticsearch service
 * @property  Settings               settings
 * @property  Connection             elasticsearch
 * @method    Settings getSettings()
 */
class Elasticsearch extends Plugin
{
    const TRANSLATION_CATEGORY = 'elasticsearch';
    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        $this->name = 'Elasticsearch';

        $this->setComponents([
            'service' => ElasticsearchService::class,
        ]);

        Craft::$app->set('elasticsearch', [
            'class' => 'yii\elasticsearch\Connection',
            'nodes' => [
                ['http_address' => $this->settings->http_address],
                // configure more hosts if you have a cluster
            ],
            'auth'  => [
                'username' => $this->settings->auth_username,
                'password' => $this->settings->auth_password,
            ],
        ]);

        // Add in our console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'lhs\elasticsearch\console\controllers';
        }

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['elasticsearch/test-connection'] = 'elasticsearch/elasticsearch/test-connection';
                $event->rules['elasticsearch/reindex-all'] = 'elasticsearch/elasticsearch/reindex-all';
            }
        );

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('elasticsearch', ElasticsearchVariable::class);
            }
        );

        /*
         * Add or update an element to the index
         */
        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            function (Event $event) {
                $element = $event->sender;
                if ($element instanceof Entry) {
                    if ($element->enabled) {
                        Craft::$app->queue->push(new IndexElement([
                            'siteId'    => $element->siteId,
                            'elementId' => $element->id,
                        ]));
                    } else {
                        Elasticsearch::getInstance()->service->deleteEntry($element);
                    }

                }
            }
        );

        /*
         * Delete an element from the index
         */
        Event::on(
            Element::class,
            Element::EVENT_AFTER_DELETE,
            function (Event $event) {
                $element = $event->sender;
                Elasticsearch::getInstance()->service->deleteEntry($element);
            }
        );

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ElasticsearchUtilities::class;
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    $this->service->reindexAll();
                }
            }
        );

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[Craft::t(self::TRANSLATION_CATEGORY, 'Elasticsearch')] = [
                    'reindex' => ['label' => Craft::t(self::TRANSLATION_CATEGORY, 'Refresh Elasticsearch index')],
                ];
            }
        );

        Event::on(
            Application::class,
            Application::EVENT_BEFORE_REQUEST,
            function () {
                /** @var \yii\debug\Module */
                $debugModule = Craft::$app->getModule('debug');

                $debugModule->panels['elasticsearch'] = new DebugPanel(['module' => $debugModule]);
            }
        );
Controller::EVENT_BEFORE_ACTION;
        Craft::info(
            Craft::t(
                self::TRANSLATION_CATEGORY,
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'elasticsearch/cp/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }


}
