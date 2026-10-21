<?php
/**
 * Dot Collective - DC_Catalog
 *
 * @category   DC
 * @package    DC_Catalog
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$installer->run("
ALTER TABLE `{$this->getTable('catalog_attribute_page')}`
    ADD COLUMN `use_default_description`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `description`,
    ADD COLUMN `use_default_page_title`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `page_title`,
    ADD COLUMN `use_default_meta_keywords`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `meta_keywords`,
    ADD COLUMN `use_default_meta_description`        TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `meta_description`,
    ADD COLUMN `use_default_description_in_page`     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `description_in_page`;
");

$installer->endSetup();
