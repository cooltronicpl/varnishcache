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

namespace cooltronicpl\varnishcache\records;

use craft\db\ActiveRecord;

/**
 * Element record class.
 *
 * @property int $cacheId ID
 * @property int $elementId
 */
class VarnishCacheElementRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%varnishcache_elements}}';
    }
}
