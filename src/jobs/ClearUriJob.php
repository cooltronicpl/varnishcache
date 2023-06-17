<?php

namespace cooltronicpl\varnishcache\jobs;

/**
 * Varnish Cache with Preload (Preheat) to static HTML Helper plugin for Craft CMS 3. & 4.x
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
use cooltronicpl\varnishcache\records\VarnishCachesRecord;

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
		\Craft::info('clearCustomUrlUriTimeout ids allUris "' . implode(", ",$cachesUri) . '"');
        $app = \Craft::$app;
        $baseUrl = $app->sites->getCurrentSite()->baseUrl;
		foreach ($cachesUri as $cache) {
			$file = $this->getCacheFileName($cache);
			\Craft::info('clearCustomUrlUriTimeout file "' . $file . '"');

			VarnishCacheService::clearVarnishUrl($baseUrl.$this->uri);
		}

		VarnishCacheService::clearVarnishUrl($this->url);

		// delete caches for related entry
		VarnishCachesRecord::deleteAll(['uri' => $cachesUri]);
    }
    
    protected function defaultDescription(): string
    {
        return \Craft::t('app', 'Clear URL');
    }

    public function isRun(): bool{
        return $this->hasRun;
    }

}
