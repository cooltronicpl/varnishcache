<?php
/**
 * CDN Cache & Preload to static HTML Helper plugin for Craft CMS 4.x
 *
 * CDN Cache & Preload to static HTML Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2024 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 */

namespace cooltronicpl\varnishcache\models;

use craft\base\Model;

class Settings extends Model
{
    public $enableGeneral = 1;
    public $forceOn = 0;
    public $optimizeContent = 0;
    public $cacheDuration = 600;
    public $purgeCache = 0;
    public $excludedUrlPaths = [];
    public $preloadSitemap = 0;
    public $sitemap = [];
    public $resetQueue = 1;
    public $enableVarnish = 1;
    public $varnishBan = 0;
    public $interval = 5;
    public $runAll = 0;
    public $enableCloudflare = 0;
    public $cloudflareZone = "";
    public $cloudflareApi = "";
    public $cloudflareEmail = "";
    public $averageAge;
    public $totalSize;
    public $numberCached;
    public $preloadAverage;
    public $firstLoadAverage;
    public $cacheRecords;
    public $customPurgeUrl = null;
    public $timeout = null;
    public $customPurgeMethod = null;

    public function rules(): array
    {
        return [
            [['enableGeneral'], 'boolean'],
            [['optimizeContent'], 'boolean'],
            [['forceOn'], 'boolean'],
            [['purgeCache'], 'boolean'],
            [['cacheDuration'], 'integer'],
            [['cacheDuration'], 'required'],
            [['preloadSitemap'], 'boolean'],
            [['resetQueue'], 'boolean'],
            [['enableVarnish'], 'boolean'],
            [['varnishBan'], 'boolean'],
            [['interval'], 'integer'],
            [['preloadAverage'], 'integer'],
            [['firstLoadAverage'], 'integer'],
            [['runAll'], 'boolean'],
            [['enableCloudflare'], 'boolean'],
            [['cloudflareZone'], 'string'],
            [['cloudflareApi'], 'string'],
            [['cloudflareEmail'], 'string'],
            [['timeout'], 'integer'],
            [['enableVarnish'], 'boolean'],
            [['customPurgeMethod'], 'boolean'],
        ];
    }
}
