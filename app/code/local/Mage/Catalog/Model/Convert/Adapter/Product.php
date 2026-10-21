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


class Mage_Catalog_Model_Convert_Adapter_Product
extends Mage_Eav_Model_Convert_Adapter_Entity
{
    const MULTI_DELIMITER   = ',';
    const ENTITY            = 'catalog_product_import';
    const CREATE_CATEGORIES = 'no';
    const CREATE_OPTIONS   = 'no';
    const SKIP_ERRORS   = 'no';
    const LOG_ERRORS   = 'yes';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'catalog_product_import';

    /**
     * Product model
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_productModel;

    /**
     * product types collection array
     *
     * @var array
     */
    protected $_productTypes;

    /**
     * Product Type Instances singletons
     *
     * @var array
     */
    protected $_productTypeInstances = array();

    /**
     * product attribute set collection array
     *
     * @var array
     */
    protected $_productAttributeSets;

    protected $_stores;

    protected $_attributes = array();

    protected $_configs = array();

    protected $_requiredFields = array();

    protected $_ignoreFields = array();

    protected $_imageFields = array();

    /**
     * Inventory Fields array
     *
     * @var array
     */
    protected $_inventoryFields             = array();

    /**
     * Inventory Fields by product Types
     *
     * @var array
     */
    protected $_inventoryFieldsProductTypes = array();

    protected $_toNumber = array();

    public function importErrors($message) {
        if(self::LOG_ERRORS == 'yes'){
            Mage::log(strip_tags($message), null, 'importErrors.log');
        }

        if(self::SKIP_ERRORS == 'yes'){
            $message = 'Item Skipped: '.$message;
            Mage::throwException($message);
        }

    }

    /**
     * Load product collection Id(s)
     *
     */
    public function load()
    {
        $attrFilterArray = array();
        $attrFilterArray ['name']           = 'like';
        $attrFilterArray ['sku']            = 'like';
        $attrFilterArray ['type']           = 'eq';
        $attrFilterArray ['attribute_set']  = 'eq';
        $attrFilterArray ['visibility']     = 'eq';
        $attrFilterArray ['status']         = 'eq';
        $attrFilterArray ['price']          = 'fromTo';
        $attrFilterArray ['qty']            = 'fromTo';
        $attrFilterArray ['store_id']       = 'eq';

        $attrToDb = array(
            'type'          => 'type_id',
            'attribute_set' => 'attribute_set_id'
        );

        $filters = $this->_parseVars();

        if ($qty = $this->getFieldValue($filters, 'qty')) {
            $qtyFrom = isset($qty['from']) ? $qty['from'] : 0;
            $qtyTo   = isset($qty['to']) ? $qty['to'] : 0;

            $qtyAttr = array();
            $qtyAttr['alias']       = 'qty';
            $qtyAttr['attribute']   = 'cataloginventory/stock_item';
            $qtyAttr['field']       = 'qty';
            $qtyAttr['bind']        = 'product_id=entity_id';
            $qtyAttr['cond']        = "{{table}}.qty between '{$qtyFrom}' AND '{$qtyTo}'";
            $qtyAttr['joinType']    = 'inner';

            $this->setJoinField($qtyAttr);
        }

        parent::setFilter($attrFilterArray, $attrToDb);

        if ($price = $this->getFieldValue($filters, 'price')) {
            $this->_filter[] = array(
                'attribute' => 'price',
                'from'      => $price['from'],
                'to'        => $price['to']
            );
            $this->setJoinAttr(array(
                'alias'     => 'price',
                'attribute' => 'catalog_product/price',
                'bind'      => 'entity_id',
                'joinType'  => 'LEFT'
                ));
        }

        return parent::load();
    }

    /**
     * Retrieve product model cache
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProductModel()
    {
        if (is_null($this->_productModel)) {
            $productModel = Mage::getModel('catalog/product');
            $this->_productModel = Mage::objects()->save($productModel);
        }
        return Mage::objects()->load($this->_productModel);
    }

    /**
     * Retrieve eav entity attribute model
     *
     * @param string $code
     * @return Mage_Eav_Model_Entity_Attribute
     */
    public function getAttribute($code)
    {
        if (!isset($this->_attributes[$code])) {
            $this->_attributes[$code] = $this->getProductModel()->getResource()->getAttribute($code);
        }
        if ($this->_attributes[$code] instanceof Mage_Catalog_Model_Resource_Eav_Attribute) {
            $applyTo = $this->_attributes[$code]->getApplyTo();
            if ($applyTo && !in_array($this->getProductModel()->getTypeId(), $applyTo)) {
                return false;
            }
        }
        return $this->_attributes[$code];
    }

    /**
     * Retrieve product type collection array
     *
     * @return array
     */
    public function getProductTypes()
    {
        if (is_null($this->_productTypes)) {
            $this->_productTypes = array();
            $options = Mage::getModel('catalog/product_type')
            ->getOptionArray();
            foreach ($options as $k => $v) {
                $this->_productTypes[$k] = $k;
            }
        }
        return $this->_productTypes;
    }

    /**
     * ReDefine Product Type Instance to Product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Convert_Adapter_Product
     */
    public function setProductTypeInstance(Mage_Catalog_Model_Product $product)
    {
        $type = $product->getTypeId();
        if (!isset($this->_productTypeInstances[$type])) {
            $this->_productTypeInstances[$type] = Mage::getSingleton('catalog/product_type')
            ->factory($product, true);
        }
        $product->setTypeInstance($this->_productTypeInstances[$type], true);
        return $this;
    }

    /**
     * Retrieve product attribute set collection array
     *
     * @return array
     */
    public function getProductAttributeSets()
    {
        if (is_null($this->_productAttributeSets)) {
            $this->_productAttributeSets = array();

            $entityTypeId = Mage::getModel('eav/entity')
            ->setType('catalog_product')
            ->getTypeId();
            $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter($entityTypeId);
            foreach ($collection as $set) {
                $this->_productAttributeSets[$set->getAttributeSetName()] = $set->getId();
            }
        }
        return $this->_productAttributeSets;
    }

    /**
     *  Init stores
     */
    protected function _initStores ()
    {
        if (is_null($this->_stores)) {
            $this->_stores = Mage::app()->getStores(true, true);
            foreach ($this->_stores as $code => $store) {
                $this->_storesIdCode[$store->getId()] = $code;
            }
        }
    }

    /**
     * Retrieve store object by code
     *
     * @param string $store
     * @return Mage_Core_Model_Store
     */
    public function getStoreByCode($store)
    {
        $this->_initStores();
        /**
         * In single store mode all data should be saved as default
         */
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        }

        if (isset($this->_stores[$store])) {
            return $this->_stores[$store];
        }
        return false;
    }

    /**
     * Retrieve store object by code
     *
     * @param string $store
     * @return Mage_Core_Model_Store
     */
    public function getStoreById($id)
    {
        $this->_initStores();
        /**
         * In single store mode all data should be saved as default
         */
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        }

        if (isset($this->_storesIdCode[$id])) {
            return $this->getStoreByCode($this->_storesIdCode[$id]);
        }
        return false;
    }

    public function parse()
    {
        $batchModel = Mage::getSingleton('dataflow/batch');
        /* @var $batchModel Mage_Dataflow_Model_Batch */

        $batchImportModel = $batchModel->getBatchImportModel();
        $importIds = $batchImportModel->getIdCollection();
        $rowNumber = 0;
        foreach ($importIds as $importId) {
            //print '<pre>'.memory_get_usage().'</pre>';
            $batchImportModel->load($importId);
            $importData = $batchImportModel->getBatchData();
            $this->saveRow($importData);

        }
    }

    protected $_productId = '';

    /**
     * Initialize convert adapter model for products collection
     *
     */
    public function __construct()
    {
        $fieldset = Mage::getConfig()->getFieldset('catalog_product_dataflow', 'admin');
        foreach ($fieldset as $code => $node) {
            /* @var $node Mage_Core_Model_Config_Element */
            if ($node->is('inventory')) {
                foreach ($node->product_type->children() as $productType) {
                    $productType = $productType->getName();
                    $this->_inventoryFieldsProductTypes[$productType][] = $code;
                    if ($node->is('use_config')) {
                        $this->_inventoryFieldsProductTypes[$productType][] = 'use_config_' . $code;
                    }
                }

                $this->_inventoryFields[] = $code;
                if ($node->is('use_config')) {
                    $this->_inventoryFields[] = 'use_config_'.$code;
                }
            }
            if ($node->is('required')) {
                $this->_requiredFields[] = $code;
            }
            if ($node->is('ignore')) {
                $this->_ignoreFields[] = $code;
            }
            if ($node->is('img')) {
                $this->_imageFields[] = $code;
            }
            if ($node->is('to_number')) {
                $this->_toNumber[] = $code;
            }
        }

        $this->setVar('entity_type', 'catalog/product');
        if (!Mage::registry('Object_Cache_Product')) {
            $this->setProduct(Mage::getModel('catalog/product'));
        }

        if (!Mage::registry('Object_Cache_StockItem')) {
            $this->setStockItem(Mage::getModel('cataloginventory/stock_item'));
        }
    }

    /**
     * Retrieve not loaded collection
     *
     * @param string $entityType
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
     */
    protected function _getCollectionForLoad($entityType)
    {
        $collection = parent::_getCollectionForLoad($entityType)
        ->setStoreId($this->getStoreId())
        ->addStoreFilter($this->getStoreId());
        return $collection;
    }

    public function setProduct(Mage_Catalog_Model_Product $object)
    {
        $id = Mage::objects()->save($object);
        //$this->_product = $object;
        Mage::register('Object_Cache_Product', $id);
    }

    public function getProduct()
    {
        return Mage::objects()->load(Mage::registry('Object_Cache_Product'));
    }

    public function setStockItem(Mage_CatalogInventory_Model_Stock_Item $object)
    {
        $id = Mage::objects()->save($object);
        Mage::register('Object_Cache_StockItem', $id);
    }

    public function getStockItem()
    {
        return Mage::objects()->load(Mage::registry('Object_Cache_StockItem'));
    }

    public function save()
    {
        $stores = array();
        foreach (Mage::getConfig()->getNode('stores')->children() as $storeNode) {
            $stores[(int)$storeNode->system->store->id] = $storeNode->getName();
        }

        $collections = $this->getData();
        if ($collections instanceof Mage_Catalog_Model_Entity_Product_Collection) {
            $collections = array($collections->getEntity()->getStoreId()=>$collections);
        } elseif (!is_array($collections)) {
            $this->addException(Mage::helper('catalog')->__('No product collections found.'), Mage_Dataflow_Model_Convert_Exception::FATAL);
        }

        //$stockItems = $this->getInventoryItems();
        $stockItems = Mage::registry('current_imported_inventory');
        if ($collections) foreach ($collections as $storeId=>$collection) {
            $this->addException(Mage::helper('catalog')->__('Records for "'.$stores[$storeId].'" store found.'));

            if (!$collection instanceof Mage_Catalog_Model_Entity_Product_Collection) {
                $this->addException(Mage::helper('catalog')->__('Product collection expected.'), Mage_Dataflow_Model_Convert_Exception::FATAL);
            }
            try {
                $i = 0;
                foreach ($collection->getIterator() as $model) {
                    $new = false;
                    // if product is new, create default values first
                    if (!$model->getId()) {
                        $new = true;
                        $model->save();

                        // if new product and then store is not default
                        // we duplicate product as default product with store_id -
                        if (0 !== $storeId ) {
                            $data = $model->getData();
                            $default = Mage::getModel('catalog/product');
                            $default->setData($data);
                            $default->setStoreId(0);
                            $default->save();
                            unset($default);
                        } // end

                        #Mage::getResourceSingleton('catalog_entity/convert')->addProductToStore($model->getId(), 0);
                    }
                    if (!$new || 0!==$storeId) {
                        if (0!==$storeId) {
                            Mage::getResourceSingleton('catalog_entity/convert')->addProductToStore($model->getId(), $storeId);
                        }
                        $model->save();
                    }

                    if (isset($stockItems[$model->getSku()]) && $stock = $stockItems[$model->getSku()]) {
                        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($model->getId());
                        $stockItemId = $stockItem->getId();

                        if (!$stockItemId) {
                            $stockItem->setData('product_id', $model->getId());
                            $stockItem->setData('stock_id', 1);
                            $data = array();
                        } else {
                            $data = $stockItem->getData();
                        }

                        foreach($stock as $field => $value) {
                            if (!$stockItemId) {
                                if (in_array($field, $this->_configs)) {
                                    $stockItem->setData('use_config_'.$field, 0);
                                }
                                $stockItem->setData($field, $value?$value:0);
                            } else {

                                if (in_array($field, $this->_configs)) {
                                    if ($data['use_config_'.$field] == 0) {
                                        $stockItem->setData($field, $value?$value:0);
                                    }
                                } else {
                                    $stockItem->setData($field, $value?$value:0);
                                }
                            }
                        }
                        $stockItem->save();
                        unset($data);
                        unset($stockItem);
                        unset($stockItemId);
                    }
                    unset($model);
                    $i++;
                }
                $this->addException(Mage::helper('catalog')->__("Saved %d record(s)", $i));
            } catch (Exception $e) {
                if (!$e instanceof Mage_Dataflow_Model_Convert_Exception) {
                    $this->addException(Mage::helper('catalog')->__('An error occurred while saving the collection, aborting. Error message: %s', $e->getMessage()),
                        Mage_Dataflow_Model_Convert_Exception::FATAL);
                }
            }
        }
        unset($collections);
        return $this;
    }

    /**
     * Save product (import)
     *
     * @param array $importData
     * @throws Mage_Core_Exception
     * @return bool
     */
    public function saveRow(array $importData )
    {
        $product = $this->getProductModel()
        ->reset();
        ///by chezky to know which row was skipped, look for $rowidentifier to where its used
        $rowidentifier ='';

        if (!empty($importData['sku'])) {
            $rowidentifier = '<b>SKU:</b> '.$importData['sku'];
        }elseif (!empty($importData['name'])) {
            $rowidentifier = '<b>Name:</b> '.$importData['name'];
        }



        ///////
        if (empty($importData['store'])) {
            if (!is_null($this->getBatchParams('store'))) {
                $store = $this->getStoreById($this->getBatchParams('store'));
            } else {
                $message = Mage::helper('catalog')->__('Skipping import row with "%s", required field "%s" is not defined.', $rowidentifier, 'store');
                Mage::throwException($message);
            }
        }
        else {
            $store = $this->getStoreByCode($importData['store']);
        }

        if ($store === false) {
            $message = Mage::helper('catalog')->__('Skipping import row with "%s", store "%s" field does not exist.', $rowidentifier, $importData['store']);
            Mage::throwException($message);
        }

        if (empty($importData['sku'])) {
            $message = Mage::helper('catalog')->__('Skipping import row with "%s", required field "%s" is not defined.', $rowidentifier, 'sku');
            Mage::throwException($message);
        }
        $product->setStoreId($store->getId());
        $productId = $product->getIdBySku($importData['sku']);

        $new = true; // chezky fix for duplicating attributes error in configurable code

        if ($productId) {
            $product->load($productId);
            $new = false; // chezky fix for duplicating attributes error

            // delete specified products
            if ( isset($importData['status']) && $importData['status'] == 'DELETE' ) {
                $product = Mage :: getSingleton( 'catalog/product' ) -> load( $productId );
                $this -> _removeFile( Mage :: getSingleton( 'catalog/product_media_config' ) -> getMediaPath( $product -> getData( 'image' ) ) );
                $this -> _removeFile( Mage :: getSingleton( 'catalog/product_media_config' ) -> getMediaPath( $product -> getData( 'small_image' ) ) );
                $this -> _removeFile( Mage :: getSingleton( 'catalog/product_media_config' ) -> getMediaPath( $product -> getData( 'thumbnail' ) ) );
                $media_gallery = $product -> getData( 'media_gallery' );
                foreach ( $media_gallery['images'] as $image ) {
                    $this -> _removeFile( Mage :: getSingleton( 'catalog/product_media_config' ) -> getMediaPath( $image['file'] ) );
                }
                $product -> delete();
                return true;
            }
            //
        }
        else {
            $productTypes = $this->getProductTypes();
            $productAttributeSets = $this->getProductAttributeSets();

            /**
             * Check product define type
             */
            if (empty($importData['type']) || !isset($productTypes[strtolower($importData['type'])])) {
                $value = isset($importData['type']) ? $importData['type'] : '';
                $message = Mage::helper('catalog')->__('Skip import row with %s, "%s" is not valid value for field "%s" for new products',$rowidentifier, $value, 'type');
                Mage::throwException($message);
            }
            $product->setTypeId($productTypes[strtolower($importData['type'])]);
            /**
             * Check product define attribute set
             */
            if (empty($importData['attribute_set']) || !isset($productAttributeSets[$importData['attribute_set']])) {
                $value = isset($importData['attribute_set']) ? $importData['attribute_set'] : '';
                $message = Mage::helper('catalog')->__('Skip import row with %s, "%s" is not valid value for field "%s" for new products',$rowidentifier, $value, 'attribute_set');
                Mage::throwException($message);
            }
            $product->setAttributeSetId($productAttributeSets[$importData['attribute_set']]);

            foreach ($this->_requiredFields as $field) {
                $attribute = $this->getAttribute($field);
                if (!isset($importData[$field]) && $attribute && $attribute->getIsRequired()) {
                    $message = Mage::helper('catalog')->__('Skipping import row with %s, required field "%s" for new products is not defined.',$rowidentifier, $field);
                    Mage::throwException($message);
                }
            }
        }

        $this->setProductTypeInstance($product);

        ///Configurable Import
        $finalsuperattributetype = '';
        if (isset($importData['type']) && $importData['type'] == 'configurable') {
            if ($importData['config_attributes']=='') {
                    $message = Mage::helper('catalog')->__('Skipping import row with %s, required field "config_attributes" required for configurable products is not defined.',$rowidentifier);
                    Mage::throwException($message);
                }

            $finalsuperattributepricing = "";
            $finalsuperattributetype = $importData['type'];

            if (isset($importData['super_attribute_pricing'])) {
                $finalsuperattributepricing = $importData['super_attribute_pricing'];
            }

            $product->setCanSaveConfigurableAttributes(true);
            $configAttributeCodes = $this->userCSVDataAsArray($importData['config_attributes']);
            $usingAttributeIds = array();
            /***
            * Check the product's super attributes (see catalog_product_super_attribute table), and make a determination that way.
            **/
            $cspa  = $product->getTypeInstance()->getConfigurableAttributesAsArray($product);
            $attr_codes = array();
            if(isset($cspa) && !empty($cspa)){ //found attributes
                foreach($cspa as $cs_attr){
                    //$attr_codes[$cs_attr['attribute_id']] = $cs_attr['attribute_code'];
                    $attr_codes[] = $cs_attr['attribute_id'];
                }
            }

            foreach($configAttributeCodes as $attributeCode) {
                $attribute = $product->getResource()->getAttribute($attributeCode);
                if($attribute==''){
                    $message = Mage::helper('catalog')->__('Skipping import row with %s, attribute "%s" in the "config_attributes" column is not an attribute.',$rowidentifier, $attributeCode);
                    Mage::throwException($message);
                }
                if ($product->getTypeInstance()->canUseAttribute($attribute)) {
                    //if (!in_array($attributeCode,$attr_codes)) { // fix for duplicating attributes error
                    if ($new) { // fix for duplicating attributes error // <---------- this must be true to fill $usingAttributes
                        $usingAttributeIds[] = $attribute->getAttributeId();
                    }
                }
                else{
                    $message = Mage::helper('catalog')->__('Skipping import row with %s, attribute "%s" in the "config_attributes" column is not a configurable attribute.',$rowidentifier, $attributeCode);
                    Mage::throwException($message);
                }

            }
            if ( !empty( $usingAttributeIds ) ) {
                $product -> getTypeInstance() -> setUsedProductAttributeIds( $usingAttributeIds );
                $attributes_array = $product->getTypeInstance()->getConfigurableAttributesAsArray();
                foreach($attributes_array as $key => $attribute_value) {
                    $attributes_array[$key]['label'] = $attribute_value['frontend_label'];
                }
                $product -> setConfigurableAttributesData($attributes_array);
                $product -> setCanSaveConfigurableAttributes( true );
                $product -> setCanSaveCustomOptions( true );
            }

            if (isset($importData['associated'])) {
                
                $product->setConfigurableProductsData($this->skusToIds($importData['associated'], $product));
                
            }elseif(isset($importData['find_children']) && strtolower($importData['find_children']) == 'yes'){
                
                $children_found = $this->_findChildren($importData['sku']);
                if(count($children_found)){
                    $product->setConfigurableProductsData($children_found);
                }
            }
        }
        ///end configurable

        ///by chezky relations grouped etc
        if ( isset( $importData['related'] ) ) {
            $linkIds = $this -> skusToIds( $importData['related'], $product );
            if ( !empty( $linkIds ) ) {
                $product->setRelatedLinkData( $linkIds );
            }
        }

        if ( isset( $importData['upsell'] ) ) {
            $linkIds = $this -> skusToIds( $importData['upsell'], $product );
            if ( !empty( $linkIds ) ) {
                $product->setUpSellLinkData( $linkIds );
            }
        }

        if ( isset( $importData['crosssell'] ) ) {
            $linkIds = $this -> skusToIds( $importData['crosssell'], $product );
            if ( !empty( $linkIds ) ) {
                $product->setCrossSellLinkData( $linkIds );
            }
        }

        if ( isset( $importData['grouped'] ) ) {
            $linkIds = $this -> skusToIds( $importData['grouped'], $product );
            if ( !empty( $linkIds ) ) {
                $product->setGroupedLinkData( $linkIds );
            }
        }
        ///

        /// Tier Prices
        if( isset($importData['tier_prices']) && !empty($importData['tier_prices']) ) {
            $this->_editTierPrices($product, $importData['tier_prices']);
        }
        ///
        
        //Group Prices
       if(isset($importData['group_prices']) && !empty($importData['group_prices'])){
            $this->_editGroupPrices($product, $importData['group_prices']);

        }
        ///
        
        if (isset($importData['category_ids'])) {
            $product->setCategoryIds($importData['category_ids']);
        }

        // Import categories by name chezky from here http://www.magentocommerce.com/wiki/import-export_and_data_manipulation/how_to_import_category_structure_with_products_using_dataflow
            if (isset($importData['categories']) && strlen($importData['categories'])) {
                if (isset($importData['store']) && $importData['store'] != 'admin' && $this->_stores[$importData['store']]) {
                    $cat_store = $this->_stores[$importData['store']];
                } else {

                     $default_websites = Mage::app()->getWebsites();
                     $default_code = $default_websites[1]->getDefaultStore()->getCode();
                     
                     $cat_store = $this->_stores[$default_code];
               }
                
                
                $categories_fieldvalue = $importData['categories'];
                
                $categoryIds = $this->_addCategories($categories_fieldvalue , $cat_store);
                //Mage::throwException($categoryIds);
                
                if ($categoryIds) {
                    $product->setCategoryIds($categoryIds);
                    
                    if (!empty($this->_categoryPositions)) {
                        $importData['categories_positions'] = join(';',$this->_categoryPositions);
                    }
                }
                else {
                    $message = Mage::helper('catalog')->__('%s, one or more of the category/ies "%s" not found',$rowidentifier,$categories_fieldvalue);
                    $this->importErrors($message);
                }

            }

        ///end modify by chezky

        foreach ($this->_ignoreFields as $field) {
            if (isset($importData[$field])) {
                unset($importData[$field]);
            }
        }

        if ($store->getId() != 0) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = array();
            }
            if (!in_array($store->getWebsiteId(), $websiteIds)) {
                $websiteIds[] = $store->getWebsiteId();
            }
            $product->setWebsiteIds($websiteIds);
        }

        if (isset($importData['websites'])) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = array();
            }
            $websiteCodes = explode(',', $importData['websites']);
            foreach ($websiteCodes as $websiteCode) {
                try {
                    $website = Mage::app()->getWebsite(trim($websiteCode));
                    if (!in_array($website->getId(), $websiteIds)) {
                        $websiteIds[] = $website->getId();
                    }
                }
                catch (Exception $e) {}
            }
            $product->setWebsiteIds($websiteIds);
            unset($websiteIds);
        }
        //chezky
        $custom_options = array();
        ///
        foreach ($importData as $field => $value) {
            if (in_array($field, $this->_inventoryFields)) {
                continue;
            }
            if (in_array($field, $this->_imageFields)) {
                continue;
            }
            //chezky from newer version
            if (is_null($value)) {
                continue;
            }
            ///
            $attribute = $this->getAttribute($field);
            if (!$attribute) {
/* IMPORT CUSTOM OPTION CODE by chezky */
                if(strpos($field,':')!==FALSE && strlen($value)) {
                    $values=explode('|',$value);
                    if(count($values)>0) {
                        @list($title,$type,$is_required,$sort_order) = explode(':',$field);
                        $title = ucfirst(str_replace('_',' ',$title));
                        $custom_options[] = array(
                             'is_delete'=>0,
                             'title'=>$title,
                             'previous_group'=>'',
                             'previous_type'=>'',
                             'type'=>$type,
                             'is_require'=>$is_required,
                             'sort_order'=>$sort_order,
                             'values'=>array()
                        );
                        foreach($values as $v) {
                            $parts = explode(':',$v);
                            $title = $parts[0];
                            if(count($parts)>1) {
                                $price_type = $parts[1];
                            } else {
                                $price_type = 'fixed';
                            }
                            if(count($parts)>2) {
                                $price = $parts[2];
                            } else {
                                $price =0;
                            }
                            if(count($parts)>3) {
                                $sku = $parts[3];
                            } else {
                                $sku='';
                            }
                            if(count($parts)>4) {
                                $sort_order = $parts[4];
                            } else {
                                $sort_order = 0;
                            }
                            switch($type) {
                                case 'file':
                           /* TODO */
                                    break;

                                case 'field':
                                    case 'area':
                                        $custom_options[count($custom_options) - 1]['max_characters'] = $sort_order;
                           /* NO BREAK */
                                        case 'date':
                                            case 'date_time':
                                                case 'time':
                                                    $custom_options[count($custom_options) - 1]['price_type'] = $price_type;
                                                    $custom_options[count($custom_options) - 1]['price'] = $price;
                                                    $custom_options[count($custom_options) - 1]['sku'] = $sku;
                                                    break;

                                                case 'drop_down':
                                                    case 'radio':
                                                        case 'checkbox':
                                                            case 'multiple':
                                                                default:
                                                                    $custom_options[count($custom_options) - 1]['values'][]=array(
                                      'is_delete'=>0,
                                      'title'=>$title,
                                      'option_type_id'=>-1,
                                      'price_type'=>$price_type,
                                      'price'=>$price,
                                      'sku'=>$sku,
                                      'sort_order'=>$sort_order,
                                                                    );
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                    }
/* END CUSTOM OPTION CODE */
                                                    continue;
                                                }
                                                $isArray = false;
                                                $setValue = $value;

                                                if ($attribute->getFrontendInput() == 'multiselect') {
                                                    $value = explode(self::MULTI_DELIMITER, $value);
                                                    $isArray = true;
                                                    $setValue = array();
                                                }

                                                if ($value && $attribute->getBackendType() == 'decimal') {
                                                    $setValue = $this->getNumber($value);
                                                }
/**create options if not exists by chezky CODE MODIFICATION STARTS HERE */
                                                if(self::CREATE_OPTIONS == 'yes'){
                                                    $optionLabelArray=array();
                                                    if ($attribute->usesSource()) {
                                                        $options = $attribute->getSource()->getAllOptions(false);

                                                        //Update the Source of the attribute when source has no options.
                                                        if(count($options)<1){
                                                            if($isArray){
                                                                foreach($value as $key=>$subvalue){
                                                                    if(!in_array($subvalue,$newOptionLabelArray)){
                                                                        $setValue[]=$this->updateSourceAndReturnId($field,$subvalue);
                                                                        array_push($newOptionLabelArray,$subvalue);
                                                                    }
                                                                }
                                                            }
                                                            else{
                                                                if(!in_array($value,$newOptionLabelArray)){
                                                                    $setValue=$this->updateSourceAndReturnId($field,$value);
                                                                    array_push($newOptionLabelArray,$value);
                                                                }
                                                            }
                                                        }
                                                        // Work on the source when it has options
                                                        else{
                                                            //This is the case of Multi-Select
                                                            if ($isArray) {
                                                                foreach ($options as $item) {
                                        /** Setting the option's ID if Label matches with the current value of XML column.*/
                                                                    if (in_array($item['label'], $value)) {
                                                                        $setValue[] = trim($item['value']);
                                                                        array_push($optionLabelArray,$item['label']); /**Adding Reference to worked attribute option**/
                                                                    }
                                                                }
                                                /**
                                                 *  Checking in the current XML column value if all values were used in the above loop or not
                                                 *  If not used then they are new options value, then new option is created and then assigned.
                                                 **/
                                                                foreach($value as $key=>$subvalue){
                                                                    if(!in_array($subvalue,$optionLabelArray)){
                                                                        $setValue[]=$this->updateSourceAndReturnId($field,$subvalue);
                                                                    }
                                                                }
                                                            }
                                        /**This is the case of single select**/
                                                            else {
                                                                $setValue = null;
                                                                $newOptionLabelArray=array();
                                                                foreach ($options as $item) {
                                                                    if ($item['label'] == $value) {
                                                                        $setValue = $item['value'];
                                                                        array_push($optionLabelArray,$item['label']); /**Adding Reference to worked attribute option**/
                                                                    }
                                                                }
                                                        /**
                                                         *  Checking in the current XML column value if all values were used in the above loop or not
                                                         *  If not used then they are new options value, then new option is created and then assigned.
                                                         **/
                                                                if(!in_array($value,$optionLabelArray)){
                                                                    $setValue=$this->updateSourceAndReturnId($field,$value);
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                                else{
/**create options if not exists by chezky CODE MODIFICATION ends HERE*/
                                                    if ($attribute->usesSource()) {
                                                        $options = $attribute->getSource()->getAllOptions(false);
                                                        $matchFound = false; //by chezky
                                                        $hasvalue = false; //by chezky
                                                        $feildvalue = '';//by chezky
                                                        $feildempty = false;
                                                        if ($isArray) {
                                                            if($value[0] == ''){$feildempty = true;}
                                                            foreach ($options as $item) {;
                                                                if (in_array($item['label'], $value)) {
                                                                    $setValue[] = $item['value'];
                                                                    $matchFound = true;
                                                                }
                                                            }
                                                            if($matchFound){
                                                                foreach ($value as $ValueItem){
                                                                    $MultiMatchFound = false;
                                                                    foreach ($options as $systemItem){
                                                                        if ($systemItem['label'] == $ValueItem)
                                                                        $MultiMatchFound = true;
                                                                    }
                                                                    if(!$MultiMatchFound){
                                                                        $feildvalue .=$ValueItem.' ';
                                                                        $matchFound = false;
                                                                    }
                                                                }
                                                            }
                                                        }else {
                                                            if($value == ''){$feildempty = true;}
                                                            $setValue = false;
                                                            foreach ($options as $item) {
                                                                if ($item['label'] == $value) {
                                                                    $setValue = $item['value'];
                                                                    $matchFound = true; //by chezky
                                                                }
                                                            }
                                                        }
                                                        //by chezky
                                                        if(!$matchFound && !$feildempty)
                                                        {
                                                            if($feildvalue !=='' ){$feildvalue = $feildvalue;}else{$feildvalue = $value;}
                                                            $message = Mage::helper('catalog')->__('Import row with %s, "%s" option/s for attribute "%s" not found in system',$rowidentifier,$feildvalue,$field);
                                                            $this->importErrors($message);
                                                        }
                                                        //

                                                    }
                                                }
        /**create options ends here*/

                                                $product->setData($field, $setValue);
                                            }

                                            if (!$product->getVisibility()) {
                                                $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
                                            }

                                            $stockData = array();
                                            $inventoryFields = isset($this->_inventoryFieldsProductTypes[$product->getTypeId()])
                                            ? $this->_inventoryFieldsProductTypes[$product->getTypeId()]
                                            : array();
                                            foreach ($inventoryFields as $field) {
                                                if (isset($importData[$field])) {
                                                    if (in_array($field, $this->_toNumber)) {
                                                        $stockData[$field] = $this->getNumber($importData[$field]);
                                                    }
                                                    else {
                                                        $stockData[$field] = $importData[$field];
                                                    }
                                                }
                                            }
                                            $product->setStockData($stockData);

//images section
        $imageData = array();
        foreach ($this->_imageFields as $field) {
            if (!empty($importData[$field]) && $importData[$field] != 'no_selection') {
                if (!isset($imageData[$importData[$field]])) {
                    $imageData[$importData[$field]] = array();
                }
                $imageData[$importData[$field]][] = $field;
            }
        }

        //Chezky added to import gallery and/or remove all images for product before uploading new images
        if (isset($importData['remove_all_images']) && $importData['remove_all_images'] == "yes") {
            //check if gallery attribute exists then remove all images if it exists
            //Get products gallery attribute
            $attributes = Mage::getSingleton('catalog/product_type')->factory($product)->getSetAttributes(); //chezky $product->getTypeInstance()->getSetAttributes();//this caused the configurable product error
            if (isset($attributes['media_gallery'])) {
                $gallery = $attributes['media_gallery'];
                //Get the images
                $galleryData = $product->getMediaGallery();
                foreach ($galleryData['images'] as $image) {
                    //If image exists
                    if ($gallery->getBackend()->getImage($product, $image['file'])) {
                        $gallery->getBackend()->removeImage($product, $image['file']);
                    }
                }
            }
            //$gallery->clearMediaAttribute($product, array('image','small_image','thumbnail'));
        }
        //END Remove Images

        foreach ($imageData as $file => $fields) {
            /// $product->addImageToMediaGallery(Mage::getBaseDir('media') . DS . 'import' . trim($file), $fields);
            //chezky//
            if (!empty($importData[$fields[0] . '_label'])) {
                $image_label = $importData[$fields[0] . '_label'];
            } else {
                $image_label = '';
            }
            //import from url
            if (preg_match('%https?://[a-z0-9\-./]+\.(?:jpeg|png|jpg|gif)%i', $file)){
                $path_parts = pathinfo($file);
                $html_filename = DS . $path_parts['basename'];
                $fullpath = Mage::getBaseDir('media') . DS . 'import' . $html_filename;
                if(!file_exists($fullpath)) {
                        $ch = curl_init ($file);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
                        $rawdata= curl_exec($ch);
                        curl_close ($ch);
                        if(file_exists($fullpath)) {
                            unlink($fullpath);
                        }
                        $fp = fopen($fullpath,'x');
                        fwrite($fp, $rawdata);
                        fclose($fp);
                     }
                  $file = $html_filename;
            }
             //end import from url
            try {
                $this->_addImageToMediaGallery($product, Mage::getBaseDir('media') . DS . 'import' . trim($file), $fields, false, true, $image_label);
            } catch (Exception $e) {
                $message = Mage::helper('catalog')->__('Image and or Image label for %s not saved. REASON:[%s]', $rowidentifier, $e->getMessage());
                $this->importErrors($message);
            }
            ///
        }

        //chezky Now add each gallery image.
        if (!empty($importData['gallery'])) {

            //$importData['gallery'] should be a list of images to import seperated by ";"
            $images = explode(";", $importData['gallery']);
            $gallerylabelData = explode(';', $importData["gallery_labels"]);
            $counter = 0;
            foreach ($images as $image) {
                //Don't add field for gallery's

                if (isset($gallerylabelData[$counter])) {//check if user had inputted a label for this image
                    $image_label = $gallerylabelData[$counter];
                } else {
                    $image_label = "";
                }

                try {
                    //made second param null so it is not added as the main image, small or thumb. just added to the gallery
                    $this->_addImageToMediaGallery($product, Mage::getBaseDir('media') . DS . 'import' . trim($image), NULL, false, false, $image_label);
                    $counter = $counter + 1;
                } catch (Exception $e) {
                    $message = Mage::helper('catalog')->__('Gallery Image and or Gallery Image label for %s not saved. REASON:[%s]', $rowidentifier, $e->getMessage());
                    $this->importErrors($message);
                }
            }
        }
        ///till here images section

                                            $product->setIsMassupdate(true);
                                            $product->setExcludeUrlRewrite(true);

                                            $product->save();
                                            
                                            ///save category_positions
                                             if(isset($importData['categories_positions'])){
                                                    $error1 = $this->setProductCategoryPosition($productId, $importData['categories_positions']);

                                                    if($error1){
                                                        $this->importErrors($error);
                                                    }
                                                }
                                             if(isset($importData['category_position'])){
                                                    $error2 = $this->setProductAllCategoryPosition($productId, $importData['category_position']);

                                                    if($error2){
                                                        $this->importErrors($error);
                                                    }
                                                }
                                                ////add to configurable
                                                if(isset($importData['parent'])){
                                                    
                                                    $done = $this->addToParent($product->getId(), $importData['parent'] );
                                                    
                                                   
                                                    if($done !== 'done'){
                                                        $this->importErrors($done);
                                                    }

                                                }
                                             ///


        /* Add the custom options specified in the CSV import file */
                                            if(count($custom_options)) {
                 /* Remove existing custom options attached to the product */
                                                foreach ($product->getOptions() as $o) {
                                                    $o->getValueInstance()->deleteValue($o->getId());
                                                    $o->deletePrices($o->getId());
                                                    $o->deleteTitles($o->getId());
                                                    $o->delete();
                                                }
                                                foreach($custom_options as $option) {
                                                    try {
                                                        $opt = Mage::getModel('catalog/product_option');
                                                        $opt->setProduct($product);
                                                        $opt->addOption($option);
                                                        $opt->saveOptions();
                                                    }
                                                    catch (Exception $e) {}
                                                }
                                            }
                                            ///////end custom options import
        /* START OF SUPER ATTRIBUTE PRICING */
                                            if ($finalsuperattributetype == 'configurable') {
                                                if ($finalsuperattributepricing != "") {

                                                    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
                                                    $superProduct = Mage :: getModel ( 'catalog/product' )-> load ( $product -> getId ());
                                                    $superArray = $superProduct -> getTypeInstance ()-> getConfigurableAttributesAsArray ();

                                                    #print_r($superArray);

                                                    $SuperAttributePricingData = array();
                                                    $FinalSuperAttributeData = array();
                                                    $SuperAttributePricingData = explode('|',$finalsuperattributepricing);

                                                    $prefix = Mage::getConfig()->getNode('global/resources/db/table_prefix');
                                                    foreach( $superArray AS $key => $val ) {
                                                        #$x = 0 ;
                                                        foreach( $val[ 'values' ] AS $keyValues => $valValues ) {

                                                            foreach($SuperAttributePricingData as $singleattributeData) {
                                                                $FinalSuperAttributeData = explode(':',$singleattributeData);

                                                                if($FinalSuperAttributeData[0] == $superArray[$key][ 'values' ][$keyValues][ 'label' ]) {
                                                                    $insertPrice='INSERT into '.$prefix.'catalog_product_super_attribute_pricing (product_super_attribute_id, value_index, is_percent, pricing_value) VALUES
                                                         ("'.$superArray[$key][ 'values' ][$keyValues][ 'product_super_attribute_id' ].'", "'.$superArray[$key][ 'values' ][$keyValues][ 'value_index' ].'", "'.$FinalSuperAttributeData[2].'", "'.$FinalSuperAttributeData[1].'");';
                                                                    #echo "SQL2: " . $insertPrice;
                                                                    $adapter->query($insertPrice);
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
        /* END OF SUPER ATTRIBUTE PRICING */
                                            return true;
                                        }

    /**
     * Silently save product (import)
     *
     * @param array $
     * @return bool
     */
    public function saveRowSilently(array $importData)
    {
        try {
            $result = $this->saveRow($importData);
            return $result;
        }
        catch (Exception $e) {
            return false;
        }
    }
    
    ///import postion in categories
    
public function setProductCategoryPosition($product, $positions){
           
          $_positions = explode(';', $positions);
          $message ='';
          
          foreach($_positions as $_position){
              
              $pos_data = explode('|', $_position);
              
                $category = Mage::getModel('catalog/category')->load($pos_data[0]);
                
                $catPositions = $category->getProductsPosition();

                $catPositions[$product] = $pos_data[1];

                $category->setPostedProducts($catPositions); 
                if($category){
                 try
                 {
                        $category->save();
                 }
                    catch (Exception $e)
                    {
                        $message .= $e->getMessage();
                        
                    }
                }else{
                    $message .= 'Category ID '.$pos_data[0].' does not exist';
                }  
            }
            if($message !=''){
                    return $message;
            }
            
} 

public function setProductAllCategoryPosition($product, $position){
          
          $_product = Mage::getModel('catalog/product')->load($product);
          
          $categoryIds = $_product->getCategoryIds();
          
          $message ='';
          
          foreach($categoryIds as $category){
              
                $category = Mage::getModel('catalog/category')->load($category);
                
                $catPositions = $category->getProductsPosition();

                $catPositions[$product] = $position;

                $category->setPostedProducts($catPositions); 
                
                 try
                 {
                        $category->save();
                 }
                    catch (Exception $e)
                    {
                        $message .= $e->getMessage();
                        
                    }
            }
            if($message !=''){
                    return $message;
            }
            
} 
    /**
     * Process after import data
     * Init indexing process after catalog product import
     *
     */
public function finish()
{
/**
* Back compatibility event
*/
    Mage::dispatchEvent('catalog_product_import_after', array());

    $entity = new Varien_Object();
    Mage::getSingleton('index/indexer')->processEntityAction(
        $entity, self::ENTITY, Mage_Index_Model_Event::TYPE_SAVE
    );
}

/////functions for relations, grouped cofigurable etc.
protected function userCSVDataAsArray( $data )
{
    return explode( ',', str_replace( " ", "", $data ) );
}

protected function skusToIds( $userData, $product )
{
    $productIds = array();
    foreach ( $this -> userCSVDataAsArray( $userData ) as $oneSku ) {
        if ( ( $a_sku = ( int )$product -> getIdBySku( $oneSku ) ) > 0 ) {
            parse_str( "position=", $productIds[$a_sku] );
        }
    }
    return $productIds;
}
///////////

    /*
     * by chezky for category import from here http://www.magentocommerce.com/wiki/import-export_and_data_manipulation/how_to_import_category_structure_with_products_using_dataflow
     */
protected $_categoryCache = array();

protected $_categoryPositions = array();

protected function _addCategories($categories, $store)
{
    unset($this->_categoryPositions);
    $this->_categoryPositions = array();
    
        $rootId = $store->getRootCategoryId();
        if (!$rootId) {
            return array();
        }
        $rootPath = '1/' . $rootId;
        if (empty($this->_categoryCache[$store->getId()])) {
            $collection = Mage::getModel('catalog/category')->getCollection()
                    ->setStore($store)
                    ->addAttributeToSelect('name');
            $collection->getSelect()->where("path like '" . $rootPath . "/%'");

            foreach ($collection as $cat) {
                $pathArr = explode('/', $cat->getPath());
                $namePath = '';
                for ($i = 2, $l = sizeof($pathArr); $i < $l; $i++) {
                    $name = $collection->getItemById($pathArr[$i])->getName();
                    $namePath .= (empty($namePath) ? '' : '/') . trim($name);
                }
                $cat->setNamePath($namePath);
            }

            $cache = array();
            foreach ($collection as $cat) {
                $cache[strtolower($cat->getNamePath())] = $cat;
                $cat->unsNamePath();
            }
            $this->_categoryCache[$store->getId()] = $cache;
        }
        $cache = & $this->_categoryCache[$store->getId()];

        $catIds = array();
        foreach (explode(',', $categories) as $categoryPathStr) {
            $categoryPathStr = preg_replace('#\s*/\s*#', '/', trim($categoryPathStr));
        
            //check for positions
            if(strpos($categoryPathStr,':') !== false){
                 $_splitCat =  explode(':', $categoryPathStr);
                 $categoryPathStr = $_splitCat[0];       
            }
        
            if (!empty($cache[$categoryPathStr])) {
                $catIds[] = $cache[$categoryPathStr]->getId();
            
                if(!empty($_splitCat[1])){
                    $this->_categoryPositions[] = $cache[$categoryPathStr]->getId().'|'.$_splitCat[1];
                }
           
                continue;
            }
            $path = $rootPath;
            $namePath = '';
            foreach (explode('/', $categoryPathStr) as $catName) {
                $namePath .= (empty($namePath) ? '' : '/') . strtolower($catName);

                if (empty($cache[$namePath])) {
                    if (self::CREATE_CATEGORIES == 'yes') {
                        $cat = Mage::getModel('catalog/category')
                                ->setStoreId($store->getId())
                                ->setPath($path)
                                ->setName($catName)
                                ->setIsActive(0)
                                ->save();
                        $cache[$namePath] = $cat;
                    } else {

                        continue;
                    }
                }
                $catId = $cache[$namePath]->getId();
                $path .= '/' . $catId;
            }
            if ($catId) {
                
                if(!empty($_splitCat[1])){
                    $this->_categoryPositions[] = $catId.'|'.$_splitCat[1];
                }
                
                $catIds[] = $catId;
            }
        }
        return join(',', $catIds);
    }

    //till here by chezky categories

/**
*  by chezky import options
     * Updates the source of the attribute by the current new value in the XML column, and returns
     * the id of the newly created option.
     *
     * @param string $attribute_code
     * @param string $newOption
     * @return int|string New Option Id
     */
public function updateSourceAndReturnId($attribute_code,$newOption){
    $attribute_model        = Mage::getModel('eav/entity_attribute');
    $attribute_options_model= Mage::getModel('eav/entity_attribute_source_table') ;

    $attribute_code         = $attribute_model->getIdByCode('catalog_product', $attribute_code);
    $attribute              = $attribute_model->load($attribute_code);
    $attribute_table        = $attribute_options_model->setAttribute($attribute);
    try{
        $value['option'] = array(trim($newOption),trim($newOption));
        $result = array('value' => $value);
        $attribute->setData('option',$result);
        $attribute->save();
    }
    catch(Exception $e){}
    $options = $attribute_options_model->getAllOptions(false);
    foreach($options as $option)
    {
        if ($option['label'] == $newOption)
        {
            return $option['value'];
        }
    }
    return "";
}
//till here by chezky

/**
 * Edit tier prices
 *
 * Uses a pipe-delimited string of qty:price to set tiers for the product row and appends.
 * Removes if REMOVE is present.
 *
 * @todo Prevent duplicate tiers (by qty) being set
 * @internal Magento will save duplicate tiers; no enforcing unique tiers by qty, so we have to do this manually
 * @param Mage_Catalog_Model_Product $product Current product row
 * @param string $tier_prices_field Pipe-separated in the form of qty:price (e.g. 0=250=12.75|0=500=12.00)
 */
private function _editTierPrices(&$product, $tier_prices_field = false)
{
    if (($tier_prices_field) && !empty($tier_prices_field)) {

        if(trim($tier_prices_field) == 'REMOVE'){

            $product->setTierPrice(array());

        } else {
            //get current product tier prices
            $existing_tps = $product->getTierPrice();
            if(!is_array($existing_tps)) {
                $existing_tps = array();
            }

            $etp_lookup = array();
            //make a lookup array to prevent dup tiers by qty
            foreach($existing_tps as $key => $etp){
                $etp_lookup[intval($etp['price_qty'])] = $key;
            }

            //parse incoming tier prices string
            $incoming_tierps = explode('|',$tier_prices_field);
            $tps_toAdd = array();
            $tierpricecount=0;
            foreach($incoming_tierps as $tier_str){
                //echo "t: " . $tier_str;
                if (empty($tier_str)) continue;

                $tmp = array();
                $tmp = explode('=',$tier_str);

                if ($tmp[1] == 0 && $tmp[2] == 0) continue;
                //echo ('adding tier');
                //print_r($tmp);
                $tps_toAdd[$tierpricecount] = array(
                    'website_id' => 0, // !!!! this is hard-coded for now
                    'cust_group' => $tmp[0], // !!! so is this
                    'price_qty' => $tmp[1],
                    'price' => $tmp[2],
                    'delete' => ''
                );

                //drop any existing tier values by qty
                if(isset($etp_lookup[intval($tmp[1])])){
                    unset($existing_tps[$etp_lookup[intval($tmp[1])]]);
                }
                $tierpricecount++;
            }

            //combine array
            $tps_toAdd =  array_merge($existing_tps, $tps_toAdd);

            //save it
            $product->setTierPrice($tps_toAdd);
        }

    }
}

//Import Group Pricing - 2014
 private function _editGroupPrices(&$product, $group_prices_field = false)
    {
        
        if (($group_prices_field) && !empty($group_prices_field)) {
            
            if(trim($group_prices_field) == 'REMOVE'){
            
                $product->setGroupPrice(array());
            
            } else {
                //parse incoming group prices string
                $incoming_groupps = explode('|',$group_prices_field);

                $tps_toAdd = array();
                foreach($incoming_groupps as $group_str){
                    if (empty($group_str)) continue;
                    
                    $tmp = array();
                    $tmp = explode('=',$group_str);
                    
                    if ($tmp[1] == 0 && $tmp[2] == 0) continue;
                    
                    $tps_toAdd[$tmp[1]] = array(
                                       'website_id' => $tmp[0],
                                        'cust_group' => $tmp[1],
                                        'price' => $tmp[2],
                                        'delete' => ''
                                    );
                }
                //save it
                $product->setGroupPrice($tps_toAdd);
            }
            
        }
        
    }
    
    
    protected function _removeFile( $file )
    {
        if ( file_exists( $file ) ) {
            $ext = substr(strrchr($file, '.'), 1);
            if( strlen( $ext ) == 4 ) {
                if ( unlink( $file ) ) {
                    return true;
                }
            }
        }
        return false;
    }
    public function _addImageToMediaGallery($product,$file, $mediaAttribute=null, $move=false, $exclude=true,$label)
    {
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);;
        if (!isset($attributes['media_gallery'])) {
            return $this;
        }
        $mediaGalleryAttribute = $attributes['media_gallery'];
    /* @var $mediaGalleryAttribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $mediaGalleryAttribute->getBackend()->addImage($product, $file, $mediaAttribute, $move, $exclude, $label);
        return $this;
    }
    
    //find products with attribute parent
    public function _findChildren($parentSKU){
        $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect('parent')
                ->addFieldToFilter(array(array('attribute'=>'parent', 'eq'=> $parentSKU)));
        $productIds = array();
        foreach($collection as $product){
            $productIds[]=$product->getId();
        }
        $productIds = implode(',',$productIds);

        return $productIds;
    }
    
     public function addToParent($childProduct, $mainProduct )
    {
         $parentProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $mainProduct);
         if($parentProduct && $parentProduct->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE){
            $children = array();
            
            $old = $parentProduct->getTypeInstance()->getUsedProductIds();
                            
            if(count($old)>0){
                $children = array_merge($children,$old);
                $children[] = $childProduct;
                
            }else{

                $children[] = $childProduct;
            }
            
            try{
            Mage::getResourceSingleton('catalog/product_type_configurable')->saveProducts($parentProduct, $children);
             } catch (Exception $e) {
                return 'Cannot assign to parent '.$mainProduct.', reason: '.$e->getMessage();
            }
            return 'done';
         }elseif($parentProduct && $parentProduct->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED){
             $products_links = Mage::getModel('catalog/product_link_api');
             $done = $products_links->assign ("grouped",$parentProduct->getId(),$childProduct);
             if($done){
                 return 'done';
             }else{
                 return 'Parent product '.$mainProduct.' not found';
             }
         }
         else{
             return 'Parent product '.$mainProduct.' not found';
         }
   }
   
   public function getAssociatedProductIds($groupedProductId)
    {
        $coreResource = Mage::getSingleton('core/resource');
        $conn = $coreResource->getConnection('core_read');
        $select = $conn->select()
            ->from($coreResource->getTableName('catalog/product_relation'), array('child_id'))
            ->where('parent_id = ?', $groupedProductId);

        return $conn->fetchCol($select);
    }
}
