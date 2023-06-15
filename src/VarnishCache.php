<?php

/**
 * Varnish Cache Helper plugin for Craft CMS 3.x
 *
 * Varnish Cache Helper Plugin with HTTP & HTTPS support
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

namespace cooltronicpl\varnishcache;

use Craft;
use craft\base\Plugin;
use craft\web\Response;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Elements;
use craft\helpers\FileHelper;
use cooltronicpl\varnishcache\services\VarnishCacheService;
use cooltronicpl\varnishcache\models\Settings;
use yii\base\Event;
use craft\elements\db\ElementQuery;
use cooltronicpl\varnishcache\jobs\QueueSingleton;
use cooltronicpl\varnishcache\records\VarnishCachesRecord;
use cooltronicpl\varnishcache\records\VarnishCacheElementRecord;
use cooltronicpl\varnishcache\variables\VarnishCacheClear;
use craft\elements\User;
use craft\elements\GlobalSet;
use cooltronicpl\varnishcache\jobs\PreloadSitemapJob;
use craft\web\twig\variables\CraftVariable;

class VarnishCache extends Plugin
{
    public static $plugin;
    public $schemaVersion = '1.0.0';
    public $hasCpSettings = true;

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate(
            'varnishcache/_settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }

    public function init()
    {
        self::$plugin = $this;


        // ignore console requests
        if ($this->isInstalled && !\Craft::$app->request->getIsConsoleRequest()) {
            $this->setComponents(
                [
                    'VarnishCacheService' => VarnishCacheService::class,
                ]
            );
            // first check if there is a cache to serve
            $this->VarnishCacheService->checkForCacheFile();

            // after request send try and create the cache file
            Event::on(Response::class, Response::EVENT_AFTER_SEND, function (Event $event) {
                $this->VarnishCacheService->createCacheFile();
                
            });

            // on every update of an element clear the caches related to the element
            Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function (Event $event) {
                $this->VarnishCacheService->clearCacheFile($event->element->id);
                
            });

            // on populated element put to relation table
            Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT, function ($event) {
                // procceed only if it should be created
                if ($this->VarnishCacheService->canCreateCacheFile()) {
                    $elementClass = get_class($event->element);
                    if (!in_array($elementClass, [User::class, GlobalSet::class])) {
                        $uri = \Craft::$app->request->getParam('p', '');
                        $siteId = \Craft::$app->getSites()->getCurrentSite()->id;
                        $elementId = $event->element->id;

                        // check if cache entry already exits otherwise create it
                        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $uri, 'siteId' => $siteId]);
                        if (!$cacheEntry) {
                            $cacheEntry = new VarnishCachesRecord();
                            $cacheEntry->id = null;
                            $cacheEntry->uri = $uri;
                            $cacheEntry->siteId = $siteId;
                            $cacheEntry->save();
                        }
                        // check if relation element is already added or create it
                        $cacheElement = VarnishCacheElementRecord::findOne(['elementId' => $elementId, 'cacheId' => $cacheEntry->id]);
                        if (!$cacheElement) {
                            $cacheElement = new VarnishCacheElementRecord();
                            $cacheElement->elementId = $elementId;
                            $cacheElement->cacheId = $cacheEntry->id;
                            $cacheElement->save();
                        }
                    }
                }
                
            });

            // always reset purge cache value
            Event::on(Plugin::class, Plugin::EVENT_BEFORE_SAVE_SETTINGS, function ($event) {
                if ($event->sender === $this) {
                    $settings = $event->sender->getSettings();
                    if ($settings->purgeCache === '1') {
                        $this->VarnishCacheService->clearCacheFiles();
                    }
                    // always reset value for purge cache
                    $event->sender->setSettings(['purgeCache' => '']);
                }
            });
        }

        // After install create the temp folder
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // create cache directory
                    $path = \Craft::$app->path->getStoragePath() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'varnishcache' . DIRECTORY_SEPARATOR;
                    FileHelper::createDirectory($path);
                    \Craft::$app->set('yiiVarnishQueue', [
                        'class' => \yii\queue\db\Queue::class,
                        'as log' => \yii\queue\LogBehavior::class,
                    ]);
                }
                
            }

        );

        // Before uninstall clear all cache
        Event::on(
            Plugins::class,
            Plugins::EVENT_BEFORE_UNINSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // clear all files
                    $this->VarnishCacheService->clearCacheFiles();
                }
            }

        );

        // After uninstall remove the cache dir
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_UNINSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // remove varnishcache dir
                    $path = \Craft::$app->path->getStoragePath() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'varnishcache' . DIRECTORY_SEPARATOR;
                    FileHelper::removeDirectory($path);

                }

            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {

                    if (VarnishCache::getInstance()->getSettings()->preloadSitemap === '1') {

                        $job = new PreloadSitemapJob();
                        if($job->isRun()==false){
                            QueueSingleton::getInstance($job)->push($job, 1, 0, 1800);

                        }
                    }
                }
            }
        );
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('varnish', VarnishCacheClear::class);
            }
        );
        parent::init();
    }
}
