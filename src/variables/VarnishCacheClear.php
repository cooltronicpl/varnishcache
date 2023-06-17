<?php
/**
 * Varnish Cache with Preload (Preheat) to static HTML Helper plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache with Preload (Preheat) to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

namespace cooltronicpl\varnishcache\variables;

use Craft;

/**
 * @author    CoolTRONIC.pl sp. z o.o. <github@cooltronic.pl>
 * @author    Pawel Potacki
 * @since     1.0.0
 */
use cooltronicpl\varnishcache\services\VarnishCacheService;

class VarnishCacheClear
{
	
    public function clearCustomUrlUriTimeout(string $uri, string $url, int $timeout){
        \Craft::info('clearCustomUrlUriTimeout single uri: '.$uri." , URL: ".$url);

        $v = new VarnishCacheService;
        $v->clearCacheCustomTimeout($uri,$url,$timeout);
        \Craft::info('clearCustomUrlUriTimeout single URL ended');
        return "";

    }

}
