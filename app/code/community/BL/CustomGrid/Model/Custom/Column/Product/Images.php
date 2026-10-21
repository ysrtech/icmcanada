<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   BL
 * @package    BL_CustomGrid
 * @copyright  Copyright (c) 2015 Benoît Leulliette <benoit.leulliette@gmail.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class BL_CustomGrid_Model_Custom_Column_Product_Images extends BL_CustomGrid_Model_Custom_Column_Simple_Abstract
{
    /**
     * Display the number of images held by the media gallery
     */
    const DISPLAY_MODE_COUNT = 'count';

    /**
     * Display whether the media gallery holds at least a given number of images
     */
    const DISPLAY_MODE_FLAG = 'flag';

    /**
     * Default number of images from which a product is considered as having multiple images
     */
    const DEFAULT_MINIMUM_COUNT = 2;

    /**
     * Key under which the ID of the column store is added to the column parameters
     */
    const PARAM_STORE_ID = 'blcg_images_store_id';

    protected function _prepareConfig()
    {
        $helper = $this->getBaseHelper();

        $descriptions = array(
            'exclude_disabled' => 'Choose "<strong>Yes</strong>" to ignore the images that are excluded from the '
                . 'product page, as it is set in the "<strong>Images</strong>" tab of the product',
            'minimum_count' => 'Number of images from which a product is considered as having multiple images. '
                . 'If none is set, "<strong>2</strong>" will be used',
        );

        $this->addCustomizationParam(
            'exclude_disabled',
            array(
                'label'        => $helper->__('Exclude Hidden Images'),
                'description'  => $helper->__($descriptions['exclude_disabled']),
                'type'         => 'select',
                'source_model' => 'customgrid/system_config_source_yesno',
                'value'        => 0,
            ),
            10
        );

        if ($this->isFlagMode()) {
            $this->addCustomizationParam(
                'minimum_count',
                array(
                    'label'       => $helper->__('Minimum Number Of Images'),
                    'description' => $helper->__($descriptions['minimum_count']),
                    'type'        => 'text',
                    'value'       => self::DEFAULT_MINIMUM_COUNT,
                ),
                20
            );
        }

        return parent::_prepareConfig();
    }

    /**
     * Return whether the column displays a flag rather than the actual number of images
     *
     * @return bool
     */
    public function isFlagMode()
    {
        return ($this->getConfigParam('display_mode') == self::DISPLAY_MODE_FLAG);
    }

    /**
     * Return the number of images from which a product is considered as having multiple images
     *
     * @param array $params Column parameters
     * @return int
     */
    protected function _getMinimumCount(array $params)
    {
        $minimumCount = $this->_extractIntParam($params, 'minimum_count', self::DEFAULT_MINIMUM_COUNT, true);
        return ($minimumCount > 0 ? $minimumCount : self::DEFAULT_MINIMUM_COUNT);
    }

    /**
     * Return the ID of the "media_gallery" product attribute
     *
     * @return int
     */
    protected function _getMediaGalleryAttributeId()
    {
        if (!$this->hasData('media_gallery_attribute_id')) {
            /** @var $eavConfig Mage_Eav_Model_Config */
            $eavConfig = Mage::getSingleton('eav/config');
            $attribute = $eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'media_gallery');
            $this->setData('media_gallery_attribute_id', ($attribute ? (int) $attribute->getId() : 0));
        }
        return $this->_getData('media_gallery_attribute_id');
    }

    /**
     * Return the select usable to count the images held by the media gallery of each product
     *
     * @param Varien_Data_Collection_Db $collection Grid collection
     * @param array $params Column parameters
     * @return Varien_Db_Select
     */
    protected function _getCountSelect(Varien_Data_Collection_Db $collection, array $params)
    {
        $collectionHandler = $this->getCollectionHandler();
        $mainAlias    = $collectionHandler->getCollectionMainTableAlias($collection);
        $galleryAlias = $collectionHandler->getUniqueTableAlias('_media_gallery');

        /** @var $adapter Zend_Db_Adapter_Abstract */
        list($adapter, $qi) = $collectionHandler->getCollectionAdapter($collection, true);

        $countSelect = $adapter->select()
            ->from(
                array($galleryAlias => $collection->getTable('catalog/product_attribute_media_gallery')),
                array('count' => new Zend_Db_Expr('COUNT(DISTINCT ' . $qi($galleryAlias . '.value_id') . ')'))
            )
            ->where($qi($galleryAlias . '.attribute_id') . ' = ?', $this->_getMediaGalleryAttributeId())
            ->where($qi($galleryAlias . '.entity_id') . ' = ' . $qi($mainAlias . '.entity_id'));

        if ($this->_extractBoolParam($params, 'exclude_disabled')) {
            /**
             * The "disabled" flag can be set for each store view, with a fallback on the default value,
             * as it is done by Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media
             */
            $storeAlias   = $collectionHandler->getUniqueTableAlias('_media_gallery_store_value');
            $defaultAlias = $collectionHandler->getUniqueTableAlias('_media_gallery_default_value');
            $valuesTable  = $collection->getTable('catalog/product_attribute_media_gallery_value');
            $storeId      = $this->_extractIntParam($params, self::PARAM_STORE_ID, 0);

            $countSelect
                ->joinLeft(
                    array($storeAlias => $valuesTable),
                    $qi($storeAlias . '.value_id') . ' = ' . $qi($galleryAlias . '.value_id')
                    . ' AND ' . $adapter->quoteInto($qi($storeAlias . '.store_id') . ' = ?', $storeId),
                    array()
                )
                ->joinLeft(
                    array($defaultAlias => $valuesTable),
                    $qi($defaultAlias . '.value_id') . ' = ' . $qi($galleryAlias . '.value_id')
                    . ' AND ' . $qi($defaultAlias . '.store_id') . ' = 0',
                    array()
                )
                ->where(
                    'IFNULL(' . $qi($storeAlias . '.disabled')
                    . ', IFNULL(' . $qi($defaultAlias . '.disabled') . ', 0)) = 0'
                );
        }

        return $countSelect;
    }

    /**
     * Return the SQL expression holding the value of the column
     *
     * @param Varien_Data_Collection_Db $collection Grid collection
     * @param array $params Column parameters
     * @return string
     */
    protected function _getColumnExpression(Varien_Data_Collection_Db $collection, array $params)
    {
        $countQuery = '(' . $this->_getCountSelect($collection, $params) . ')';

        return $this->isFlagMode()
            ? 'IF(' . $countQuery . ' >= ' . $this->_getMinimumCount($params) . ', 1, 0)'
            : $countQuery;
    }

    public function applyToGridCollection(
        Varien_Data_Collection_Db $collection,
        Mage_Adminhtml_Block_Widget_Grid $gridBlock,
        BL_CustomGrid_Model_Grid $gridModel,
        $columnBlockId,
        $columnIndex,
        array $params,
        Mage_Core_Model_Store $store
    ) {
        // The store is not forwarded to the collection callbacks, put it where they will be able to find it
        $params[self::PARAM_STORE_ID] = $store->getId();

        return parent::applyToGridCollection(
            $collection,
            $gridBlock,
            $gridModel,
            $columnBlockId,
            $columnIndex,
            $params,
            $store
        );
    }

    public function addFieldToGridCollection(
        $columnIndex,
        array $params,
        Mage_Adminhtml_Block_Widget_Grid $gridBlock,
        Varien_Data_Collection_Db $collection
    ) {
        $collection->getSelect()
            ->columns(array($columnIndex => new Zend_Db_Expr($this->_getColumnExpression($collection, $params))));
        return $this;
    }

    /**
     * Return the SQL conditions to apply on the images count for the given filter condition,
     * when the column displays a flag
     *
     * @param Zend_Db_Adapter_Abstract $adapter Grid collection adapter
     * @param mixed $condition Filter condition
     * @param array $params Column parameters
     * @return string[]
     */
    protected function _getFlagFilterConditions(Zend_Db_Adapter_Abstract $adapter, $condition, array $params)
    {
        if (is_array($condition)) {
            $condition = (isset($condition['eq']) ? $condition['eq'] : null);
        }
        if (is_null($condition) || ($condition === '')) {
            return array();
        }

        $minimumCount = $this->_getMinimumCount($params);
        return array(($condition ? ' >= ' : ' < ') . $adapter->quote($minimumCount, Zend_Db::INT_TYPE));
    }

    /**
     * Return the SQL conditions to apply on the images count for the given filter condition,
     * when the column displays the actual number of images
     *
     * @param Zend_Db_Adapter_Abstract $adapter Grid collection adapter
     * @param mixed $condition Filter condition
     * @return string[]
     */
    protected function _getCountFilterConditions(Zend_Db_Adapter_Abstract $adapter, $condition)
    {
        $conditions = array();

        if (is_array($condition)) {
            // The range filter may only hold one of its two bounds
            if (isset($condition['from']) && ($condition['from'] !== '')) {
                $conditions[] = ' >= ' . $adapter->quote($condition['from']);
            }
            if (isset($condition['to']) && ($condition['to'] !== '')) {
                $conditions[] = ' <= ' . $adapter->quote($condition['to']);
            }
        } elseif (!is_null($condition) && ($condition !== '')) {
            $conditions[] = ' = ' . $adapter->quote($condition);
        }

        return $conditions;
    }

    public function addFilterToGridCollection(
        Varien_Data_Collection_Db $collection,
        Mage_Adminhtml_Block_Widget_Grid_Column $columnBlock
    ) {
        $params = $columnBlock->getBlcgFilterParams();

        if (!is_array($params)) {
            return $this;
        }

        $adapter   = $this->getCollectionHandler()->getCollectionAdapter($collection);
        $condition = $columnBlock->getFilter()->getCondition();

        $conditions = $this->isFlagMode()
            ? $this->_getFlagFilterConditions($adapter, $condition, $params)
            : $this->_getCountFilterConditions($adapter, $condition);

        if (!empty($conditions)) {
            $countQuery = '(' . $this->_getCountSelect($collection, $params) . ')';

            foreach ($conditions as $conditionSql) {
                $collection->getSelect()->where(new Zend_Db_Expr($countQuery . $conditionSql));
            }
        }

        return $this;
    }

    public function getForcedBlockValues(
        Mage_Adminhtml_Block_Widget_Grid $gridBlock,
        BL_CustomGrid_Model_Grid $gridModel,
        $columnBlockId,
        $columnIndex,
        array $params,
        Mage_Core_Model_Store $store
    ) {
        $helper = $this->getBaseHelper();
        $params[self::PARAM_STORE_ID] = $store->getId();

        $values = array(
            'blcg_filter_params' => $params,
            'filter_condition_callback' => array($this, 'addFilterToGridCollection'),
        );

        if ($this->isFlagMode()) {
            $values['type']    = 'options';
            $values['filter']  = 'customgrid/widget_grid_column_filter_yesno';
            $values['options'] = array(
                1 => $helper->__('Yes'),
                0 => $helper->__('No'),
            );
        } else {
            $values['type'] = 'number';
        }

        return $values;
    }
}
