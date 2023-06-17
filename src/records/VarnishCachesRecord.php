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

namespace cooltronicpl\varnishcache\records;

use craft\db\ActiveRecord;

/**
 * Element record class.
 *
 * @property int $id ID
 * @property int $siteId
 * @property string $uri

 */
class VarnishCachesRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%varnishcache_caches}}';
    }
}
