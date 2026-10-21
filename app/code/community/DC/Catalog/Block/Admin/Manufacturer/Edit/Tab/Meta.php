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
class DC_Catalog_Block_Admin_Manufacturer_Edit_Tab_Meta extends Mage_Adminhtml_Block_Widget_Form
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

        // Determine current store scope (same logic as Main tab).
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

        $fieldset = $form->addFieldset('design_fieldset', array(
            'legend' => Mage::helper('dc_catalog')->__('Meta Data'),
            'class'  => 'fieldset-wide',
        ));

        // --- Page Title ---
        $pageTitleConfig = array(
            'name'  => 'page_title',
            'label' => Mage::helper('adminhtml')->__('Page Title'),
            'title' => Mage::helper('adminhtml')->__('Page Title'),
        );
        if ($isStoreScope) {
            $pageTitleConfig['after_element_html'] = $this->_getScopeCheckboxHtml(
                'page_title',
                (bool)$model->getUseDefaultPageTitle(),
                $defaultModel ? $defaultModel->getPageTitle() : ''
            );
        }
        $fieldset->addField('page_title', 'text', $pageTitleConfig);

        // --- Meta Keywords ---
        $metaKeywordsConfig = array(
            'name'  => 'meta_keywords',
            'label' => Mage::helper('dc_catalog')->__('Meta Keywords'),
            'title' => Mage::helper('dc_catalog')->__('Meta Keywords'),
        );
        if ($isStoreScope) {
            $metaKeywordsConfig['after_element_html'] = $this->_getScopeCheckboxHtml(
                'meta_keywords',
                (bool)$model->getUseDefaultMetaKeywords(),
                $defaultModel ? $defaultModel->getMetaKeywords() : ''
            );
        }
        $fieldset->addField('meta_keywords', 'editor', $metaKeywordsConfig);

        // --- Meta Description ---
        $metaDescConfig = array(
            'name'  => 'meta_description',
            'label' => Mage::helper('dc_catalog')->__('Meta Description'),
            'title' => Mage::helper('dc_catalog')->__('Meta Description'),
        );
        if ($isStoreScope) {
            $metaDescConfig['after_element_html'] = $this->_getScopeCheckboxHtml(
                'meta_description',
                (bool)$model->getUseDefaultMetaDescription(),
                $defaultModel ? $defaultModel->getMetaDescription() : ''
            );
        }
        $fieldset->addField('meta_description', 'editor', $metaDescConfig);

        $form->setValues($model->getData());

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Build the "Use Default Value" checkbox HTML that appears below a store-scoped field.
     *
     * @param  string $fieldName    e.g. 'page_title'
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
