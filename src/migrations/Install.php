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

namespace cooltronicpl\varnishcache\migrations;

use Craft;
use craft\db\Migration;

/**
 * Installation Migration
 *
 * @author    CoolTRONIC.pl sp. z o.o. <github@cooltronic.pl>
 * @package   Install
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropTableIfExists('{{%varnishcache_caches}}');
        $this->dropTableIfExists('{{%varnishcache_elements}}');
        $columns = [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'uri' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'createdAt' => $this->dateTime()->notNull(),
            'cacheSize' =>  $this->float(),
            'preloadTime' => $this->float(),
            'firstLoadTime' => $this->float(),
            'uid' => $this->uid()
        ];
        $this->createTable('{{%varnishcache_caches}}', $columns);
        $this->createIndex('varnishcache_caches_uri_siteId_idx', '{{%varnishcache_caches}}', ['uri', 'siteId'], true);
        $this->addForeignKey('varnishcache_caches_siteId_fk', '{{%varnishcache_caches}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE');
        $columns = [
            'elementId' => $this->integer()->notNull(),
            'cacheId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'createdAt' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ];
        $this->createTable('{{%varnishcache_elements}}', $columns);
        $this->createIndex('varnishcache_caches_elementId_cacheId_idx', '{{%varnishcache_elements}}', ['elementId', 'cacheId'], true);
        $this->addForeignKey('varnishcache_elements_elementId_fk', '{{%varnishcache_elements}}', ['elementId'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey('varnishcache_elements_cacheId_fk', '{{%varnishcache_elements}}', ['cacheId'], '{{%varnishcache_caches}}', ['id'], 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%varnishcache_elements}}');
        $this->dropTableIfExists('{{%varnishcache_caches}}');
    }
}
