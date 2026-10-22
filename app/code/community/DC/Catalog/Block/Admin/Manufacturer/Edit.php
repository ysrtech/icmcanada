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
class DC_Catalog_Block_Admin_Manufacturer_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{

    protected $attributeCode = 'manufacturer';

	public function __construct()
    {
        $this->_objectId = 'attribute_page_id';
        $this->_controller = 'admin_manufacturer';
        $this->_blockGroup = 'dc_catalog';

        parent::__construct();

    	$this->setData('form_action_url', $this->getUrl('*/manufacturer/save'));
        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Page'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Page'));

        $this->_addButton('saveandcontinue', array(
            'label'     => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ), -100);

        $this->_formScripts[] = "
            function toggleEditor() {
                if (tinyMCE.getInstanceById('page_content') == null) {
                    tinyMCE.execCommand('mceAddControl', false, 'page_content');
                } else {
                    tinyMCE.execCommand('mceRemoveControl', false, 'page_content');
                }
            }

            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }

            /**
             * Toggle the \"Use Default Value\" state for a store-scoped field.
             *
             * @param {string} fieldId   - the Varien form element ID (e.g. 'page_description')
             * @param {Element} checkbox - the checkbox element that was clicked
             */
            function dcToggleUseDefault(fieldId, checkbox) {
                var useDefault = checkbox.checked;

                // 1. Handle plain <input> / <textarea> (WYSIWYG off)
                var el = \$(fieldId);
                if (el) {
                    el.disabled = useDefault;
                    if (useDefault) {
                        el.addClassName('dc-field-disabled');
                    } else {
                        el.removeClassName('dc-field-disabled');
                    }
                }

                // 2. Handle TinyMCE 3.x (Magento 1 default):
                //    The editor wrapper table has id fieldId + '_tbl'
                var mceTbl = \$(fieldId + '_tbl');
                if (mceTbl) {
                    mceTbl.style.opacity       = useDefault ? '0.4' : '';
                    mceTbl.style.pointerEvents = useDefault ? 'none' : '';
                }

                // Also try the TinyMCE 4 / newer container suffix
                var mceWrap = \$(fieldId + '-tbl') || \$(fieldId + '_ifr');
                if (mceWrap) {
                    var wrap = mceWrap.up('.mce-tinymce') || mceWrap.up('.mceEditor');
                    if (wrap) {
                        wrap.style.opacity       = useDefault ? '0.4' : '';
                        wrap.style.pointerEvents = useDefault ? 'none' : '';
                    }
                }
            }

            // On DOM ready, apply the initial disabled state for any pre-checked boxes.
            document.observe('dom:loaded', function() {
                \$\$('.dc-use-default-row input[type=checkbox]').each(function(cb) {
                    if (cb.checked) {
                        var fieldId = cb.getAttribute('onclick').replace(/.*'([^']+)'.*/, '\$1');
                        dcToggleUseDefault(fieldId, cb);
                    }
                });
            });
        ";
    }

    /**
     * Append a small CSS block for the "Use Default Value" scope UI.
     */
    protected function _toHtml()
    {
        $css = '<style type="text/css">
            .dc-use-default-row { margin-top: 4px; }
            .dc-use-default-row label { cursor: pointer; color: #444; }
            .dc-scope-hint { color: #888; font-style: italic; font-size: 11px; }
            .dc-field-disabled { background: #f5f5f5 !important; color: #aaa !important; }
        </style>';
        return $css . parent::_toHtml();
    }

    public function getHeaderText()
    {
        if (Mage::registry('dc_catalog_manufacturer')->getAttributePageId()) {
			$stores = Mage::app()->getStores(true, false);
        	$model = Mage::registry('dc_catalog_manufacturer');
        	/* @var $model DC_Catalog_Model_Manufacturer */
        	$storeName = 'Default';
        	if(isset($stores[$model->getAttributeValueStoreId()])) {
        		$storeName = $stores[$model->getAttributeValueStoreId()]->getName();
        	}

        	$text = Mage::helper('adminhtml')->__('Edit %s Info Page for %s (in %s store)', $model->getFrontendLabel(), $model->getValue(), $storeName);
            return $text;
        } else {
			$stores = Mage::app()->getStores(true, false);
        	$model = Mage::registry('dc_catalog_manufacturer');
        	/* @var $model DC_Catalog_Model_Manufacturer */
        	$storeName = 'Default';
        	if($stores[$model->getStoreId()]) {
        		$storeName = $stores[$model->getStoreId()]->getName();
        	}

        	$text = Mage::helper('adminhtml')->__('New %s Info Page for %s (in %s store)', $model->getFrontendLabel(), $model->getValue(), $storeName);
            return $text;
        }
    }

}