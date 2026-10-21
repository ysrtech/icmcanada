<?php
/**
 * Dot Collective - Magento Output 2009
 * Find more about Attribute Info Pages:
 * http://dot.collective.ro/magento-output/magento-shop-by-manufacturer-brand-character-attribute-info-pages/
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   DC
 * @package    DC_Catalog
 * @copyright  Copyright (c) 2009 Dot Collective SRL http://dot.collective.ro
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DC_Catalog_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Parsed mapping cache: store_id => array( attribute_code => url_prefix )
     * @var array
     */
    protected $_urlLabelMap = array();

    /**
     * Parse the store-scoped config textarea into a flat array.
     * Format is one entry per line: attribute_code=url_prefix
     * Lines that are blank or malformed are ignored.
     *
     * @param  int|null $storeId  null = current store
     * @return array  [ attribute_code => url_prefix, ... ]
     */
    protected function _getUrlLabelMap($storeId = null)
    {
        if ($storeId === null) {
            $storeId = (int)Mage::app()->getStore()->getId();
        }
        $storeId = (int)$storeId;

        if (!isset($this->_urlLabelMap[$storeId])) {
            $raw = (string)Mage::getStoreConfig('dc_catalog/url_labels/mapping', $storeId);
            $map = array();
            foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '=') === false) {
                    continue;
                }
                list($code, $label) = explode('=', $line, 2);
                $code  = strtolower(trim($code));
                $label = strtolower(trim($label));
                if ($code !== '' && $label !== '') {
                    $map[$code] = $label;
                }
            }
            $this->_urlLabelMap[$storeId] = $map;
        }

        return $this->_urlLabelMap[$storeId];
    }

    /**
     * Return the URL prefix for an attribute in the given store.
     * Falls back to the attribute_code itself if no mapping is configured.
     *
     * Example: getAttributeUrlPrefix('brand', 2) => 'merk'
     *          getAttributeUrlPrefix('brand', 1) => 'brand'  (no mapping for EN store)
     *
     * @param  string   $attributeCode
     * @param  int|null $storeId  null = current store
     * @return string
     */
    public function getAttributeUrlPrefix($attributeCode, $storeId = null)
    {
        $map = $this->_getUrlLabelMap($storeId);
        $attributeCode = strtolower($attributeCode);
        return isset($map[$attributeCode]) ? $map[$attributeCode] : $attributeCode;
    }

    /**
     * Reverse lookup: given a URL prefix, return the real attribute_code.
     * First checks the current store mapping, then falls back to searching
     * ALL store mappings combined. This ensures the router works even when
     * the store hasn't been fully resolved yet (early dispatch).
     *
     * Falls back to returning $prefix unchanged if no mapping matches.
     *
     * Example: getAttributeCodeFromPrefix('merk', 2) => 'brand'
     *          getAttributeCodeFromPrefix('brand', 1) => 'brand'
     *
     * @param  string   $prefix
     * @param  int|null $storeId  null = current store
     * @return string
     */
    public function getAttributeCodeFromPrefix($prefix, $storeId = null)
    {
        $prefix = strtolower($prefix);

        // 1. Try the current (or given) store first
        $map     = $this->_getUrlLabelMap($storeId);
        $flipped = array_flip($map);
        if (isset($flipped[$prefix])) {
            return $flipped[$prefix];
        }

        // 2. The prefix wasn't found in the current store map — this can happen
        //    when the router fires before the store is resolved (store_id = 0
        //    at that point). Search all stores to find a match.
        foreach (Mage::app()->getStores(false) as $store) {
            $storeMap     = $this->_getUrlLabelMap((int)$store->getId());
            $storeFlipped = array_flip($storeMap);
            if (isset($storeFlipped[$prefix])) {
                return $storeFlipped[$prefix];
            }
        }

        // 3. No mapping found — treat prefix as the attribute code itself
        return $prefix;
    }
}
