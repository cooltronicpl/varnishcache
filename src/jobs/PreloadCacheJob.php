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
use Craft;
use craft\errors\SiteNotFoundException;
use craft\helpers\StringHelper;
use craft\queue\BaseJob;

class PreloadCacheJob extends BaseJob
{
    public $url;

    /**
     * @throws SiteNotFoundException
     */
    public function execute($queue): void
    {
        $plugin = Craft::$app->plugins->getPlugin('varnishcache');

        $startPreloadTime = microtime(true);
        $parsedUrl = parse_url($this->url);
        $path = $parsedUrl['path'] ?? '';
        $path = ltrim($path, '/');
        $host = $parsedUrl['host'];
        if (!empty($plugin->getSettings()->timeout)) {
            $timeout = $plugin->getSettings()->timeout;
        } else {
            $timeout = 20;
        }

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $host));
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if (curl_exec($ch) === false) {
            $error = curl_error($ch);
            \Craft::error('Preload Error: ' . var_dump($error));
            throw new \Exception('Failed to preload cache for URL: ' . StringHelper::toString($this->url) . '. Error: ' . StringHelper::toString($error));
        } else {
            \Craft::info('Preload - Successful for URL: ' . StringHelper::toString($this->url));
        }
        curl_close($ch);
        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $path, 'siteId' => \Craft::$app->getSites()->getCurrentSite()->id]);
        $endPreloadTime = microtime(true);
        $preloadTime = $endPreloadTime - $startPreloadTime;
        if (empty($cacheEntry)) {
            $cacheEntryCreate = new VarnishCachesRecord(['uri' => $path, 'siteId' => \Craft::$app->getSites()->getCurrentSite()->id, 'createdAt' => date('Y-m-d H:i:s')]);
            $cacheEntryCreate->save();
            if ($cacheEntryCreate->hasErrors()) {
                throw new \Exception('Failed to create entry in Preload Cache Entry workaround: ' . StringHelper::toString($this->url));
            }
            $cacheEntry = VarnishCachesRecord::findOne(['uri' => $path, 'siteId' => \Craft::$app->getSites()->getCurrentSite()->id]);
            $content = file_get_contents($this->url);
            if ($plugin->getSettings()->optimizeContent) {
                $content = str_replace(array("\r", "\n", "           ", "      ", "      ", "    ", "  ", "    "), ' ', $content);
            }
            $file = $this->getCacheFileName($cacheEntry->uid);
            if (!$fp = fopen($file, 'w+')) {
                \Craft::error('HTML Cache create in PreloadJob Cache Entry workaround could not write as create new cache file "' . $file . '"');
                return;
            }
            fwrite($fp, $content);
            fclose($fp);
            if ($cacheEntry->hasErrors()) {
                throw new \Exception('Failed to update entry in Preload: ' . StringHelper::toString($this->url));
            }
            $v = new \cooltronicpl\varnishcache\services\VarnishCacheService();
            $v->clearCacheUrl($this->url);
            $ch = curl_init($this->url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $host));
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            if (curl_exec($ch) === false) {
                $error = curl_error($ch);
                \Craft::error('Preload Error: ' . var_dump($error));
                throw new \Exception('Failed to preload cache for URL: ' . StringHelper::toString($this->url) . '. Error: ' . StringHelper::toString($error));
            } else {
                \Craft::info('Preload - Cache Entry workaround - Successful for URL: ' . StringHelper::toString($this->url));
            }
            curl_close($ch);
            $endPreloadTime = microtime(true);
            $preloadTime = $endPreloadTime - $startPreloadTime;
            \Craft::info('Cache Entry workaround preload sucessful for URL: ' . StringHelper::toString($this->url));
        }
        if (empty($cacheEntry)) {
            throw new \Exception('Failed to preload Cache Entry workaround for URL, no cache entry: ' . StringHelper::toString($this->url));
        } else {
            \Craft::info('Preload - Cache Entry exist: ' . StringHelper::toString($this->url) . ' filesize: ' . filesize($this->getCacheFileName($cacheEntry->uid)) . " filename: " . $this->getCacheFileName($cacheEntry->uid));
        }
        $startFirstLoadTime = microtime(true);
        $check = curl_init($this->url);
        curl_setopt($check, CURLOPT_HTTPHEADER, array('Host: ' . $host));
        curl_setopt($check, CURLOPT_URL, $this->url);
        curl_setopt($check, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($check, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($check, CURLOPT_TIMEOUT, $timeout);

        if (curl_exec($check) === false) {
            $error = curl_error($check);
            \Craft::error('First Load of Cached Error: ' . StringHelper::toString($error));
            throw new \Exception('Failed to First Load of Cached for URL: ' . StringHelper::toString($this->url) . '. Error: ' . StringHelper::toString($error));
        } else {
            \Craft::info('First Load of Cached - Successful for URL: ' . StringHelper::toString($this->url));
        }
        curl_close($check);
        $endFirstLoadTime = microtime(true);
        $firstLoadTime = $endFirstLoadTime - $startFirstLoadTime;
        if (!empty($cacheEntry)) {
            $cacheEntry->preloadTime = $preloadTime;
            $cacheEntry->firstLoadTime = $firstLoadTime;
            $cacheEntry->cacheSize = filesize($this->getCacheFileName($cacheEntry->uid));
            $cacheEntry->save();
        }
    }

    protected function defaultDescription(): string
    {
        return 'Preloading cache for URL: ' . $this->url;
    }

    /**
     * Get the filename path
     *
     * @param string $uid
     * @return string
     */

    protected function getCacheFileName($uid)
    {
        return $this->getDirectory() . $uid . '.html';
    }

    /**
     * Get the directory path
     *
     * @return string
     */
    private function getDirectory()
    {
        if (!defined('CRAFT_STORAGE_PATH')) {
            define('CRAFT_STORAGE_PATH', CRAFT_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
        }
        return CRAFT_STORAGE_PATH . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'varnishcache' . DIRECTORY_SEPARATOR;
    }
}
