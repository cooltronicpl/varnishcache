<?php
namespace cooltronicpl\varnishcache\jobs;

/**
 * Varnish Cache Helper plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2022 CoolTRONIC.pl sp. z o.o.
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