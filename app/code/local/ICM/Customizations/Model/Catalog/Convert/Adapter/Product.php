<?php

/**
 * ICM customizations
 *
 * Extended Dataflow product import (System > Import/Export > Dataflow - Profiles, "Import all products" etc.).
 * Replaces the old full copy of app/code/local/Mage/Catalog/Model/Convert/Adapter/Product.php (a Magento 1.4 file
 * with many additions). On top of the core import this supports these extra CSV columns:
 *
 *  - status = DELETE                      delete the product (and its image files)
 *  - config_attributes, associated,       create/update configurable products
 *    find_children, super_attribute_pricing
 *  - parent                               add the row's product to a configurable/grouped parent (by SKU)
 *  - related, upsell, crosssell, grouped  product links (comma separated SKUs)
 *  - tier_prices                          "group=qty=price|..." or REMOVE
 *  - group_prices                         "website=group=price|..." or REMOVE
 *  - categories                           category name paths ("Kitchen/Pots:5,Sale"), optional ":position"
 *  - category_position                    position in all of the product's categories
 *  - Title:type:is_required:sort_order    custom options columns ("value:price_type:price:sku:sort|...")
 *  - image/small_image/thumbnail          may be a http(s) URL, downloaded into media/import
 *  - *_label                              image labels
 *  - remove_all_images = yes              clear the gallery before adding the row's images
 *  - gallery, gallery_labels              extra gallery images, ";" separated
 *
 * Row errors are logged to var/log/importErrors.log.
 */
class ICM_Customizations_Model_Catalog_Convert_Adapter_Product extends Mage_Catalog_Model_Convert_Adapter_Product
{
    public const CREATE_CATEGORIES = 'no';
    public const CREATE_OPTIONS    = 'no';
    public const SKIP_ERRORS       = 'no';
    public const LOG_ERRORS        = 'yes';

    /**
     * Category name path cache per store
     *
     * @var array
     */
    protected $_categoryCache = [];

    /**
     * "category_id|position" pairs collected from the categories column
     *
     * @var array
     */
    protected $_categoryPositions = [];

    /**
     * @param  string $message
     * @throws Mage_Core_Exception
     */
    public function importErrors($message)
    {
        if (self::LOG_ERRORS == 'yes') {
            Mage::log(strip_tags($message), null, 'importErrors.log');
        }

        if (self::SKIP_ERRORS == 'yes') {
            Mage::throwException('Item Skipped: ' . $message);
        }
    }

    /**
     * Save product (import)
     *
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function saveRow(array $importData)
    {
        $product = $this->getProductModel()
            ->reset();

        // identify the row in error messages
        $rowIdentifier = '';
        if (!empty($importData['sku'])) {
            $rowIdentifier = '<b>SKU:</b> ' . $importData['sku'];
        } elseif (!empty($importData['name'])) {
            $rowIdentifier = '<b>Name:</b> ' . $importData['name'];
        }

        if (empty($importData['store'])) {
            if (!is_null($this->getBatchParams('store'))) {
                $store = $this->getStoreById($this->getBatchParams('store'));
            } else {
                $message = Mage::helper('catalog')->__('Skipping import row with "%s", required field "%s" is not defined.', $rowIdentifier, 'store');
                Mage::throwException($message);
            }
        } else {
            $store = $this->getStoreByCode($importData['store']);
        }

        if ($store === false) {
            $message = Mage::helper('catalog')->__('Skipping import row with "%s", store "%s" field does not exist.', $rowIdentifier, $importData['store']);
            Mage::throwException($message);
        }

        if (empty($importData['sku'])) {
            $message = Mage::helper('catalog')->__('Skipping import row with "%s", required field "%s" is not defined.', $rowIdentifier, 'sku');
            Mage::throwException($message);
        }

        $product->setStoreId($store->getId());
        $productId = $product->getIdBySku($importData['sku']);

        // fix for duplicating attributes error in configurable code
        $isNew = true;

        if ($productId) {
            $product->load($productId);
            $isNew = false;

            // delete specified products
            if (isset($importData['status']) && $importData['status'] == 'DELETE') {
                $mediaConfig = Mage::getSingleton('catalog/product_media_config');
                foreach (['image', 'small_image', 'thumbnail'] as $imageAttribute) {
                    if ($product->getData($imageAttribute)) {
                        $this->_removeFile($mediaConfig->getMediaPath($product->getData($imageAttribute)));
                    }
                }

                $mediaGallery = $product->getData('media_gallery');
                if (!empty($mediaGallery['images']) && is_array($mediaGallery['images'])) {
                    foreach ($mediaGallery['images'] as $image) {
                        $this->_removeFile($mediaConfig->getMediaPath($image['file']));
                    }
                }

                $product->delete();
                return true;
            }
        } else {
            $productTypes = $this->getProductTypes();
            $productAttributeSets = $this->getProductAttributeSets();

            /**
             * Check product define type
             */
            if (empty($importData['type']) || !isset($productTypes[strtolower($importData['type'])])) {
                $value = $importData['type'] ?? '';
                $message = Mage::helper('catalog')->__('Skip import row with %s, "%s" is not valid value for field "%s" for new products', $rowIdentifier, $value, 'type');
                Mage::throwException($message);
            }

