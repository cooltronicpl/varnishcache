<?php

/**
 * Varnish Cache Purge plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache Purge Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright ( c ) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

namespace cooltronicpl\varnishcache\services;

use cooltronicpl\varnishcache\jobs\ClearUriJob;
use cooltronicpl\varnishcache\jobs\PreloadCacheJob;
use cooltronicpl\varnishcache\jobs\QueueSingleton;
use cooltronicpl\varnishcache\records\VarnishCacheElementRecord;
use cooltronicpl\varnishcache\records\VarnishCachesRecord;
use cooltronicpl\varnishcache\VarnishCache;
use craft\base\Component;
use craft\helpers\FileHelper;

class VarnishCacheService extends Component
{
    private $uri;
    private $siteId;
    private $settings;

    public function __construct()
    {
        $this->uri = \Craft::$app->request->getParam('p', '');
        $this->siteId = \Craft::$app->getSites()->getCurrentSite()->id;
        $this->settings = VarnishCache::getInstance()->getSettings();

        // listen for the custom event and call the preloadCacheFromSitemap() function when the event is triggered

    }

    public function checkForCacheFile()
    {
        // Bypass cache for live preview
        if (\Craft::$app->request->getQueryParam('x-craft-live-preview')) {
            return;
        }

        if (!$this->canCreateCacheFile()) {
            return;
        }

        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $this->uri, 'siteId' => $this->siteId]);

        if ($cacheEntry) {
            $file = $this->getCacheFileName($cacheEntry->uid);

            if (file_exists($file)) {
                // Get the size of the cache file
                $cacheEntry->cacheSize = filesize($file);
                $cacheEntry->save();
                if ($this->loadCache($file)) {
                    return \Craft::$app->end();
                }
            }
        }

        ob_start();
    }

    public function canCreateCacheFile(): bool
    {
        $app = \Craft::$app;

        // Check various conditions and return false if any of them are met
        switch (true) {
            // forced mode in all cases when enabled
            case $this->settings->enableGeneral && $this->settings->forceOn == true:
                break;

            // Skip if we're running in devMode and not in force mode
            case $app->config->general->devMode === true && $this->settings->forceOn == false:
                return false;

            // Skip if not enabled
            case $this->settings->enableGeneral == false:
                return false;

            // Skip if system is not on and not in force mode
            case !$app->getIsSystemOn() && $this->settings->forceOn == false:
                return false;

            // Skip if it's a CP Request
            case $app->request->getIsCpRequest():
                return false;

            // Skip if it's an action Request
            case $app->request->getIsActionRequest():
                return false;

            // Skip if it's a preview request
            case $app->request->getIsLivePreview():
                return false;

            // Skip if it's a post request
            case !$app->request->getIsGet():
                return false;

            // Skip if it's an ajax request
            case $app->request->getIsAjax():
                return false;

            // Skip if route from element api
            case $this->isElementApiRoute():
                return false;

            // Skip if currently requested URL path is excluded
            case $this->isPathExcluded():
                return false;
        }

        // If none of the conditions above were met, return true
        return true;
    }

    /**
     * Check if route is from element api
     *
     * @return boolean
     */

    private function isElementApiRoute()
    {
        $plugin = \Craft::$app->getPlugins()->getPlugin('element-api');
        if ($plugin) {
            $elementApiRoutes = $plugin->getSettings()->endpoints;
            $routes = array_keys($elementApiRoutes);
            foreach ($routes as $route) {
                // form the correct expression
                $route = preg_replace('~\<.*?:(.*?)\>~', '$1', $route);
                $found = preg_match('~' . $route . '~', $this->uri);
                if ($found) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if currently requested URL path has been added to list of excluded paths
     *
     * @return bool
     */

    private function isPathExcluded()
    {
        // determine currently requested URL path and the multi-site ID
        $requestedPath = \Craft::$app->request->getFullPath();
        $requestedSiteId = \Craft::$app->getSites()->getCurrentSite()->id;

        // compare with excluded paths and sites from the settings
        if (!empty($this->settings->excludedUrlPaths)) {
            foreach ($this->settings->excludedUrlPaths as $exclude) {
                $path = reset($exclude);
                $siteId = intval(next($exclude));

                // check if requested path is one of those of the settings
                if ($requestedPath == $path || preg_match('@' . $path . '@', $requestedPath)) {
                    // and if requested site either corresponds to the exclude setting or if it's unimportant at all
                    if ($requestedSiteId == $siteId || $siteId < 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Create the cache file
     *
     * @return void
     */
    public function createCacheFile()
    {
        // Check for live preview parameters
        if (\Craft::$app->request->getQueryParam('x-craft-live-preview')) {
            return;
        }
        if (!$this->canCreateCacheFile() || http_response_code() !== 200) {
            return;
        }
        if (preg_match('/\badmin\b/i', $this->uri)) {
            return;
        }
        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $this->uri, 'siteId' => $this->siteId]);

        if ($cacheEntry) {
            $content = ob_get_contents();

            if ($this->settings->optimizeContent) {
                $content = implode("\n", array_map('trim', explode("\n", $content)));
            }

            $file = $this->getCacheFileName($cacheEntry->uid);
            // Get the size of the cache file
            if (!$fp = fopen($file, 'w+')) {
                \Craft::info('HTML Cache could not write cache file "' . $file . '"');
                return;
            }

            fwrite($fp, $content);
            fclose($fp);
            // Update the cacheSize field of the $cacheEntry instance
            $cacheEntry->cacheSize = filesize($file);
            $cacheEntry->save();
            $app = \Craft::$app;
            $this->clearVarnishUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

        } else {
            \Craft::info('HTML Cache could not find cache entry for siteId: "' . $this->siteId . '" and uri: "' . $this->uri . '"');
        }
    }

    /**
     * clear cache for given elementId
     *
     * @param integer $elementId
     * @return boolean
     */
    public function clearCacheFile($elementId)
    {
        // get all possible caches
        $elements = VarnishCacheElementRecord::findAll(['elementId' => $elementId]);
        // \craft::Dd($elements);
        $cacheIds = array_map(function ($el) {
            return $el->cacheId;
        }, $elements);
        $app = \Craft::$app;

        // get all possible caches
        $caches = VarnishCachesRecord::findAll(['id' => $cacheIds]);
        foreach ($caches as $cache) {
            $file = $this->getCacheFileName($cache->uid);
            $this->clearVarnishUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // delete caches for related entry
        VarnishCachesRecord::deleteAll(['id' => $cacheIds]);

        return true;
    }

    public function clearCacheUri($uri)
    {
        // get all possible caches
        $elements = VarnishCacheElementRecord::find()->all(['uri' => $uri]);
        // \craft::Dd($elements);
        $cacheIds = array_map(function ($el) {
            return $el->cacheId;
        }, $elements);
        \Craft::info('clearCacheUri Purge ids "' . implode(", ", $cacheIds) . '"');

        //$cachesUri = VarnishCachesRecord::findAll(['uri' => $uri]);
        //\Craft::info('clearCacheUri Purge ids "' . implode(", ",$cachesUri) . '"');
        $app = \Craft::$app;

        foreach ($cacheIds as $cache) {
            $file = $this->getCacheFileName($cache->uid);
            \Craft::info('clearCacheUri file "' . $file . '"');

            $this->clearVarnishUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

            \Craft::info('clearCacheUri Purge response: ' . $result . ' file: ' . $file);

            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // delete caches for related entry
        VarnishCachesRecord::deleteAll(['uri' => $cachesUri]);
        return null;
    }
    public function clearCacheCustom($uri, $url)
    {

        $cachesUri = VarnishCachesRecord::findAll(['uri' => $uri]);
        \Craft::info('clearCacheCustom ids allUris "' . implode(", ", $cachesUri) . '"');

        $app = \Craft::$app;

        foreach ($cachesUri as $cache) {
            $file = $this->getCacheFileName($cache);
            \Craft::info('clearCacheCustom file "' . $file . '"');

            $this->clearVarnishUrl($url);
            $this->clearVarnishUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // delete caches for related entry
        VarnishCachesRecord::deleteAll(['uri' => $cachesUri]);
        return null;
    }

    public function clearCacheCustomTimeout($uri, $url, $timeout)
    {
        $job = new ClearUriJob($uri, $url);
        QueueSingleton::getInstance($job)->push($job, 1, $timeout, 1800);
        return null;
    }

    /**
     * Clear all caches
     *
     * @return void
     */
    public function clearCacheFiles()
    {
        $cachesUri = VarnishCachesRecord::findAll([]);
        \Craft::info('clearCacheCustom ids allUris "' . implode(", ", $cachesUri) . '"');

        // Get the site's base URL
        $app = \Craft::$app;
        $baseUrl = $app->sites->getCurrentSite()->baseUrl;

        foreach ($cachesUri as $cache) {
            $file = $this->getCacheFileName($cache);
            \Craft::info('clearCacheCustom file "' . $file . '"');

            $this->clearVarnishUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $sitemaps = array();
        if (file_exists($settingsFile = $this->getDirectory() . 'settings.json')) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            foreach ($settings as $key => $sitemap) {
                $sitemaps[$key] = "{$baseUrl}{$sitemap[0]}";
            }
        } elseif (!empty($this->settings->sitemap)) {
            $settings = $this->settings->sitemap;
            foreach ($settings as $key => $sitemap) {
                $sitemaps[$key] = "{$baseUrl}{$sitemap[0]}";
            }
        } else {
            $sitemaps[0] = "{$baseUrl}sitemap.xml";
        }

        // Initialize an empty array to hold all URLs from all sitemaps.
        $allUrls = array();
        foreach ($sitemaps as $key => $sitemap) {
            $this->clearVarnishUrl($sitemap);
            try {
                $content = @file_get_contents($sitemap);
                if ($content === false) {
                    throw new \Exception('Failed to open sitemap');
                }
                $xml = simplexml_load_string($content);
                // Rest of your code...
            } catch (\Exception $e) {
                \Craft::error('Error opening Sitemap: ' . $sitemap . ': ' . $e->getMessage());
                continue;
            }
            // Load the sitemap XML and extract the URLs
            foreach ($xml as $urlElement) {
                // Convert the SimpleXMLElement to a string
                $url = (string) $urlElement->loc;
                if (!$this->isAbsoluteUrl($url)) {
                    // If the URL is relative, treat it as a path and check if it's excluded
                    if ($this->isPathSExcluded($url)) {
                        continue;
                    }
                } else {
                    $parsedUrl = parse_url($url);
                    $path = $parsedUrl['path'] ?? '';
                    // Skip this URL if it's excluded
                    if ($this->isPathSExcluded($path)) {
                        continue;
                    }
                }
                $allUrls[] = $url;
            }
        }
        foreach ($allUrls as $url) {
            $this->clearVarnishUrl($url);
        }

        FileHelper::clearDirectory($this->getDirectory());
        VarnishCachesRecord::deleteAll();
    }

    /**
     * Get the filename path
     *
     * @param string $uid
     * @return string
     */

    public function getCacheFileName($uid)
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
        // Fallback to default directory if no storage path defined
        if (defined('CRAFT_STORAGE_PATH')) {
            $basePath = CRAFT_STORAGE_PATH;
        } else {
            $basePath = CRAFT_BASE_PATH . DIRECTORY_SEPARATOR . 'storage';
        }

        return $basePath . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'varnishcache' . DIRECTORY_SEPARATOR;
    }

    /**
     * Check cache and return it if exists
     *
     * @param string $file
     * @return boolean
     */

    private function loadCache($file)
    {
        if (file_exists($settingsFile = $this->getDirectory() . 'settings.json')) {
            $settings = json_decode(file_get_contents($settingsFile), true);
        } elseif (!empty($this->settings->cacheDuration)) {
            $settings = ['cacheDuration' => ($this->settings->cacheDuration * 60)];
        } else {
            $settings = ['cacheDuration' => (10 * 60)];
        }
        if (time() - ($fmt = filemtime($file)) >= $settings['cacheDuration']) {
            unlink($file);
            return false;
        }
        \Craft::$app->response->data = file_get_contents($file);
        return true;
    }
    /**
     * Check is path excluded by provided path
     *
     * @param string $path
     * @return boolean
     */

    private function isPathSExcluded($path)
    {
        // determine the multi-site ID
        $requestedSiteId = \Craft::$app->getSites()->getCurrentSite()->id;

        // compare with excluded paths and sites from the settings
        if (!empty($this->settings->excludedUrlPaths)) {
            foreach ($this->settings->excludedUrlPaths as $exclude) {
                $excludePath = reset($exclude);
                $siteId = intval(next($exclude));

                // check if the path is one of those of the settings
                if ($path == $excludePath || preg_match('@' . $excludePath . '@', $path)) {
                    // and if requested site either corresponds to the exclude setting or if it's unimportant at all
                    if ($requestedSiteId == $siteId || $siteId < 0) {
                        \Craft::info('Excluded: ' . $excludePath . ', ' . $path . " siteId: " . $requestedSiteId);

                        return true;
                    }
                }
            }
        }

        return false;
    }
    /**
     * Check is url absolute
     *
     * @param string $url
     * @return boolean
     */

    private function isAbsoluteUrl($url)
    {
        $parsedUrl = parse_url($url);
        return isset($parsedUrl['scheme']);
    }

    /**
     * Preaload Cache from Sitemap from plugin settings
     *
     */

    public function preloadCacheFromSitemap()
    {
        $app = \Craft::$app;
        $sitemaps = array();

        // Get the site's base URL
        $baseUrl = $app->sites->getCurrentSite()->baseUrl;
        if (file_exists($settingsFile = $this->getDirectory() . 'settings.json')) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            foreach ($settings as $key => $sitemap) {
                $sitemaps[$key] = "{$baseUrl}{$sitemap[0]}";
            }
        } elseif (!empty($this->settings->sitemap)) {
            $settings = $this->settings->sitemap;
            foreach ($settings as $key => $sitemap) {
                $sitemaps[$key] = "{$baseUrl}{$sitemap[0]}";
            }
        } else {
            $sitemaps[0] = "{$baseUrl}sitemap.xml";
        }

        // Initialize an empty array to hold all URLs from all sitemaps.
        $allUrls = array();
        foreach ($sitemaps as $key => $sitemap) {
            $this->clearVarnishUrl($sitemap);
            try {
                $content = @file_get_contents($sitemap);
                if ($content === false) {
                    throw new \Exception('Failed to open sitemap');
                }
                $xml = simplexml_load_string($content);
                // Rest of your code...
            } catch (\Exception $e) {
                \Craft::error('Error opening Sitemap: ' . $sitemap . ': ' . $e->getMessage());
                continue;
            }
            // Load the sitemap XML and extract the URLs
            foreach ($xml as $urlElement) {
                // Convert the SimpleXMLElement to a string
                $url = (string) $urlElement->loc;
                if (!$this->isAbsoluteUrl($url)) {
                    // If the URL is relative, treat it as a path and check if it's excluded
                    if ($this->isPathSExcluded($url)) {
                        continue;
                    }
                } else {
                    $parsedUrl = parse_url($url);
                    $path = $parsedUrl['path'] ?? '';
                    // Skip this URL if it's excluded
                    if ($this->isPathSExcluded($path)) {
                        continue;
                    }
                }
                $allUrls[] = $url;
            }
        }

        // retrieve properties from the sitemap object
        \Craft::info('Preload urls ' . implode(', ', $allUrls));

        // Preload cache for Sitemap the URLs
        $this->preloadCache($allUrls);
    }

    /**
     * Preaload Cache from:
     * @param array $urls
     *
     */

    public function preloadCache(array $urls)
    {
        $delay = 0;
        $preloadInterval = $this->settings->interval;
        $queue = \Craft::$app->getQueue();
        $nextTask = QueueSingleton::getInstance();
        if($this->settings->runAll){
            foreach ($urls as $url) {
                $nextTask->push(new PreloadCacheJob([
                    'url' => $url,
                ]), 50, 0);
            }           
        }
        else{
            foreach ($urls as $url) {
                $nextTask->push(new PreloadCacheJob([
                    'url' => $url,
                ]), 50, $delay, ($preloadInterval - 1));
                $delay = $delay + $preloadInterval;
            }
        }
    }

    public function getCacheAnalytics()
    {
        // Get all cache records
        $cacheRecords = VarnishCachesRecord::find()->all();

        // Initialize variables for total size and total age
        $totalSize = 0;
        $totalAge = 0;
        $totalPreload = 0;
        $totalFirstLoad = 0;
        
        // Loop through all cache records
        foreach ($cacheRecords as $cacheRecord) {
            // Add the size of each cache file to the total size to KB
            $totalSize += $cacheRecord->cacheSize / (1024);

            // Calculate the age of each cache file and add it to the total age
            // Convert the difference between the current time and the creation time to minutes
            $age = (time() - strtotime($cacheRecord->createdAt)) / 60;
            $totalAge += $age;
            $preloadTime = $cacheRecord->preloadTime;
            $totalPreload += $preloadTime;
            $firstLoadTime = $cacheRecord->firstLoadTime;
            $totalFirstLoad += $firstLoadTime;
        }

        // Calculate the average age
        $averageAge = count($cacheRecords) > 0 ? $totalAge / count($cacheRecords) : 0;
        $preloadAverage = count($cacheRecords) > 0 ? $totalPreload / count($cacheRecords) : 0;
        $firstLoadAverage = count($cacheRecords) > 0 ? $totalFirstLoad / count($cacheRecords) : 0;
        // Return the total size and average age
        return [
            'totalSize' => $totalSize,
            'averageAge' => $averageAge,
            'numberCached' => count($cacheRecords),
            'preloadAverage' => $preloadAverage,
            'firstLoadAverage' => $firstLoadAverage,
            'cacheRecords' => $cacheRecords
        ];
    }

    public function clearVarnishUrl($url)
    {
        if (VarnishCache::getInstance()->getSettings()->enableVarnish == true) {
            $app = \Craft::$app;
            $baseUrl = $app->sites->getCurrentSite()->baseUrl;
            $parsedUrl = parse_url($url);

            $purgeurl = $parsedUrl['path'] ?? '';
            $purgeurl = ltrim($purgeurl, '/');
            $varnishurl = $baseUrl . $purgeurl;
            $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
            if (VarnishCache::getInstance()->getSettings()->varnishBan == true) {
                $varnishcommand = 'BAN';
            } else {
                $varnishcommand = 'PURGE';
            }

            $curl = curl_init($varnishurl);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array($varnishhost));
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
            curl_setopt($curl, CURLOPT_ENCODING, $varnishhost);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            if (curl_exec($curl) === false) {
                \Craft::error('Purge cURL Error: ' . var_dump(curl_error($curl)) . ', purgeUrl: ' . $purgeurl . ', varnishUrl' . $varnishurl);
            }
            \Craft::info('Purge response purgeUrl: ' . $purgeurl . ', varnishUrl: ' . $varnishurl);
            curl_close($curl);
        }
    }
}
