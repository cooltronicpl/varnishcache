<?php

namespace cooltronicpl\varnishcache\jobs;

/**
 * Varnish Cache with Preload (Preheat) to static HTML Helper plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache with Preload (Preheat) to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

use cooltronicpl\varnishcache\services\VarnishCacheService;
use cooltronicpl\varnishcache\VarnishCache;
use cooltronicpl\varnishcache\jobs\QueueSingleton;
use craft\helpers\Table;

class PreloadSitemapJob extends \craft\queue\BaseJob
{

    private $hasRun = false;
    
    public function execute($queue): void
    {
        if (!$this->hasRun) {

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
                \Craft::info('After Varnish Execution loop: "' . $duration . '"');

            } else {
                $duration = 3600;
            }
            if (VarnishCache::getInstance()->getSettings()->resetQueue==true)
            {
                // Delete tasks with the IDs of the tasks with the description
                $taskIds = (new \craft\db\Query())
                    ->select(['id'])
                    ->from('{{%queue}}')
                    ->where(['description' => 'Preloading CRON active'])
                    ->column();
            
                // Release (delete) the tasks
                foreach ($taskIds as $taskId) {
                    $queue->release($taskId);
                }
            }
            if (VarnishCache::getInstance()->getSettings()->resetQueue==true)
            {
                $job = new PreloadSitemapJob();
                $this->hasRun = false;
                $nextTask=QueueSingleton::getInstance();
                $nextTask->push($job, 150, $duration, 1800);

                \Craft::info('After Varnish Execution loop: "' . $now . '"');
            }
        }
    }
    
    protected function defaultDescription(): string
    {
        return 'Preloading CRON active';
    }

    public function isRun(): bool{
        return $this->hasRun;
    }

}
