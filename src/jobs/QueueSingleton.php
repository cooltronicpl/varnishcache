<?php
namespace cooltronicpl\varnishcache\jobs;

/**
 * Varnish Cache & Preload to static HTML plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache & Preload to static HTML Plugin with http & htttps
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
        // Hide the constructor so that no one can create new instances of the queue
    }
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new Queue;
        }
        return self::$instance;
    }

}
