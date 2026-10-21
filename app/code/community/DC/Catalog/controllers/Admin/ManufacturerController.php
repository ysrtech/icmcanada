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
class DC_Catalog_Admin_ManufacturerController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Init actions
     *
     * @return DC_Catalog_Admin_ManufacturerController
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        $this->loadLayout()
            ->_setActiveMenu('cms/dc_catalog')
            ->_addBreadcrumb(Mage::helper('dc_catalog')->__('Catalog'), Mage::helper('dc_catalog')->__('Catalog'))
            ->_addBreadcrumb(Mage::helper('dc_catalog')->__('Manage Attribute Pages'), Mage::helper('dc_catalog')->__('Manage Attribute Pages'))
        ;
        return $this;
    }

    /**
     * Index action
     */
    public function indexAction()
    {
        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('dc_catalog/admin_manufacturer'))
            ->renderLayout();
    }

    /**
     * Create new page
     */
    public function newAction()
    {
        // cannot add directly, only from the grid
        $this->_redirect('*/*/');
    }

    /**
     * Edit attribute info page
     */
    public function editAction()
    {
        // 1. Get ID and create model
        $id = $this->getRequest()->getParam('attribute_page_id');
        $model = Mage::getModel('dc_catalog/manufacturer');
        /* @var $model DC_Catalog_Model_Manufacturer */

        // 2. Initial checking
        if ($id) {
            $model->load($id);/*die('<br>#stop');*/
            if (! $model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('This attribute info no longer exists'));
                $this->_redirect('*/*/');
                return;
            }
        } else {
            $attribute_code = $this->getRequest()->getParam('attribute_code');
            $option_id      = $this->getRequest()->getParam('option_id');
            $store_id       = $this->getRequest()->getParam('store_id');

        	$model->loadFromAttribute($attribute_code, $option_id, $store_id);/*die('<br>#stop');*/
        }


        // 3. Set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (! empty($data)) {
            $model->setData($data);
        }

        // 4. Register model to use later in blocks
        Mage::register('dc_catalog_manufacturer', $model);

        // 5. Build edit form
        $this->_initAction()
            ->_addBreadcrumb($id ? Mage::helper('adminhtml')->__('Edit Page') : Mage::helper('adminhtml')->__('New Page'), $id ? Mage::helper('adminhtml')->__('Edit Page') : Mage::helper('adminhtml')->__('New Page'))
            ->_addContent($this->getLayout()->createBlock('dc_catalog/admin_manufacturer_edit')->setData('action', $this->getUrl('*/manufacturer/save')))
            ->_addLeft($this->getLayout()->createBlock('dc_catalog/admin_manufacturer_edit_tabs'));

        $this->renderLayout();

    }

    /**
     * Save action
     */
    public function saveAction()
    {
        // check if data sent
        if ($data = $this->getRequest()->getPost()) {
            // init model and set data
            $model = Mage::getModel('dc_catalog/manufacturer');

            // Process use_default_* flags for translatable fields.
            // When a field is set to "use default", clear its value so the resource
            // model will fall back to the default-store page at load time.
            $translatableFields = array('description', 'page_title', 'meta_keywords', 'meta_description', 'description_in_page');
            foreach ($translatableFields as $field) {
                $flagKey = 'use_default_' . $field;
                if (!empty($data[$flagKey])) {
                    $data[$flagKey] = 1;
                    $data[$field]   = '';
                } else {
                    $data[$flagKey] = 0;
                }
            }

            $model->setData($data);
	        if (!$this->getRequest()->getParam('attribute_page_id')) {
	            $model->setData('attribute_code', $this->getRequest()->getParam('attribute_code'));
	            $model->setData('attribute_option_id', $this->getRequest()->getParam('option_id'));
	            $model->setData('attribute_value_store_id', $this->getRequest()->getParam('store_id'));
	        }

            Mage::dispatchEvent('dc_catalog_admin_manufacturer_prepare_save', array('info' => $model, 'request' => $this->getRequest()));


            // try to save it
            try {
                // save the data
                $model->save();

                // Save per-store translations submitted from the Translations tab.
                // Each entry in translations[store_id][field] is saved as a separate
                // row in catalog_attribute_page with attribute_value_store_id = store_id.
                if (!empty($data['translations']) && is_array($data['translations'])) {
                    $this->_saveTranslations($model, $data['translations']);
                }

                // display success message
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('The attribute info was successfully saved'));
                // clear previously saved data from session
                Mage::getSingleton('adminhtml/session')->setFormData(false);
                // check if 'Save and Continue'
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', array('attribute_page_id' => $model->getId()));
                    return;
                }
                // go to grid
                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                // display error message
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                // save data in session
                Mage::getSingleton('adminhtml/session')->setFormData($data);

                // redirect back to edit form
		        if ($id = $this->getRequest()->getParam('attribute_page_id')) {
	                $this->_redirect('*/*/edit', array('attribute_page_id' => $id));
		        } else {
		            $attribute_code = $this->getRequest()->getParam('attribute_code');
		            $option_id      = $this->getRequest()->getParam('option_id');
		            $store_id       = $this->getRequest()->getParam('store_id');
	                $this->_redirect('*/*/edit', array(
	                	'attribute_code' => $attribute_code,
	                	'option_id' => $option_id,
	                	'store_id' => $store_id
	                ));
		        }

                return;
            }
        }
        //$this->_redirect('*/*/');
    }

    /**
     * Delete action
     */
    public function deleteAction()
    {
        // check if we know what should be deleted
        if ($id = $this->getRequest()->getParam('attribute_page_id')) {
            $title = "";
            try {
                // init model and delete
                $model = Mage::getModel('dc_catalog/manufacturer');
                $model->load($id);
                $title = $model->getName();
                $model->delete();
                // display success message
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Page was successfully deleted'));
                // go to grid
                //Mage::dispatchEvent('adminhtml_cmspage_on_delete', array('title' => $title, 'status' => 'success'));
                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                //Mage::dispatchEvent('adminhtml_cmspage_on_delete', array('title' => $title, 'status' => 'fail'));
                // display error message
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                // go back to edit form
                $this->_redirect('*/*/edit', array('attribute_page_id' => $id));
                return;
            }
        }
        // display error message
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Unable to find a page to delete'));
        // go to grid
        $this->_redirect('*/*/');
    }

    /**
     * Save per-store translations that were submitted via the Translations tab.
     *
     * Writes directly to the DB (INSERT or UPDATE) to bypass _beforeSave validation
     * (identifier uniqueness, image upload processing) which is only relevant for the
     * main admin form, not for programmatic translation saves.
     *
     * @param DC_Catalog_Model_Manufacturer $defaultModel  The already-saved default (store=0) model
     * @param array                         $translations  ['store_id' => ['field' => 'value', ...], ...]
     */
    protected function _saveTranslations($defaultModel, array $translations)
    {
        $translatableFields = array(
            'description', 'page_title', 'meta_keywords', 'meta_description', 'description_in_page'
        );

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $read     = $resource->getConnection('core_read');
        $write    = $resource->getConnection('core_write');
        $table    = $resource->getTableName('dc_catalog/manufacturer');

        // When editing an existing page, attribute_code and attribute_option_id may not
        // be in POST (only attribute_page_id is). Resolve them from the DB.
        $attrCode = $defaultModel->getAttributeCode();
        $optionId = (int)$defaultModel->getAttributeOptionId();

        if ((!$attrCode || !$optionId) && $defaultModel->getId()) {
            $fallback = $read->fetchRow(
                $read->select()
                    ->from($table, array('attribute_code', 'attribute_option_id'))
                    ->where('attribute_page_id = ?', (int)$defaultModel->getId())
                    ->limit(1)
            );
            if ($fallback) {
                if (!$attrCode) { $attrCode = $fallback['attribute_code']; }
                if (!$optionId) { $optionId = (int)$fallback['attribute_option_id']; }
            }
        }

        if (!$attrCode || !$optionId) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('dc_catalog')->__('Could not resolve attribute code / option ID — translations not saved.')
            );
            return;
        }

        // Load the default row to copy structural fields for new store rows
        $defaultRow = $read->fetchRow(
            $read->select()
                ->from($table)
                ->where('attribute_code = ?',           $attrCode)
                ->where('attribute_option_id = ?',      $optionId)
                ->where('attribute_value_store_id = ?', 0)
                ->limit(1)
        );

        $now = Mage::getSingleton('core/date')->gmtDate();

        foreach ($translations as $storeId => $storeData) {
            $storeId = (int)$storeId;
            if ($storeId <= 0) {
                continue;
            }

            // Determine if there is any actual translated content submitted
            $hasContent = false;
            foreach ($translatableFields as $field) {
                if (empty($storeData['use_default_' . $field]) && trim((string)$storeData[$field]) !== '') {
                    $hasContent = true;
                    break;
                }
            }

            // Check if a store-specific row already exists
            $existingRow = $read->fetchRow(
                $read->select()
                    ->from($table)
                    ->where('attribute_code = ?',           $attrCode)
                    ->where('attribute_option_id = ?',      $optionId)
                    ->where('attribute_value_store_id = ?', $storeId)
                    ->limit(1)
            );

            // Don't create a new row if there is nothing to translate
            if (!$existingRow && !$hasContent) {
                continue;
            }

            // Build the translatable columns to write
            $translationData = array('update_time' => $now);
            foreach ($translatableFields as $field) {
                $flagKey = 'use_default_' . $field;
                if (!empty($storeData[$flagKey])) {
                    $translationData[$flagKey] = 1;
                    $translationData[$field]   = '';
                } else {
                    $translationData[$flagKey] = 0;
                    $translationData[$field]   = isset($storeData[$field]) ? (string)$storeData[$field] : '';
                }
            }

            try {
                if ($existingRow) {
                    // UPDATE existing store row — only touch translatable columns
                    $write->update(
                        $table,
                        $translationData,
                        array('attribute_page_id = ?' => (int)$existingRow['attribute_page_id'])
                    );
                } else {
                    // INSERT new store row — copy structural fields from default row
                    $insertData = array_merge(
                        array(
                            'attribute_code'           => $attrCode,
                            'attribute_option_id'      => $optionId,
                            'attribute_value_store_id' => $storeId,
                            'name'                     => $defaultRow ? $defaultRow['name']               : '',
                            'identifier'               => $defaultRow ? $defaultRow['identifier']         : '',
                            'is_active'                => $defaultRow ? $defaultRow['is_active']          : 1,
                            'favorite'                 => $defaultRow ? $defaultRow['favorite']           : null,
                            'image'                    => $defaultRow ? $defaultRow['image']              : '',
                            'banner'                   => $defaultRow ? $defaultRow['banner']             : '',
                            'external_url'             => $defaultRow ? $defaultRow['external_url']       : '',
                            'external_url_label'       => $defaultRow ? $defaultRow['external_url_label'] : '',
                            'root_template'            => $defaultRow ? $defaultRow['root_template']      : '',
                            'creation_time'            => $now,
                        ),
                        $translationData
                    );
                    $write->insert($table, $insertData);
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('dc_catalog')->__('Error saving translation for store ID %s: %s', $storeId, $e->getMessage())
                );
            }
        }
    }

    /**
     * Check the permission to run it
     */
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('dc_catalog/manufacturer');
    }
}