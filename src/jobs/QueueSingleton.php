<?php
namespace cooltronicpl\varnishcache\jobs;

/**
 * CDN Cache & Preload to static HTML Helper plugin for Craft CMS 4.x
 *
 * CDN Cache & Preload to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */
use craft\helpers\Queue;

class QueueSingleton
{
    private static $instance;

    private function __construct()
    {

    }
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new Queue;
        }
        return self::$instance;
    }

}
