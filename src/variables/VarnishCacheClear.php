<?php
/**
 * CDN Cache & Preload to static HTML Helper plugin for Craft CMS 4.x
 *
 * CDN Cache & Preload to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

namespace cooltronicpl\varnishcache\variables;

use cooltronicpl\varnishcache\services\VarnishCacheService;

/**
 * @author    CoolTRONIC.pl sp. z o.o. <github@cooltronic.pl>
 * @author    Pawel Potacki
 * @since     1.0.0
 */
use Craft;

class VarnishCacheClear
{

    public function clearCustomUrlUriTimeout(string $uri, string $url, int $timeout)
    {
        \Craft::debug('clearCustomUrlUriTimeout single uri: ' . $uri . " , URL: " . $url);

        $v = new VarnishCacheService;
        $v->clearCacheCustomTimeout($uri, $url, $timeout);
        \Craft::debug('clearCustomUrlUriTimeout single URL ended');
        return "";

    }

}
