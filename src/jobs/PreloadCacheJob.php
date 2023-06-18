<?php
/**
 * Varnish Cache & Preload to static HTML Helper plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache & Preload to static HTML Helper Plugin Preloading function
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */
namespace cooltronicpl\varnishcache\jobs;
use cooltronicpl\varnishcache\records\VarnishCachesRecord;

use craft\queue\BaseJob;

class PreloadCacheJob extends BaseJob
{
    public $url;

    public function execute($queue): void
    {
        $start_preload_time = microtime(true);

        $parsedUrl = parse_url($this->url);
        $path = $parsedUrl['path'] ?? '';
        $path = ltrim($path, '/');

        // Preload the cache for the URL
        $ch = curl_init();
        $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($varnishhost));
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        if (curl_exec($ch) === false) {
            $error = curl_error($ch);
            \Craft::error('Preload Error: ' . var_dump($error));
            throw new \Exception ('Failed to preload cache for URL: ' . $this->url . '. Error: ' . $error);
        } else {
            \Craft::info('Preload - Successful for URL: ' . $this->url);
        }

        curl_close($ch);
        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $path, 'siteId' => \Craft::$app->getSites()->getCurrentSite()->id]);

        $end_preload_time = microtime(true);
        $preload_time_taken = $end_preload_time - $start_preload_time;
        if (empty($cacheEntry)) {
            \Craft::error('Error - No Preload Entry with Path: ' . $path);
        } else {
            \Craft::info('Successful - Preload Entry with Path: ' . $path . ', timeTaken: ' . $preload_time_taken . ', id: ' . $cacheEntry->id . ', uid: ' . $cacheEntry->uid);
            $cacheEntry->preloadTime = $preload_time_taken;
            $cacheEntry->save();
        }
        $start_firstload_time = microtime(true);

        $check = curl_init();

        curl_setopt($check, CURLOPT_HTTPHEADER, array($varnishhost));
        curl_setopt($check, CURLOPT_URL, $this->url);
        curl_setopt($check, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($check, CURLOPT_FOLLOWLOCATION, 1);

        if (curl_exec($check) === false) {
            $errorfirst = curl_error($check);
            \Craft::error('First Load of Cached Error: ' . var_dump($errorfirst));
            throw new \Exception ('Failed to First Load of Cached for URL: ' . $this->url . '. Error: ' . $errorfirst);
        } else {
            \Craft::info('First Load of Cached - Successful for URL: ' . $this->url);
        }

        curl_close($check);
        $end_firstload_time = microtime(true);
        $firstload_time_taken = $end_firstload_time - $start_firstload_time;
        if (!empty($cacheEntry)) {
            $cacheEntry->firstLoadTime = $firstload_time_taken;
            $cacheEntry->save();
        }
    }

    protected function defaultDescription(): string
    {
        return 'Preloading cache for URL: ' . $this->url;
    }
}
