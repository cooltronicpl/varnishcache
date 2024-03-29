<?php

namespace cooltronicpl\varnishcache\jobs;

/**
 * CDN Cache & Preload to static HTML Helper plugin for Craft CMS 3. & 4.x
 *
 * CDN Cache & Preload to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2024 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

use cooltronicpl\varnishcache\records\VarnishCachesRecord;
use cooltronicpl\varnishcache\services\VarnishCacheService;

class ClearUriJob extends \craft\queue\BaseJob

{

    private $url;
    private $uri;

    public function __construct($url, $uri)
    {
        $this->url = $url;
        $this->uri = $uri;
    }

    public function execute($queue): void
    {
        $cachesUri = VarnishCachesRecord::findAll(['uri' => $this->uri]);
        \Craft::info('clearCustomUrlUriTimeout ids allUris "' . implode(", ", $cachesUri) . '"');
        $app = \Craft::$app;
        $baseUrl = $app->sites->getCurrentSite()->baseUrl;
        foreach ($cachesUri as $cache) {
            $file = $this->getCacheFileName($cache);
            \Craft::info('clearCustomUrlUriTimeout file "' . $file . '"');
            VarnishCacheService::clearCacheUrl($baseUrl . $this->uri);
        }
        VarnishCacheService::clearCacheUrl($this->url);
        VarnishCachesRecord::deleteAll(['uri' => $cachesUri]);
    }

    protected function defaultDescription(): string
    {
        return \Craft::t('app', 'Clear URL');
    }

    public function isRun(): bool
    {
        return $this->hasRun;
    }

}
