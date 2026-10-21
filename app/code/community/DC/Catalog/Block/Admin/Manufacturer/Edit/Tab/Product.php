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
class DC_Catalog_Block_Admin_Manufacturer_Edit_Tab_Product extends Mage_Adminhtml_Block_Widget_Form
{
    public function __construct()
    {
        parent::__construct();
        $this->setShowGlobalIcon(true);
    }

	public function _prepareForm()
    {
        $form = new Varien_Data_Form();

        $form->setHtmlIdPrefix('page_');

        $model = Mage::registry('dc_catalog_manufacturer');

        // Determine current store scope.
        $currentStoreId = $model->getAttributeValueStoreId();
        if (!$currentStoreId && $currentStoreId !== 0) {
            $currentStoreId = (int)$model->getData('store_id');
        }
        $isStoreScope = ($currentStoreId != 0);

        // Load default-store page to show hint values.
        $defaultModel = null;
        if ($isStoreScope) {
            $attrCode = $model->getAttributeCode();
            $optionId = $model->getAttributeOptionId() ?: $model->getOptionId();
            if ($attrCode && $optionId) {
                $defaultModel = Mage::getModel('dc_catalog/manufacturer');
                $defaultModel->loadFromAttribute($attrCode, $optionId, 0);
            }
        }

        $fieldset = $form->addFieldset('product_fieldset', array(
        	'legend'=>Mage::helper('adminhtml')->__('Information to be displayed in the product pages'),
        	'class'=>'fieldset-wide'
        ));

        $fieldset->addField('banner', 'image', array(
            'name'      => 'banner',
            'label'     => Mage::helper('adminhtml')->__('Banner'),
            'title'     => Mage::helper('adminhtml')->__('Banner'),
            'required'  => false,
            'after_element_html' => '<p class="nm"><small>' . Mage::helper('adminhtml')->__('Banner to be displayed in the product page') . '</small></p>',
        ));

        $descInPageConfig = array(
            'name'      => 'description_in_page',
            'label'     => Mage::helper('adminhtml')->__('Description in product page'),
            'title'     => Mage::helper('adminhtml')->__('Description in product page'),
            'rows'      => 3,
            'cols'      => 30,
            'style'     => 'height:5em;',
            'required'  => false,
            'after_element_html' => '<p class="nm"><small>' . Mage::helper('adminhtml')->__('Add this text next to the value in the product page') . '</small></p>',
        );
        if ($isStoreScope) {
            $descInPageConfig['after_element_html'] .= $this->_getScopeCheckboxHtml(
                'description_in_page',
                (bool)$model->getUseDefaultDescriptionInPage(),
                $defaultModel ? $defaultModel->getDescriptionInPage() : ''
            );
        }
    	$fieldset->addField('description_in_page', 'textarea', $descInPageConfig);


        //fix the image upload nag
        $values = $model->getData();
        if (is_array($values['banner']) && isset($values['banner']['value'])) {
			$values['banner'] = 'catalog/attribute/'.$values['banner']['value'];
		} elseif (is_string($values['banner']) && ($values['banner'] > '')) {
			$values['banner'] = 'catalog/attribute/'.$values['banner'];
        }
        $form->setValues($values);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Build the "Use Default Value" checkbox HTML that appears below a store-scoped field.
     *
     * @param  string $fieldName    e.g. 'description_in_page'
     * @param  bool   $isChecked
     * @param  string $defaultValue Raw default-store value (shown as a hint)
     * @return string
     */
    protected function _getScopeCheckboxHtml($fieldName, $isChecked, $defaultValue = '')
    {
        $checkboxId = 'use_default_' . $fieldName;
        $textareaId = 'page_' . $fieldName;   // Varien form uses the html_id_prefix "page_"
        $checked    = $isChecked ? ' checked="checked"' : '';
        $label      = Mage::helper('adminhtml')->__('Use Default Value');

        $hint = '';
        if ($defaultValue !== '') {
            $plainDefault = strip_tags($defaultValue);
            $hint = Mage::helper('core')->escapeHtml(
                mb_strlen($plainDefault) > 120
                    ? mb_substr($plainDefault, 0, 120) . '…'
                    : $plainDefault
            );
        }
        $hintHtml = $hint !== ''
            ? '<span class="dc-scope-hint"> &mdash; ' . $hint . '</span>'
            : '';

        return <<<HTML
<div class="dc-use-default-row" id="dc_use_default_row_{$fieldName}">
    <input type="checkbox"
           id="{$checkboxId}"
           name="{$checkboxId}"
           value="1"{$checked}
           onclick="dcToggleUseDefault('{$textareaId}', this)" />
    <label for="{$checkboxId}"><small>{$label}</small></label>{$hintHtml}
</div>
HTML;
    }
}
