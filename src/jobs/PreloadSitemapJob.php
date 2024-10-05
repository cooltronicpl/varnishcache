<?php
/**
 * CDN Cache & Preload to static HTML Helper plugin for Craft CMS 4.x
 *
 * CDN Cache & Preload to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2024 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */
namespace cooltronicpl\varnishcache\jobs;

use cooltronicpl\varnishcache\jobs\QueueSingleton;
use cooltronicpl\varnishcache\services\VarnishCacheService;
use Craft;

class PreloadSitemapJob extends \craft\queue\BaseJob

{

    private $hasRun = false;

    public function execute($queue): void
    {
        if (!$this->hasRun) {
            $this->hasRun = true;
            $plugin = Craft::$app->plugins->getPlugin('varnishcache');
            $now = 0;
            if ($plugin->getSettings()->resetQueue == true) {
                $now = \Craft::$app->formatter->asDatetime(time());
                $queue = \Yii::$app->queue;
            }
            \Craft::info('Before Varnish Execution loop: "' . $now . '"');
            $v = new VarnishCacheService;
            $v->clearCacheFiles();
            $v->preloadCacheFromSitemap();

            if ($plugin->getSettings()->cacheDuration) {
                $duration = ($plugin->getSettings()->cacheDuration * 60);
                \Craft::info('After Varnish Execution loop: "' . $duration . '"');

            } else {
                $duration = 3600;
            }
            if ($plugin->getSettings()->resetQueue == true) {
                $taskIds = (new \craft\db\Query())
                    ->select(['id'])
                    ->from('{{%queue}}')
                    ->where(['description' => 'Preloading CRON active'])
                    ->column();
                foreach ($taskIds as $taskId) {
                    $queue->release($taskId);
                }
                $job = new PreloadSitemapJob();
                $this->hasRun = false;
                $nextTask = QueueSingleton::getInstance();
                $nextTask->push($job, 150, $duration, 1800);
                \Craft::info('After Varnish Execution loop: "' . $now . '"');
            }
        }
    }

    protected function defaultDescription(): string
    {
        return 'Preloading CRON active';
    }

    public function isRun(): bool
    {
        return $this->hasRun;
    }

}
