<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog layered navigation view block
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class DC_Catalog_Block_Layer_View extends Mage_Catalog_Block_Layer_View
{
    /**
     * Get all filterable attributes of current category, excluding the
     * attribute the current attribute info page is built on.
     *
     * Mage_Catalog_Model_Layer::getFilterableAttributes() returns either a
     * Varien_Data_Collection or a plain array (e.g. when the layer's product
     * collection has no attribute sets), so both cases are handled here.
     *
     * @return Mage_Catalog_Model_Resource_Product_Attribute_Collection|array
     */
    protected function _getFilterableAttributes()
    {
        $attributes = $this->getData('_filterable_attributes');
        if (is_null($attributes)) {
            $attributes = $this->getLayer()->getFilterableAttributes();
            $excludeCode = Mage::registry('attribute_code');

            if ($excludeCode) {
                if ($attributes instanceof Varien_Data_Collection) {
                    //remove the current attribute from layered nav
                    foreach ($attributes as $a) {
                        if ($a->getAttributeCode() == $excludeCode) {
                            $attributes->removeItemByKey($a->getId());
                        }
                    }
                } elseif (is_array($attributes)) {
                    foreach ($attributes as $key => $a) {
                        if (is_object($a) && $a->getAttributeCode() == $excludeCode) {
                            unset($attributes[$key]);
                        }
                    }
                }
            }

            $this->setData('_filterable_attributes', $attributes);
        }
        return $attributes;
    }

	/**
     * Get layer object
     *
     * @return Mage_Catalog_Model_Layer
     */
    public function getLayer()
    {
    	//var_export(Mage::getSingleton('catalog/layer')->getAttributeInfoPage());
        return Mage::getSingleton('catalog/layer');
    }
}
