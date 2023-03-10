<?php
/**
 * Varnish Cache Helper plugin for Craft CMS 3.x
 *
 * Varnish Cache Helper Plugin with http & htttps
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2022 CoolTRONIC.pl sp. z o.o.
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
