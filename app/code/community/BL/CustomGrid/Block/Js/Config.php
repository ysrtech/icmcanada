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

class BL_CustomGrid_Block_Js_Config extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bl/customgrid/js/config.phtml');
    }
    
    /**
     * Return the config helper
     * 
     * @return BL_CustomGrid_Helper_Config
     */
    protected function _getConfigHelper()
    {
        return $this->helper('customgrid/config');
    }
    
    /**
     * Return the JSON-encoded config values used by the searchable dropdowns
     * 
     * @return string
     */
    public function getSearchableSelectJsonConfig()
    {
        /** @var $coreHelper Mage_Core_Helper_Data */
        $coreHelper = $this->helper('core');
        $configHelper = $this->_getConfigHelper();
        
        $config = array(
            'enabled'    => $configHelper->getSearchableDropdowns(),
            'minOptions' => $configHelper->getSearchableDropdownsThreshold(),
            'style'      => $configHelper->getSearchableDropdownsStyle(),
            'formSelectors' => $configHelper->getSearchableDropdownsFormSelectors(),
        );
        
        if ($this->_canAddAttributeOptions()) {
            $config['formKey'] = Mage::getSingleton('core/session')->getFormKey();
            $config['addOptionUrl'] = $this->getUrl('adminhtml/blcg_attribute_option/add');
            $config['addOptionAttributes'] = $configHelper->getProductAttributesAcceptingNewOptions();
        }
        
        return $coreHelper->jsonEncode($config);
    }
    
    /**
     * Return whether the searchable dropdowns may offer to create a missing
     * option. Sent to the browser only to decide whether to draw the link: the
     * controller checks the same things again before it writes anything.
     * 
     * @return bool
     */
    protected function _canAddAttributeOptions()
    {
        $configHelper = $this->_getConfigHelper();
        
        return $configHelper->getSearchableDropdowns()
            && $configHelper->getSearchableDropdownsAddOptions()
            && count($configHelper->getSearchableDropdownsFormSelectors())
            && Mage::getSingleton('admin/session')->isAllowed('catalog/products');
    }
}
