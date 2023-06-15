<?php

namespace cooltronicpl\varnishcache\jobs;

/**
 * Varnish Cache Helper plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2022 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

use cooltronicpl\varnishcache\services\VarnishCacheService;
use cooltronicpl\varnishcache\VarnishCache;
use cooltronicpl\varnishcache\jobs\QueueSingleton;

class PreloadSitemapJob extends \craft\queue\BaseJob
{

    private $hasRun = false;
    
    public function myInit()
    {

    }

    public function execute($queue): void
    {
        if (!$this->hasRun) {
            $this->myInit();

            $this->hasRun = true;
            if (VarnishCache::getInstance()->getSettings()->resetQueue==true)
            {
                $now = \Craft::$app->formatter->asDatetime(time());
                $queue = \Yii::$app->queue;
            }
            \Craft::info('Before Varnish Execution loop: "' . $now . '"');

            // delete cache files and preload the cache from the sitemap
            $v = new VarnishCacheService;
            $v->clearCacheFiles();
            $v->preloadCacheFromSitemap();
            if (VarnishCache::getInstance()->getSettings()->cacheDuration) {
                $duration = (VarnishCache::getInstance()->getSettings()->cacheDuration * 60 );
            } else {
                $duration = 60;
            }
            if (VarnishCache::getInstance()->getSettings()->resetQueue==true)
            {
                $nextTask = QueueSingleton::getInstance();
                // Delete all tasks
                $queue->releaseAll();
            }
            $this->hasRun = false;
            if (VarnishCache::getInstance()->getSettings()->resetQueue==true)
            {
                $nextTask->push(new PreloadSitemapJob(), 1, $duration, 1800, $queue);
                $now = \Craft::$app->formatter->asDatetime(time());
                \Craft::info('After Varnish Execution loop: "' . $now . '"');
            }
        }
    }
    
    protected function defaultDescription(): string
    {
        return \Craft::t('app', 'Preloading CRON active');
    }

    public function isRun(): bool{
        return $this->hasRun;
    }

}
