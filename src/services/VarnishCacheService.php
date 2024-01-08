<?php

/**
 * Varnish Cache Purge plugin for Craft CMS 4.x
 *
 * Varnish Cache Purge Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright ( c ) 2024 CoolTRONIC.pl sp. z o.o.
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
    }

    public function checkForCacheFile()
    {
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

        switch (true) {
            case $this->settings->enableGeneral && $this->settings->forceOn == true:
                break;

            case $app->config->general->devMode === true && $this->settings->forceOn == false:
                return false;

            case $this->settings->enableGeneral == false:
                return false;

            case !$app->getIsSystemOn() && $this->settings->forceOn == false:
                return false;

            case $app->request->getIsCpRequest():
                return false;

            case $app->request->getIsActionRequest():
                return false;

            case $app->request->getIsLivePreview():
                return false;

            case !$app->request->getIsGet():
                return false;

            case $app->request->getIsAjax():
                return false;

            case $this->isElementApiRoute():
                return false;

            case $this->isPathExcluded():
                return false;
        }

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
        $requestedPath = \Craft::$app->request->getFullPath();
        $requestedSiteId = \Craft::$app->getSites()->getCurrentSite()->id;
        if (!empty($this->settings->excludedUrlPaths)) {
            foreach ($this->settings->excludedUrlPaths as $exclude) {
                $path = reset($exclude);
                $siteId = intval(next($exclude));
                if ($requestedPath == $path || preg_match('@' . $path . '@', $requestedPath)) {
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
        $app = \Craft::$app;

        switch (true) {
            case ($app->request->getQueryParam('x-craft-live-preview')):
                \Craft::info('HTML Cache Preview x-craft-live-preview: "' . $this->uri . '"');
                return;
            case (!$this->canCreateCacheFile() || http_response_code() !== 200):
                \Craft::info('HTML Cache Preview canCreateCacheFile & http_response_code!=200: "' . $this->uri . '"');
                return;
            case (preg_match('/\badmin\b/i', $this->uri)):
                \Craft::info('HTML Cache Preview /admin/"' . $this->uri . '"');
                return;
            case ($this->isPathExcluded()):
                \Craft::info('HTML Cache Preview isPathExcluded: "' . $this->uri . '"');
                return;
            case ($this->isElementApiRoute()):
                \Craft::info('HTML Cache Preview isElementApiRoute: "' . $this->uri . '"');
                return;
            case $app->request->getIsCpRequest():
                \Craft::info('HTML Cache Preview $app->request->getIsCpRequest(): "' . $this->uri . '"');
                return;
            case $app->request->getIsActionRequest():
                \Craft::info('HTML Cache Preview $app->request->getIsActionRequest(): "' . $this->uri . '"');
                return;
            case $app->request->getIsLivePreview():
                \Craft::info('HTML Cache Preview $app->request->getIsLivePreview(): "' . $this->uri . '"');
                return;
            case !$app->request->getIsGet():
                \Craft::info('HTML Cache Preview !$app->request->getIsGet(): "' . $this->uri . '"');
                return;
            case $app->request->getIsAjax():
                \Craft::info('HTML Cache Preview $app->request->getIsAjax(): "' . $this->uri . '"');
                return;
        }

        $cacheEntry = VarnishCachesRecord::findOne(['uri' => $this->uri, 'siteId' => $this->siteId]);

        if ($cacheEntry) {
            $content = ob_get_contents();
            if ($this->settings->optimizeContent) {
                $content = str_replace(array("\r", "\n","           ", "      ","      ","    ","  ", "    "), ' ', $content);
            }
            $file = $this->getCacheFileName($cacheEntry->uid);
            if (!$fp = fopen($file, 'w+')) {
                \Craft::error('HTML Cache could not write cache file "' . $file . '"');
                return;
            }
            fwrite($fp, $content);
            fclose($fp);
            $cacheEntry->cacheSize = filesize($file);
            $cacheEntry->save();
            $app = \Craft::$app;
            $this->clearCacheUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);
        } else
        {
            \Craft::error('HTML Cache could not find cache entry for siteId: "' . $this->siteId . '" and uri: "' . $this->uri . '"');
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
        $elements = VarnishCacheElementRecord::findAll(['elementId' => $elementId]);
        $cacheIds = array_map(function ($el) {
            return $el->cacheId;
        }, $elements);
        $app = \Craft::$app;
        $caches = VarnishCachesRecord::findAll(['id' => $cacheIds]);
        foreach ($caches as $cache) {
            $file = $this->getCacheFileName($cache->uid);
            $this->clearCacheUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

            if (file_exists($file)) {
                @unlink($file);
            }
        }
        VarnishCachesRecord::deleteAll(['id' => $cacheIds]);

        return true;
    }

    public function clearCacheUri($uri)
    {
        $elements = VarnishCacheElementRecord::find()->all(['uri' => $uri]);
        $cacheIds = array_map(function ($el) {
            return $el->cacheId;
        }, $elements);
        \Craft::info('clearCacheUri Purge ids "' . implode(", ", $cacheIds) . '"');
        $app = \Craft::$app;

        foreach ($cacheIds as $cache) {
            $file = $this->getCacheFileName($cache->uid);
            \Craft::info('clearCacheUri file "' . $file . '"');

            $this->clearCacheUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

            \Craft::info('clearCacheUri Purge response: ' . $result . ' file: ' . $file);

            if (file_exists($file)) {
                @unlink($file);
            }
        }

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

            $this->clearCacheUrl($url);
            $this->clearCacheUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

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

        $app = \Craft::$app;
        $baseUrl = $app->sites->getCurrentSite()->baseUrl;

        foreach ($cachesUri as $cache) {
            $file = $this->getCacheFileName($cache);
            \Craft::info('clearCacheCustom file "' . $file . '"');

            $this->clearCacheUrl($app->sites->getCurrentSite()->baseUrl . $this->uri);

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

        $allUrls = array();
        foreach ($sitemaps as $key => $sitemap) {
            $this->clearCacheUrl($sitemap);
            try {
                $content = @file_get_contents($sitemap);
                if ($content === false) {
                    throw new \Exception('Failed to open sitemap');
                }
                $xml = simplexml_load_string($content);
            } catch (\Exception $e) {
                \Craft::error('Error opening Sitemap: ' . $sitemap . ': ' . $e->getMessage());
                continue;
            }
            foreach ($xml as $urlElement) {
                $url = (string) $urlElement->loc;
                if (!$this->isAbsoluteUrl($url)) {
                    if ($this->isPathSExcluded($url)) {
                        continue;
                    }
                } else {
                    $parsedUrl = parse_url($url);
                    $path = $parsedUrl['path'] ?? '';
                    if ($this->isPathSExcluded($path)) {
                        continue;
                    }
                }
                $allUrls[] = $url;
            }
        }
        foreach ($allUrls as $url) {
            $this->clearCacheUrl($url);
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
        $requestedSiteId = \Craft::$app->getSites()->getCurrentSite()->id;
        if (!empty($this->settings->excludedUrlPaths)) {
            foreach ($this->settings->excludedUrlPaths as $exclude) {
                $excludePath = reset($exclude);
                $siteId = intval(next($exclude));
                if ($path == $excludePath || preg_match('@' . $excludePath . '@', $path)) {
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

        $allUrls = array();
        foreach ($sitemaps as $key => $sitemap) {
            $this->clearCacheUrl($sitemap);
            try {
                $content = @file_get_contents($sitemap);
                if ($content === false) {
                    throw new \Exception('Failed to open sitemap');
                }
                $xml = simplexml_load_string($content);
            } catch (\Exception $e) {
                \Craft::error('Error opening Sitemap: ' . $sitemap . ': ' . $e->getMessage());
                continue;
            }
            foreach ($xml as $urlElement) {
                $url = (string) $urlElement->loc;
                if (!$this->isAbsoluteUrl($url)) {
                    if ($this->isPathSExcluded($url)) {
                        continue;
                    }
                } else {
                    $parsedUrl = parse_url($url);
                    $path = $parsedUrl['path'] ?? '';
                    if ($this->isPathSExcluded($path)) {
                        continue;
                    }
                }
                $allUrls[] = $url;
            }
        }
        \Craft::info('Preload urls ' . implode(', ', $allUrls));
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
        $nextTask = QueueSingleton::getInstance();
        if ($this->settings->runAll) {
            foreach ($urls as $url) {
                $nextTask->push(new PreloadCacheJob([
                    'url' => $url,
                ]), 50, 0);
            }
        } else {
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
        $cacheRecords = VarnishCachesRecord::find()->all();
        $totalSize = 0;
        $totalAge = 0;
        $totalPreload = 0;
        $totalFirstLoad = 0;
        foreach ($cacheRecords as $cacheRecord) {
            $totalSize += $cacheRecord->cacheSize / (1024);
            $age = (time() - strtotime($cacheRecord->createdAt)) / 60;
            $totalAge += $age;
            $preloadTime = $cacheRecord->preloadTime;
            $totalPreload += $preloadTime;
            $firstLoadTime = $cacheRecord->firstLoadTime;
            $totalFirstLoad += $firstLoadTime;
        }
        $averageAge = count($cacheRecords) > 0 ? $totalAge / count($cacheRecords) : 0;
        $preloadAverage = count($cacheRecords) > 0 ? $totalPreload / count($cacheRecords) : 0;
        $firstLoadAverage = count($cacheRecords) > 0 ? $totalFirstLoad / count($cacheRecords) : 0;
        return [
            'totalSize' => $totalSize,
            'averageAge' => $averageAge,
            'numberCached' => count($cacheRecords),
            'preloadAverage' => $preloadAverage,
            'firstLoadAverage' => $firstLoadAverage,
            'cacheRecords' => $cacheRecords,
        ];
    }

    public function clearCacheUrl($url)
    {
        if (VarnishCache::getInstance()->getSettings()->enableVarnish == true) {

            $app = \Craft::$app;
            $baseUrl = $app->sites->getCurrentSite()->baseUrl;
            $parsedUrl = parse_url($url);
            $purgeurl = $parsedUrl['path'] ?? '';
            $purgeurl = ltrim($purgeurl, '/');
            if (VarnishCache::getInstance()->getSettings()->customPurgeMethod == true) {
                $purgemethod = "urlmode";
            } else {
                $purgemethod = "default";
            }
            $parsedUrl = parse_url($baseUrl);
            $domainUrl = parse_url($baseUrl);
            $domain = isset($domainUrl['host']) ? $domainUrl['host'] : '';
            if (!empty(VarnishCache::getInstance()->getSettings()->customPurgeUrl)) {
                $varnishurl = VarnishCache::getInstance()->getSettings()->customPurgeUrl . $purgeurl;
                $domainUrl = parse_url($varnishurl);
                $domain = isset($domainUrl['host']) ? $domainUrl['host'] : '';
            } elseif (VarnishCache::getInstance()->getSettings()->enableCloudflare == true) {
                $varnishurl = 'http://localhost/' . $purgeurl;
            } else {
                $varnishurl = $baseUrl . $purgeurl;
            }

            $varnishhost = 'Host: ' . $domain;
            if (VarnishCache::getInstance()->getSettings()->varnishBan == true) {
                $varnishcommand = 'BAN';
            } else {
                $varnishcommand = 'PURGE';
            }
            $curl = curl_init($varnishurl);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'host' => $varnishhost,
                'X-Purge-Method' => $purgemethod,
                'url' => $url,
            ));
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
            curl_setopt($curl, CURLOPT_ENCODING, '');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            $result = curl_exec($curl);
            if ($result === false) {
                \Craft::error('Purge Varnish Error: ' . var_dump(curl_error($curl)) . ', purgeUrl: ' . $varnishurl . ', inputUrl: ' . $url);
            } else {
                $info = curl_getinfo($curl);
                $code = $info['http_code'];
                \Craft::info('Purge Varnish response purgeUrl: ' . $purgeurl . ', varnishUrl: ' . $varnishurl . ', inputUrl: ' . $url . ', http_code: ' . $code);
            }
            curl_close($curl);
        }
        if (VarnishCache::getInstance()->getSettings()->enableCloudflare == true) {
            $zoneId = VarnishCache::getInstance()->getSettings()->cloudflareZone;
            $endpoint = "https://api.cloudflare.com/client/v4/zones/$zoneId";
            $apiKey = VarnishCache::getInstance()->getSettings()->cloudflareApi;
            $email = VarnishCache::getInstance()->getSettings()->cloudflareEmail;
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "X-Auth-Email: $email",
                "X-Auth-Key: $apiKey",
                "Content-Type: application/json",
            ));
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                \Craft::error("Cloudflare Curl error: " . curl_error($ch));
            } else {
                $data = json_decode($response, true);
                if ($data["success"]) {
                    $purgeEndpoint = "$endpoint/purge_cache";
                    $purgeParams = array(
                        "files" => array($url),
                    );
                    $purgeJson = json_encode($purgeParams);
                    $ch = curl_init($purgeEndpoint);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "X-Auth-Email: $email",
                        "X-Auth-Key: $apiKey",
                        "Content-Type: application/json",
                    ));
                    curl_setopt($ch, CURLOPT_ENCODING, '');
                    curl_setopt($ch, CURLOPT_URL, $purgeEndpoint);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $purgeJson);
                    $purgeResponse = curl_exec($ch);
                    $purgeData = json_decode($purgeResponse, true);
                    if ($purgeData["success"]) {
                        \Craft::info("Cloudflare cache cleared for $url, response: " . $purgeResponse);
                    } else {
                        \Craft::error("Cloudflare cache purge failed: " . $purgeData["errors"][0]["message"] . "URL: " . $url);
                    }
                } else {
                    \Craft::error("Cloudflare API request failed: " . $data["errors"][0]["message"]);
                }
            }
            curl_close($ch);
        }

    }

}
