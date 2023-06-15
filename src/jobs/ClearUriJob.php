<?php

namespace cooltronicpl\varnishcache\jobs;

/**
 * Varnish Cache Helper plugin for Craft CMS 3. & 4.x
 *
 * Varnish Cache Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2022 CoolTRONIC.pl sp. z o.o.
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
		\Craft::info('Varnish clearCustomUrlUriTimeout ids allUris "' . implode(", ",$cachesUri) . '"');

		foreach ($cachesUri as $cache) {
			$file = $this->getCacheFileName($cache);
			\Craft::info('Varnish clearCustomUrlUriTimeout file "' . $file . '"');

			$purgeurl = $this->url;
			$varnishurl = $purgeurl;
			$varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
			$varnishcommand = "PURGE";

			$curl = curl_init($varnishurl);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $varnishcommand);
			curl_setopt($curl, CURLOPT_ENCODING, $varnishhost);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
			$result = curl_exec($curl);
			curl_close($curl);
			\Craft::info('Varnish clearCustomUrlUriTimeout Purge response: ' . $result . ' file: ' . $file);

			if (file_exists($file)) {
				@unlink($file);
			}
		}


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
