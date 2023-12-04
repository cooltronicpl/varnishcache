<?php

/**
 * Varnish Cloudflare & Preload to static HTML Helper plugin for Craft CMS 4.x
 *
 * Varnish Cloudflare & Preload to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

namespace cooltronicpl\varnishcache;

use cooltronicpl\varnishcache\controllers\VarnishCacheController;
use cooltronicpl\varnishcache\jobs\PreloadSitemapJob;
use cooltronicpl\varnishcache\jobs\QueueSingleton;
use cooltronicpl\varnishcache\models\Settings;
use cooltronicpl\varnishcache\records\VarnishCacheElementRecord;
use cooltronicpl\varnishcache\records\VarnishCachesRecord;
use cooltronicpl\varnishcache\services\VarnishCacheService;
use cooltronicpl\varnishcache\variables\VarnishCacheClear;
use craft\base\Plugin;
use craft\elements\db\ElementQuery;
use craft\elements\GlobalSet;
use craft\elements\User;
use craft\events\PluginEvent;
use craft\helpers\FileHelper;
use craft\services\Elements;
use craft\services\Plugins;
use craft\web\Response;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use craft\elements\Entry;
use craft\events\ElementEvent;
use cooltronicpl\varnishcache\controller\CloudflareController;

/**
 * 
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 * @package   VarnishCache
 * @since     1.0.0
 *
 */
class VarnishCache extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * VarnishCache::$plugin
     *
     * @var VarnishCache
     */
    public static $plugin;
    public string $schemaVersion = '1.0.0';
    public bool $allowAnonymous = true;
    public bool $hasCpSettings = true;
    public $job;
    // Public Methods
    // =========================================================================

    public function hasCpSection()
    {
        return false;
    }

    public function hasSettings()
    {
        return true;
    }

    public $controllerMap = [
        'cloudflare' => CloudflareController::class,
    ];

    /**
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     * @throws \Twig_Error_Loader
     * @throws \RuntimeException
     */
    protected function settingsHtml(): string
    {
        // Get the settings model
        $settings = $this->getSettings();

        // Get the cache analytics data
        $cacheAnalytics = $this->VarnishCacheService->getCacheAnalytics();

        // Set the statistics data into settings
        $settings->averageAge = $cacheAnalytics['averageAge'];
        $settings->totalSize = $cacheAnalytics['totalSize'];
        $settings->numberCached = $cacheAnalytics['numberCached'];
        $settings->preloadAverage = $cacheAnalytics['preloadAverage'];
        $settings->firstLoadAverage = $cacheAnalytics['firstLoadAverage'];
        $settings->cacheRecords = $cacheAnalytics['cacheRecords'];

        return \Craft::$app->getView()->renderTemplate(
            'varnishcache/_settings',
            [
                'settings' => $settings,
            ]
        );
    }

    /**
     * Init plugin and initiate events
     */
    public function init()
    {
        self::$plugin = $this;
        // Register the VarnishCacheController

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
                        $uid = $event->element->uid;

                        // check if cache entry already exits otherwise create it
                        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $uri, 'siteId' => $siteId]);
                        if (!$cacheEntry) {
                            $cacheEntry = new VarnishCachesRecord();
                            $cacheEntry->id = null;
                            $cacheEntry->uri = $uri;
                            $cacheEntry->siteId = $siteId;
                            $cacheEntry->createdAt = date('Y-m-d H:i:s');
                            $cacheEntry->save();
                        }
                        // check if relation element is already added or create it
                        $cacheElement = VarnishCacheElementRecord::findOne(['elementId' => $elementId, 'cacheId' => $cacheEntry->id]);
                        if (!$cacheElement) {
                            $cacheElement = new VarnishCacheElementRecord();
                            $cacheElement->elementId = $elementId;
                            $cacheElement->cacheId = $cacheEntry->id;
                            $cacheElement->createdAt = date('Y-m-d H:i:s');
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
                        if ($job->isRun() == false) {
                            QueueSingleton::getInstance($job)->push($job, 150, 0, 1800);

                        }
                    }
                }
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            function (ElementEvent $event) {
                if ($event->element === $this) {
                    if (VarnishCache::getInstance()->getSettings()->purgeCache === '1') {
                        $this->VarnishCacheService->clearCacheFiles();
                    }
                    if (VarnishCache::getInstance()->getSettings()->preloadSitemap === '1') {
                        // If the event element is an Entry (a page)
                        if ($event->element instanceof \craft\elements\Entry ) {
                            // Create a new PreloadSitemapJob
                            $job = new PreloadSitemapJob();
                            // Push the job to the queue
                            QueueSingleton::getInstance($job)->push($job, 150, 0, 1800);
                        }
                    }
                }
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                if ($event->element instanceof \craft\elements\Entry ) {
                    // Check if the preloadSitemap setting of the VarnishCache instance is set to '1'
                    if (VarnishCache::getInstance()->getSettings()->purgeCache === '1') {
                        $this->VarnishCacheService->clearCacheFiles();
                    }
                    if (VarnishCache::getInstance()->getSettings()->preloadSitemap === '1') {
                        // If the event element is an Entry (a page)
                        // Create a new PreloadSitemapJob
                        $job = new PreloadSitemapJob();
                        // Push the job to the queue
                        QueueSingleton::getInstance($job)->push($job, 150, 0, 1800);
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
