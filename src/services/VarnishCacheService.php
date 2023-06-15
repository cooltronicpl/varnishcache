<?php

/**
 * Varnish Cache Purge plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache Purge Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2022 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

namespace cooltronicpl\varnishcache\services;

use craft\base\Component;
use craft\helpers\FileHelper;
use cooltronicpl\varnishcache\VarnishCache;
use cooltronicpl\varnishcache\jobs\QueueSingleton;
use cooltronicpl\varnishcache\jobs\ClearUriJob;
use cooltronicpl\varnishcache\records\VarnishCachesRecord;
use cooltronicpl\varnishcache\records\VarnishCacheElementRecord;
use craft\test\mockclasses\ToString;

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
        if (!$this->canCreateCacheFile()) {
            return;
        }

        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $this->uri, 'siteId' => $this->siteId]);

        if ($cacheEntry) {
            $file = $this->getCacheFileName($cacheEntry->uid);

            if (file_exists($file)) {
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

            if (!$fp = fopen($file, 'w+')) {
                \Craft::info('HTML Cache could not write cache file "' . $file . '"');
                return;
            }

            fwrite($fp, $content);
            fclose($fp);

            $purgeurl = $this->uri;
            $varnishurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://127.0.0.1" . $purgeurl;
            $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
            $varnishcommand = "PURGE";

            $curl = curl_init($varnishurl);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
            curl_setopt($curl, CURLOPT_ENCODING, $varnishhost);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
            $result = curl_exec($curl);
            curl_close($curl);

            \Craft::info('Varnish Cache Purge response "' . $result . '"');
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

        // get all possible caches
        $caches = VarnishCachesRecord::findAll(['id' => $cacheIds]);
        foreach ($caches as $cache) {
            $file = $this->getCacheFileName($cache->uid);
            $purgeurl = $this->uri;
            $varnishurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://127.0.0.1" . $purgeurl;
            $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
            $varnishcommand = "PURGE";

            $curl = curl_init($varnishurl);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
            curl_setopt($curl, CURLOPT_ENCODING, $varnishhost);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
            $result = curl_exec($curl);
            curl_close($curl);
            \Craft::info('Varnish Cache Purge response "' . $result . '"');

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
        \Craft::info('Varnish clearCacheUri Purge ids "' . implode(", ", $cacheIds) . '"');

        //$cachesUri = VarnishCachesRecord::findAll(['uri' => $uri]);
        //\Craft::info('Varnish clearCacheUri Purge ids "' . implode(", ",$cachesUri) . '"');

        foreach ($cacheIds as $cache) {
            $file = $this->getCacheFileName($cache->uid);
            \Craft::info('Varnish clearCacheUri file "' . $file . '"');

            $purgeurl = $this->uri;
            $varnishurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://127.0.0.1" . $purgeurl;
            $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
            $varnishcommand = "PURGE";

            $curl = curl_init($varnishurl);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
            curl_setopt($curl, CURLOPT_ENCODING, $varnishhost);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
            $result = curl_exec($curl);
            curl_close($curl);
            \Craft::info('Varnish clearCacheUri Purge response: ' . $result . ' file: ' . $file);

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
        \Craft::info('Varnish clearCacheCustom ids allUris "' . implode(", ", $cachesUri) . '"');


        foreach ($cachesUri as $cache) {
            $file = $this->getCacheFileName($cache);
            \Craft::info('Varnish clearCacheCustom file "' . $file . '"');

            $purgeurl = $url;
            $varnishurl = $purgeurl;
            $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
            $varnishcommand = "PURGE";

            $curl = curl_init($varnishurl);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
            curl_setopt($curl, CURLOPT_ENCODING, $varnishhost);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
            $result = curl_exec($curl);
            curl_close($curl);
            \Craft::info('Varnish clearCacheCustom Purge response: ' . $result . ' file: ' . $file);

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
        $purgeurl = $this->uri;
        $varnishurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://127.0.0.1" . $purgeurl;
        $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
        $varnishcommand = "PURGE";

        $curl = curl_init($varnishurl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
        curl_setopt($curl, CURLOPT_ENCODING, $varnishhost);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        $result = curl_exec($curl);
        curl_close($curl);
        \Craft::info('Varnish Cache Purge response "' . $result . '"');

        FileHelper::clearDirectory($this->getDirectory());
        VarnishCachesRecord::deleteAll();
    }

    /**
     * Get the filename path
     *
     * @param string $uid
     * @return string
     */
    private function getCacheFileName($uid)
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
        // compare with excluded paths from the settings
        if (!empty($this->settings->excludedUrlPaths)) {
            foreach ($this->settings->excludedUrlPaths as $exclude) {
                $excludePath = reset($exclude);

                // check if the path is one of those of the settings
                if ($path == $excludePath || preg_match('@' . $excludePath . '@', $path)) {
                    return true;
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
                    $sitemaps[$key] = "{$baseUrl}/{$sitemap[0]}";
            }
        } elseif (!empty($this->settings->sitemap)) {
            $settings = $this->settings->sitemap;
            foreach ($settings as $key => $sitemap) {
                \Craft::info('base: "' .  $baseUrl . '"');
                \Craft::info('sitemap: "' .  $sitemap[0] . '"');
                $sitemaps[$key] = "{$baseUrl}/{$sitemap[0]}";
            }
        } else {
            $sitemaps[] = ["{$baseUrl}/sitemap.xml"];
        }

        // Initialize an empty array to hold all URLs from all sitemaps.
        $allUrls = array();
        foreach ($sitemaps as $sitemap) {
            $content = file_get_contents($sitemap);
            $xml = simplexml_load_string($content);
            // Load the sitemap XML and extract the URLs
            foreach ($xml as $urlElement) {
                // Convert the SimpleXMLElement to a string
                $url = (string) $urlElement->loc;
                if (!$this->isAbsoluteUrl($url)) {
                    // If the URL is relative, treat it as a path and check if it's excluded
                    if ($this->isPathExcluded($url)) {
                        continue;
                    }
                } else {
                    $parsedUrl = parse_url($url);
                    $path = $parsedUrl['path'] ?? '';
                    // Skip this URL if it's excluded
                    if ($this->isPathExcluded($path)) {
                        continue;
                    }
                }
                $allUrls[] = $url;
            }
        }

        // retrieve properties from the sitemap object
        \Craft::info('Vanish Cache Preload urls '.implode(", ",$sitemaps));

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
        $app = \Craft::$app;
        $this->createCacheFile();

        // Create a new cURL handle
        $curl = curl_init();

        // Set options for the cURL request
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);

        // Set the "Host" header to the current site's domain
        $httpHost = $app->request->getServerName();
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Host: {$httpHost}"]);

        // Iterate over the list of URLs and preload their cache
        foreach ($urls as $url) {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_exec($curl);
        }

        // Close the cURL handle
        curl_close($curl);
        \Craft::info('Vanish Cache Preload ended"');

    }
}