<?php
/**
 * CDN Cache & Preload to static HTML Helper plugin for Craft CMS 4.x
 *
 * CDN Cache & Preload to static HTML Helper Plugin Preloading function
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2024 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */
namespace cooltronicpl\varnishcache\jobs;
use cooltronicpl\varnishcache\records\VarnishCachesRecord;
use craft\errors\SiteNotFoundException;
use craft\helpers\StringHelper;
use craft\queue\BaseJob;
use cooltronicpl\varnishcache\VarnishCache;

class PreloadCacheJob extends BaseJob
{
    public $url;

    /**
     * @throws SiteNotFoundException
     */
    public function execute($queue): void
    {
        $startPreloadTime = microtime(true);
        $parsedUrl = parse_url($this->url);
        $path = $parsedUrl['path'] ?? '';
        $path = ltrim($path, '/');
        $host = $parsedUrl['host'];
        if (!empty(VarnishCache::getInstance()->getSettings()->timeout))
            $timeout = VarnishCache::getInstance()->getSettings()->timeout;
        else
            $timeout = 20;
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $host));
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if (curl_exec($ch) === false) {
            $error = curl_error($ch);
            \Craft::error('Preload Error: ' . var_dump($error));
            throw new \Exception ('Failed to preload cache for URL: ' . StringHelper::toString($this->url) . '. Error: ' . StringHelper::toString($error));
        } else {
            \Craft::info('Preload - Successful for URL: ' . StringHelper::toString($this->url));
        }
        curl_close($ch);
        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $path, 'siteId' => \Craft::$app->getSites()->getCurrentSite()->id]);
        $endPreloadTime = microtime(true);
        $preloadTime = $endPreloadTime - $startPreloadTime;
        if (empty($cacheEntry)) {
            throw new \Exception ('Error - No Preload Entry with Path: ' .StringHelper::toString($path) . ' Failed to Preload of Cached for URL: ' .  StringHelper::toString($this->url));
        } else {
            \Craft::info('Successful - Preload Entry with Path: ' . StringHelper::toString($path) . ', timeTaken: ' . StringHelper::toString($preloadTime) . ', id: ' . StringHelper::toString($cacheEntry->id) . ', uid: ' . StringHelper::toString($cacheEntry->uid));
            $cacheEntry->preloadTime = $preloadTime;
            $cacheEntry->save();
        }
        $startFirstLoadTime = microtime(true);
        $check = curl_init($this->url);
        curl_setopt($check, CURLOPT_HTTPHEADER, array('Host: '.$host));
        curl_setopt($check, CURLOPT_URL, $this->url);
        curl_setopt($check, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($check, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($check, CURLOPT_TIMEOUT, $timeout);

        if (curl_exec($check) === false) {
            $error = curl_error($check);
            \Craft::error('First Load of Cached Error: ' . StringHelper::toString($error));
            throw new \Exception ('Failed to First Load of Cached for URL: ' .  StringHelper::toString($this->url) . '. Error: ' .  StringHelper::toString($error));
        } else {
            \Craft::info('First Load of Cached - Successful for URL: ' .  StringHelper::toString($this->url));
        }
        curl_close($check);
        $endFirstLoadTime = microtime(true);
        $firstLoadTime = $endFirstLoadTime - $startFirstLoadTime;
        if (!empty($cacheEntry)) {
            $cacheEntry->firstLoadTime = $firstLoadTime;
            $cacheEntry->save();
        }
    }

    protected function defaultDescription(): string
    {
        return 'Preloading cache for URL: ' . $this->url;
    }
}