            $product->setTypeId($productTypes[strtolower($importData['type'])]);
            /**
             * Check product define attribute set
             */
            if (empty($importData['attribute_set']) || !isset($productAttributeSets[$importData['attribute_set']])) {
                $value = $importData['attribute_set'] ?? '';
                $message = Mage::helper('catalog')->__('Skip import row with %s, "%s" is not valid value for field "%s" for new products', $rowIdentifier, $value, 'attribute_set');
                Mage::throwException($message);
            }

            $product->setAttributeSetId($productAttributeSets[$importData['attribute_set']]);

            foreach ($this->_requiredFields as $field) {
                $attribute = $this->getAttribute($field);
                if (!isset($importData[$field]) && $attribute && $attribute->getIsRequired()) {
                    $message = Mage::helper('catalog')->__('Skipping import row with %s, required field "%s" for new products is not defined.', $rowIdentifier, $field);
                    Mage::throwException($message);
                }
            }
        }

        // process row with media data only
        if (isset($importData['_media_image']) && strlen($importData['_media_image'])) {
            $this->saveImageDataRow($product, $importData);
            return true;
        }

        $this->setProductTypeInstance($product);

        // configurable products
        $finalSuperAttributeType = '';
        $finalSuperAttributePricing = '';
        if (isset($importData['type']) && $importData['type'] == 'configurable') {
            if (empty($importData['config_attributes'])) {
                $message = Mage::helper('catalog')->__('Skipping import row with %s, required field "config_attributes" required for configurable products is not defined.', $rowIdentifier);
                Mage::throwException($message);
            }

            $finalSuperAttributeType = $importData['type'];
            if (isset($importData['super_attribute_pricing'])) {
                $finalSuperAttributePricing = $importData['super_attribute_pricing'];
            }

            $product->setCanSaveConfigurableAttributes(true);
            $configAttributeCodes = $this->userCSVDataAsArray($importData['config_attributes']);
            $usingAttributeIds = [];

            foreach ($configAttributeCodes as $attributeCode) {
                $attribute = $product->getResource()->getAttribute($attributeCode);
                if (!$attribute) {
                    $message = Mage::helper('catalog')->__('Skipping import row with %s, attribute "%s" in the "config_attributes" column is not an attribute.', $rowIdentifier, $attributeCode);
                    Mage::throwException($message);
                }

                if ($product->getTypeInstance()->canUseAttribute($attribute)) {
                    // only for new products, otherwise the super attributes get duplicated
                    if ($isNew) {
                        $usingAttributeIds[] = $attribute->getAttributeId();
                    }
                } else {
                    $message = Mage::helper('catalog')->__('Skipping import row with %s, attribute "%s" in the "config_attributes" column is not a configurable attribute.', $rowIdentifier, $attributeCode);
                    Mage::throwException($message);
                }
            }

            if (!empty($usingAttributeIds)) {
                $product->getTypeInstance()->setUsedProductAttributeIds($usingAttributeIds);
                $attributesArray = $product->getTypeInstance()->getConfigurableAttributesAsArray();
                foreach ($attributesArray as $key => $attributeValue) {
                    $attributesArray[$key]['label'] = $attributeValue['frontend_label'];
                }

                $product->setConfigurableAttributesData($attributesArray);
                $product->setCanSaveConfigurableAttributes(true);
                $product->setCanSaveCustomOptions(true);
            }

            if (isset($importData['associated'])) {
                $product->setConfigurableProductsData($this->skusToIds($importData['associated'], $product));
            } elseif (isset($importData['find_children']) && strtolower($importData['find_children']) == 'yes') {
                $childrenFound = $this->_findChildren($importData['sku']);
                if ($childrenFound) {
                    $product->setConfigurableProductsData(array_fill_keys($childrenFound, []));
                }
            }
        }

        // product relations
        if (isset($importData['related'])) {
            $linkIds = $this->skusToIds($importData['related'], $product);
            if (!empty($linkIds)) {
                $product->setRelatedLinkData($linkIds);
            }
        }

        if (isset($importData['upsell'])) {
            $linkIds = $this->skusToIds($importData['upsell'], $product);
            if (!empty($linkIds)) {
                $product->setUpSellLinkData($linkIds);
            }
        }

        if (isset($importData['crosssell'])) {
            $linkIds = $this->skusToIds($importData['crosssell'], $product);
            if (!empty($linkIds)) {
                $product->setCrossSellLinkData($linkIds);
            }
        }

        if (isset($importData['grouped'])) {
            $linkIds = $this->skusToIds($importData['grouped'], $product);
            if (!empty($linkIds)) {
                $product->setGroupedLinkData($linkIds);
            }
        }

        if (!empty($importData['tier_prices'])) {
            $this->_editTierPrices($product, $importData['tier_prices']);
        }

        if (!empty($importData['group_prices'])) {
            $this->_editGroupPrices($product, $importData['group_prices']);
        }

        if (isset($importData['category_ids'])) {
            $product->setCategoryIds($importData['category_ids']);
        }

        // categories by name path
        if (isset($importData['categories']) && strlen($importData['categories'])) {
            $categoryStore = false;
            if (isset($importData['store']) && $importData['store'] != 'admin') {
                $categoryStore = $this->getStoreByCode($importData['store']);
            }

            if (!$categoryStore) {
                $categoryStore = Mage::app()->getDefaultStoreView();
            }

            $categoryIds = $this->_addCategories($importData['categories'], $categoryStore);
            if ($categoryIds) {
                $product->setCategoryIds($categoryIds);
                if (!empty($this->_categoryPositions)) {
                    $importData['categories_positions'] = implode(';', $this->_categoryPositions);
                }
            } else {
                $message = Mage::helper('catalog')->__('%s, one or more of the category/ies "%s" not found', $rowIdentifier, $importData['categories']);
                $this->importErrors($message);
            }
        }

        foreach ($this->_ignoreFields as $field) {
            if (isset($importData[$field])) {
                unset($importData[$field]);
            }
        }

        if ($store->getId() != 0) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = [];
            }

            if (!in_array($store->getWebsiteId(), $websiteIds)) {
                $websiteIds[] = $store->getWebsiteId();
            }

            $product->setWebsiteIds($websiteIds);
        }

        if (isset($importData['websites'])) {
            $websiteIds = $product->getWebsiteIds();
            if (!is_array($websiteIds) || !$store->getId()) {
                $websiteIds = [];
            }

            $websiteCodes = explode(',', $importData['websites']);
            foreach ($websiteCodes as $websiteCode) {
                try {
                    $website = Mage::app()->getWebsite(trim($websiteCode));
                    if (!in_array($website->getId(), $websiteIds)) {
                        $websiteIds[] = $website->getId();
                    }
                } catch (Exception) {
                }
            }

            $product->setWebsiteIds($websiteIds);
            unset($websiteIds);
        }

        $customOptions = [];
        $imageFields = ['image', 'small_image', 'thumbnail'];

        foreach ($importData as $field => $value) {
            if (in_array($field, $this->_inventoryFields)) {
                continue;
            }

            if (in_array($field, $imageFields)) {
                continue;
            }

            if (is_null($value)) {
                continue;
            }

            $attribute = $this->getAttribute($field);
            if (!$attribute) {
                // custom options column: "Title:type:is_required:sort_order"
                if (str_contains((string) $field, ':') && strlen((string) $value)) {
                    $this->_collectCustomOption($customOptions, (string) $field, (string) $value);
                }

                continue;
            }

            $isArray = false;
            $setValue = $value;

            if ($attribute->getFrontendInput() == 'multiselect') {
                $value = explode(self::MULTI_DELIMITER, $value);
                $isArray = true;
                $setValue = [];
            }

            if ($value && $attribute->getBackendType() == 'decimal') {
                $setValue = $this->getNumber($value);
            }

            if ($attribute->usesSource()) {
                if (self::CREATE_OPTIONS == 'yes') {
                    $setValue = $this->_getOptionValueCreatingMissing($attribute, $field, $value, $isArray);
                } else {
                    $options = $attribute->getSource()->getAllOptions(false);
                    $matchFound = false;
                    $missingValues = '';
                    $fieldEmpty = false;
                    if ($isArray) {
                        if ($value[0] == '') {
                            $fieldEmpty = true;
                        }

                        foreach ($options as $item) {
                            if (in_array($item['label'], $value)) {
                                $setValue[] = $item['value'];
                                $matchFound = true;
                            }
                        }

                        if ($matchFound) {
                            $labels = array_column($options, 'label');
                            foreach ($value as $valueItem) {
                                if (!in_array($valueItem, $labels)) {
                                    $missingValues .= $valueItem . ' ';
                                    $matchFound = false;
                                }
                            }
                        }
                    } else {
                        if ($value == '') {
                            $fieldEmpty = true;
                        }

                        $setValue = false;
                        foreach ($options as $item) {
                            if (is_array($item['value'])) {
                                foreach ($item['value'] as $subValue) {
                                    if (isset($subValue['value']) && $subValue['value'] == $value) {
                                        $setValue = $value;
                                        $matchFound = true;
                                    }
                                }
                            } elseif ($item['label'] == $value) {
                                $setValue = $item['value'];
                                $matchFound = true;
                            }
                        }
                    }

                    if (!$matchFound && !$fieldEmpty) {
                        $missing = $missingValues !== '' ? $missingValues : (is_array($value) ? implode(', ', $value) : $value);
                        $message = Mage::helper('catalog')->__('Import row with %s, "%s" option/s for attribute "%s" not found in system', $rowIdentifier, $missing, $field);
                        $this->importErrors($message);
                    }
                }
            }

            $product->setData($field, $setValue);
        }

        if (!$product->getVisibility()) {
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        }

        $stockData = [];
        $inventoryFields = $this->_inventoryFieldsProductTypes[$product->getTypeId()] ?? [];
        foreach ($inventoryFields as $field) {
            if (isset($importData[$field])) {
                $stockData[$field] = in_array($field, $this->_toNumber) ? $this->getNumber($importData[$field]) : $importData[$field];
            }
        }

        $product->setStockData($stockData);

        // images
        $imageData = [];
        foreach ($imageFields as $field) {
            if (!empty($importData[$field]) && $importData[$field] != 'no_selection') {
                if (!isset($imageData[$importData[$field]])) {
                    $imageData[$importData[$field]] = [];
                }

                $imageData[$importData[$field]][] = $field;
            }
        }

        // remove all images of the product before adding the new ones
        if (isset($importData['remove_all_images']) && $importData['remove_all_images'] == 'yes') {
            $attributes = $product->getTypeInstance(true)->getSetAttributes($product);
            if (isset($attributes['media_gallery'])) {
                $gallery = $attributes['media_gallery'];
                $galleryData = $product->getMediaGallery();
                if (!empty($galleryData['images']) && is_array($galleryData['images'])) {
                    foreach ($galleryData['images'] as $image) {
                        if ($gallery->getBackend()->getImage($product, $image['file'])) {
                            $gallery->getBackend()->removeImage($product, $image['file']);
                        }
                    }
                }
            }
        }

        foreach ($imageData as $file => $fields) {
            $imageLabel = $importData[$fields[0] . '_label'] ?? '';
            $file = $this->_downloadImportImage((string) $file);
            try {
                $this->_addImageToMediaGallery($product, Mage::getBaseDir('media') . DS . 'import' . trim($file), $fields, false, true, $imageLabel);
            } catch (Exception $e) {
                $message = Mage::helper('catalog')->__('Image and or Image label for %s not saved. REASON:[%s]', $rowIdentifier, $e->getMessage());
                $this->importErrors($message);
            }
        }

        // additional gallery images, ";" separated
        if (!empty($importData['gallery'])) {
            $images = explode(';', $importData['gallery']);
            $galleryLabels = explode(';', $importData['gallery_labels'] ?? '');
            foreach ($images as $counter => $image) {
                $imageLabel = $galleryLabels[$counter] ?? '';
                try {
                    // no media attribute, so it is only added to the gallery
                    $this->_addImageToMediaGallery($product, Mage::getBaseDir('media') . DS . 'import' . trim($image), null, false, false, $imageLabel);
                } catch (Exception $e) {
                    $message = Mage::helper('catalog')->__('Gallery Image and or Gallery Image label for %s not saved. REASON:[%s]', $rowIdentifier, $e->getMessage());
                    $this->importErrors($message);
                }
            }
        }

        $product->setIsMassupdate(true);
        $product->setExcludeUrlRewrite(true);

        $product->save();

        // Store affected products ids
        $this->_addAffectedEntityIds($product->getId());

        // category positions
        if (isset($importData['categories_positions'])) {
            $error = $this->setProductCategoryPosition($product->getId(), $importData['categories_positions']);
            if ($error) {
                $this->importErrors($error);
            }
        }

        if (isset($importData['category_position'])) {
            $error = $this->setProductAllCategoryPosition($product->getId(), $importData['category_position']);
            if ($error) {
                $this->importErrors($error);
            }
        }

        // add to configurable/grouped parent
        if (!empty($importData['parent'])) {
            $done = $this->addToParent($product->getId(), $importData['parent']);
            if ($done !== 'done') {
                $this->importErrors($done);
            }
        }

        // replace the custom options with the ones from the import file
        if (count($customOptions)) {
            foreach ($product->getOptions() as $option) {
                $option->getValueInstance()->deleteValue($option->getId());
                $option->deletePrices($option->getId());
                $option->deleteTitles($option->getId());
                $option->delete();
            }

            foreach ($customOptions as $option) {
                try {
                    $productOption = Mage::getModel('catalog/product_option');
                    $productOption->setProduct($product);
                    $productOption->addOption($option);
                    $productOption->saveOptions();
                } catch (Exception $e) {
                    $this->importErrors($rowIdentifier . ' custom option not saved: ' . $e->getMessage());
                }
            }
        }

        // super attribute pricing: "Label:price:is_percent|..."
        if ($finalSuperAttributeType == 'configurable' && $finalSuperAttributePricing != '') {
            $this->_saveSuperAttributePricing($product, $finalSuperAttributePricing);
        }

        return true;
    }

    /**
     * Collect one custom options column into $customOptions
     *
     * @param string $field "Title:type:is_required:sort_order"
     * @param string $value "value:price_type:price:sku:sort_order|..."
     */
    protected function _collectCustomOption(array &$customOptions, $field, $value)
    {
        $values = explode('|', $value);
        $fieldParts = array_pad(explode(':', $field), 4, '');
        [$title, $type, $isRequired, $sortOrder] = $fieldParts;
        $title = ucfirst(str_replace('_', ' ', $title));
        $customOptions[] = [
            'is_delete'      => 0,
            'title'          => $title,
            'previous_group' => '',
            'previous_type'  => '',
            'type'           => $type,
            'is_require'     => $isRequired,
            'sort_order'     => $sortOrder,
            'values'         => [],
        ];
        $last = count($customOptions) - 1;

        foreach ($values as $optionValue) {
            $parts = explode(':', $optionValue);
            $valueTitle = $parts[0];
            $priceType = $parts[1] ?? 'fixed';
            $price = $parts[2] ?? 0;
            $sku = $parts[3] ?? '';
            $valueSortOrder = $parts[4] ?? 0;

            switch ($type) {
                case 'file':
                    break;
                case 'field':
                case 'area':
                    $customOptions[$last]['max_characters'] = $valueSortOrder;
                    // no break
                case 'date':
                case 'date_time':
                case 'time':
                    $customOptions[$last]['price_type'] = $priceType;
                    $customOptions[$last]['price'] = $price;
                    $customOptions[$last]['sku'] = $sku;
                    break;
                case 'drop_down':
                case 'radio':
                case 'checkbox':
                case 'multiple':
                default:
                    $customOptions[$last]['values'][] = [
                        'is_delete'      => 0,
                        'title'          => $valueTitle,
                        'option_type_id' => -1,
                        'price_type'     => $priceType,
                        'price'          => $price,
                        'sku'            => $sku,
                        'sort_order'     => $valueSortOrder,
                    ];
                    break;
            }
        }
    }

    /**
     * Find option ids by label, creating the options that don't exist yet (CREATE_OPTIONS = yes)
     *
     * @param  Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param  string                                   $field
     * @param  array|string                             $value
     * @param  bool                                     $isArray
     * @return mixed
     */
    protected function _getOptionValueCreatingMissing($attribute, $field, $value, $isArray)
    {
        $options = $attribute->getSource()->getAllOptions(false);
        if ($isArray) {
            $setValue = [];
            $found = [];
            foreach ($options as $item) {
                if (in_array($item['label'], $value)) {
                    $setValue[] = trim($item['value']);
                    $found[] = $item['label'];
                }
            }

            foreach ($value as $subValue) {
                if (!in_array($subValue, $found)) {
                    $setValue[] = $this->updateSourceAndReturnId($field, $subValue);
                    $found[] = $subValue;
                }
            }

            return $setValue;
        }

        foreach ($options as $item) {
            if ($item['label'] == $value) {
                return $item['value'];
            }
        }

        return $this->updateSourceAndReturnId($field, $value);
    }

    /**
     * Download an image given as URL into media/import and return its path relative to media/import
     *
     * @param  string $file
     * @return string
     */
    protected function _downloadImportImage($file)
    {
        if (!preg_match('#^https?://[a-z0-9\-./_%]+\.(?:jpeg|png|jpg|gif|webp)$#i', trim($file))) {
            return $file;
        }

        $fileName = DS . basename(parse_url(trim($file), PHP_URL_PATH));
        $fullPath = Mage::getBaseDir('media') . DS . 'import' . $fileName;
        if (!file_exists($fullPath)) {
            $ch = curl_init(trim($file));
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $rawData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($rawData !== false && $httpCode == 200) {
                @mkdir(dirname($fullPath), 0775, true);
                file_put_contents($fullPath, $rawData);
            }
        }

        return $fileName;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @param  string                     $file
     * @param  null|array|string          $mediaAttribute
     * @param  bool                       $move
     * @param  bool                       $exclude
     * @param  string                     $label
     * @return $this
     */
    public function _addImageToMediaGallery($product, $file, $mediaAttribute = null, $move = false, $exclude = true, $label = '')
    {
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);
        if (!isset($attributes['media_gallery'])) {
            return $this;
        }

        /** @var Mage_Catalog_Model_Product_Attribute_Backend_Media $backend */
        $backend = $attributes['media_gallery']->getBackend();
        $addedFile = $backend->addImage($product, $file, $mediaAttribute, $move, $exclude);
        if ($addedFile && $label !== '') {
            $backend->updateImage($product, $addedFile, ['label' => $label]);
        }

        return $this;
    }

    /**
     * Insert catalog_product_super_attribute_pricing rows for a configurable product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $pricing "Label:price:is_percent|..."
     */
    protected function _saveSuperAttributePricing($product, $pricing)
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/product_super_attribute_pricing');
        $superProduct = Mage::getModel('catalog/product')->load($product->getId());
        $superArray = $superProduct->getTypeInstance()->getConfigurableAttributesAsArray();
        $pricingData = explode('|', $pricing);

        foreach ($superArray as $superAttribute) {
            foreach ($superAttribute['values'] as $superValue) {
                foreach ($pricingData as $singleAttributeData) {
                    $data = array_pad(explode(':', $singleAttributeData), 3, 0);
                    if ($data[0] == $superValue['label']) {
                        $adapter->insert($table, [
                            'product_super_attribute_id' => $superValue['product_super_attribute_id'],
                            'value_index'                => $superValue['value_index'],
                            'is_percent'                 => (int) $data[2],
                            'pricing_value'              => $data[1],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Set product position in categories, "category_id|position;..."
     *
     * @param  int         $productId
     * @param  string      $positions
     * @return null|string error message
     */
    public function setProductCategoryPosition($productId, $positions)
    {
        $message = '';
        foreach (explode(';', $positions) as $position) {
            $positionData = array_pad(explode('|', $position), 2, 0);
            $category = Mage::getModel('catalog/category')->load($positionData[0]);
            if (!$category->getId()) {
                $message .= 'Category ID ' . $positionData[0] . ' does not exist';
                continue;
            }

            $catPositions = $category->getProductsPosition();
            $catPositions[$productId] = $positionData[1];
            $category->setPostedProducts($catPositions);
            try {
                $category->save();
            } catch (Exception $e) {
                $message .= $e->getMessage();
            }
        }

        return $message !== '' ? $message : null;
    }

    /**
     * Set product position in all of its categories
     *
     * @param  int         $productId
     * @param  int|string  $position
     * @return null|string error message
     */
    public function setProductAllCategoryPosition($productId, $position)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        $message = '';
        foreach ($product->getCategoryIds() as $categoryId) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            $catPositions = $category->getProductsPosition();
            $catPositions[$productId] = $position;
            $category->setPostedProducts($catPositions);
            try {
                $category->save();
            } catch (Exception $e) {
                $message .= $e->getMessage();
            }
        }

        return $message !== '' ? $message : null;
    }

    /**
     * @param  string $data
     * @return array
     */
    protected function userCSVDataAsArray($data)
    {
        return explode(',', str_replace(' ', '', (string) $data));
    }

    /**
     * @param  string                     $userData comma separated SKUs
     * @param  Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function skusToIds($userData, $product)
    {
        $productIds = [];
        foreach ($this->userCSVDataAsArray($userData) as $oneSku) {
            if (($id = (int) $product->getIdBySku($oneSku)) > 0) {
                $productIds[$id] = ['position' => ''];
            }
        }

        return $productIds;
    }

    /**
     * Get category ids by name paths ("Parent/Child:position,Other")
     *
     * @param  string                $categories
     * @param  Mage_Core_Model_Store $store
     * @return string comma separated ids
     */
    protected function _addCategories($categories, $store)
    {
        $this->_categoryPositions = [];

        $rootId = $store->getRootCategoryId();
        if (!$rootId) {
            return '';
        }

        $rootPath = '1/' . $rootId;
        if (empty($this->_categoryCache[$store->getId()])) {
            $collection = Mage::getModel('catalog/category')->getCollection()
                ->setStore($store)
                ->addAttributeToSelect('name');
            $collection->getSelect()->where('path like ?', $rootPath . '/%');

            foreach ($collection as $cat) {
                $pathArr = explode('/', $cat->getPath());
                $namePath = '';
                for ($i = 2, $l = count($pathArr); $i < $l; $i++) {
                    $item = $collection->getItemById($pathArr[$i]);
                    $name = $item ? $item->getName() : '';
                    $namePath .= (empty($namePath) ? '' : '/') . trim((string) $name);
                }

                $cat->setNamePath($namePath);
            }

            $cache = [];
            foreach ($collection as $cat) {
                $cache[strtolower($cat->getNamePath())] = $cat;
                $cat->unsNamePath();
            }

            $this->_categoryCache[$store->getId()] = $cache;
        }

        $cache = &$this->_categoryCache[$store->getId()];

        $catIds = [];
        foreach (explode(',', $categories) as $categoryPathStr) {
            $categoryPathStr = preg_replace('#\s*/\s*#', '/', trim($categoryPathStr));
            $categoryPosition = null;

            // check for positions
            if (str_contains($categoryPathStr, ':')) {
                [$categoryPathStr, $categoryPosition] = explode(':', $categoryPathStr, 2);
            }

            $lookup = strtolower($categoryPathStr);
            if (!empty($cache[$lookup])) {
                $catIds[] = $cache[$lookup]->getId();
                if (!empty($categoryPosition)) {
                    $this->_categoryPositions[] = $cache[$lookup]->getId() . '|' . $categoryPosition;
                }

                continue;
            }

            $path = $rootPath;
            $namePath = '';
            $catId = null;
            foreach (explode('/', $categoryPathStr) as $catName) {
                $namePath .= (empty($namePath) ? '' : '/') . strtolower($catName);

                if (empty($cache[$namePath])) {
                    if (self::CREATE_CATEGORIES != 'yes') {
                        continue;
                    }

                    $cat = Mage::getModel('catalog/category')
                        ->setStoreId($store->getId())
                        ->setPath($path)
                        ->setName($catName)
                        ->setIsActive(0)
                        ->save();
                    $cache[$namePath] = $cat;
                }

                $catId = $cache[$namePath]->getId();
                $path .= '/' . $catId;
            }

            if ($catId) {
                if (!empty($categoryPosition)) {
                    $this->_categoryPositions[] = $catId . '|' . $categoryPosition;
                }

                $catIds[] = $catId;
            }
        }

        return implode(',', $catIds);
    }

    /**
     * Create a new option for the attribute and return its id
     *
     * @param  string     $attributeCode
     * @param  string     $newOption
     * @return int|string
     */
    public function updateSourceAndReturnId($attributeCode, $newOption)
    {
        $attributeModel = Mage::getModel('eav/entity_attribute');
        $attributeOptionsModel = Mage::getModel('eav/entity_attribute_source_table');

        $attributeId = $attributeModel->getIdByCode('catalog_product', $attributeCode);
        $attribute = $attributeModel->load($attributeId);
        $attributeOptionsModel->setAttribute($attribute);
        try {
            $value['option'] = [trim($newOption), trim($newOption)];
            $attribute->setData('option', ['value' => $value]);
            $attribute->save();
        } catch (Exception $e) {
            $this->importErrors($e->getMessage());
        }

        foreach ($attributeOptionsModel->getAllOptions(false) as $option) {
            if ($option['label'] == $newOption) {
                return $option['value'];
            }
        }

        return '';
    }

    /**
     * Edit tier prices, "cust_group=qty=price|..." (appended, same qty replaced) or REMOVE
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $tierPricesField
     */
    protected function _editTierPrices($product, $tierPricesField)
    {
        if (trim($tierPricesField) == 'REMOVE') {
            $product->setTierPrice([]);
            return;
        }

        $existing = $product->getTierPrice();
        if (!is_array($existing)) {
            $existing = [];
        }

        $lookup = [];
        foreach ($existing as $key => $tierPrice) {
            $lookup[(int) $tierPrice['price_qty']] = $key;
        }

        $toAdd = [];
        foreach (explode('|', $tierPricesField) as $tierStr) {
            if (empty($tierStr)) {
                continue;
            }

            $tmp = array_pad(explode('=', $tierStr), 3, 0);
            if ($tmp[1] == 0 && $tmp[2] == 0) {
                continue;
            }

            $toAdd[] = [
                'website_id' => 0,
                'cust_group' => $tmp[0],
                'price_qty'  => $tmp[1],
                'price'      => $tmp[2],
                'delete'     => '',
            ];

            // drop any existing tier values by qty
            if (isset($lookup[(int) $tmp[1]])) {
                unset($existing[$lookup[(int) $tmp[1]]]);
            }
        }

        $product->setTierPrice(array_merge($existing, $toAdd));
    }

    /**
     * Edit group prices, "website_id=cust_group=price|..." or REMOVE
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $groupPricesField
     */
    protected function _editGroupPrices($product, $groupPricesField)
    {
        if (trim($groupPricesField) == 'REMOVE') {
            $product->setGroupPrice([]);
            return;
        }

        $toAdd = [];
        foreach (explode('|', $groupPricesField) as $groupStr) {
            if (empty($groupStr)) {
                continue;
            }

            $tmp = array_pad(explode('=', $groupStr), 3, 0);
            if ($tmp[1] == 0 && $tmp[2] == 0) {
                continue;
            }

            $toAdd[$tmp[1]] = [
                'website_id' => $tmp[0],
                'cust_group' => $tmp[1],
                'price'      => $tmp[2],
                'delete'     => '',
            ];
        }

        $product->setGroupPrice($toAdd);
    }

    /**
     * @param  string $file
     * @return bool
     */
    protected function _removeFile($file)
    {
        if (is_file($file)) {
            $ext = substr(strrchr($file, '.'), 1);
            // only image-like files with a 3-4 char extension, never directories
            if (strlen($ext) == 3 || strlen($ext) == 4) {
                return unlink($file);
            }
        }

        return false;
    }

    /**
     * Find products whose "parent" attribute is the given SKU
     *
     * @param  string $parentSku
     * @return array  product ids
     */
    public function _findChildren($parentSku)
    {
        if (!$this->getAttribute('parent')) {
            return [];
        }

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('parent')
            ->addAttributeToFilter('parent', ['eq' => $parentSku]);

        return $collection->getAllIds();
    }

    /**
     * Add a product to a configurable or grouped parent
     *
     * @param  int    $childProductId
     * @param  string $parentSku
     * @return string "done" or the error message
     */
    public function addToParent($childProductId, $parentSku)
    {
        $parentProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $parentSku);
        if ($parentProduct && $parentProduct->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $children = $parentProduct->getTypeInstance()->getUsedProductIds();
            $children[] = $childProductId;
            try {
                Mage::getResourceSingleton('catalog/product_type_configurable')->saveProducts($parentProduct, array_unique($children));
            } catch (Exception $e) {
                return 'Cannot assign to parent ' . $parentSku . ', reason: ' . $e->getMessage();
            }

            return 'done';
        }

        if ($parentProduct && $parentProduct->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $done = Mage::getModel('catalog/product_link_api')->assign('grouped', $parentProduct->getId(), $childProductId);
            return $done ? 'done' : 'Parent product ' . $parentSku . ' not found';
        }

        return 'Parent product ' . $parentSku . ' not found';
    }

    /**
     * @param  int   $groupedProductId
     * @return array
     */
    public function getAssociatedProductIds($groupedProductId)
    {
        $coreResource = Mage::getSingleton('core/resource');
        $conn = $coreResource->getConnection('core_read');
        $select = $conn->select()
            ->from($coreResource->getTableName('catalog/product_relation'), ['child_id'])
            ->where('parent_id = ?', $groupedProductId);

        return $conn->fetchCol($select);
    }
}
