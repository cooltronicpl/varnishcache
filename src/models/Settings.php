<?php
/**
 * Varnish Cache Helper plugin for Craft CMS 3.x & 4.x
 *
 * Varnish Cache Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2022 CoolTRONIC.pl sp. z o.o.
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
    public $sitemap = ["sitemap.xml"];
    public $resetQueue = 1;
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
        ];
    }
}
