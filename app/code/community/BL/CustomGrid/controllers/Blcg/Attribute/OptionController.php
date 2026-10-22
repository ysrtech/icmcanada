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
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Creates the options that the searchable dropdowns offer to add when a search
 * matches nothing - see js/bl/customgrid/select-widget.js.
 *
 * The option has to exist in the database before it is any use: the product
 * form posts option IDs, so an option invented in the browser would be
 * submitted as a label where an ID is expected, and dropped on save.
 */
class BL_CustomGrid_Blcg_Attribute_OptionController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Longest label the eav_attribute_option_value.value column holds
     */
    const MAX_LABEL_LENGTH = 255;
    
    /**
     * Adding an option is an edit to the attribute, but it is offered to the
     * people editing products, from the product form, and is a much smaller
     * thing than what the attribute screens allow - it adds a value, it cannot
     * rename, reorder or remove one. Gate it on the product permission that got
     * them to the form rather than on the attribute one, which few of them hold.
     * 
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/products');
    }
    
    /**
     * @param string $message
     * @param array $values
     */
    protected function _respond($message, array $values = array())
    {
        /** @var $coreHelper Mage_Core_Helper_Data */
        $coreHelper = Mage::helper('core');
        
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=utf-8', true)
            ->setBody($coreHelper->jsonEncode($values + array('message' => $message)));
    }
    
    /**
     * Return the product attribute named by the request, if new options may be
     * added to it
     * 
     * @param string $code
     * @return Mage_Eav_Model_Entity_Attribute_Abstract|null
     */
    protected function _loadAttribute($code)
    {
        if ($code === '') {
            return null;
        }
        
        /** @var $helper BL_CustomGrid_Helper_Config */
        $helper = Mage::helper('customgrid/config');
        
        /*
         * The same test the form was drawn from, applied again here: the list
         * sent to the browser is a convenience, this is the permission check.
         */
        $addable = $helper->getProductAttributesAcceptingNewOptions();
        
        if (!isset($addable[$code])) {
            return null;
        }
        
        $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $code);
        
        return ($attribute && $attribute->getId()) ? $attribute : null;
    }
    
    /**
     * Return the ID of the option of the given attribute that already carries
     * the given label, if there is one
     * 
     * @param int $attributeId
     * @param string $label
     * @return int|null
     */
    protected function _findExistingOption($attributeId, $label)
    {
        /** @var $resource Mage_Core_Model_Resource */
        $resource = Mage::getSingleton('core/resource');
        $adapter  = $resource->getConnection('core_read');
        
        $select = $adapter->select()
            ->from(array('o' => $resource->getTableName('eav/attribute_option')), array('option_id'))
            ->join(
                array('v' => $resource->getTableName('eav/attribute_option_value')),
                'v.option_id = o.option_id',
                array('value')
            )
            ->where('o.attribute_id = ?', $attributeId)
            ->where('v.store_id = ?', 0);
        
        foreach ($adapter->fetchAll($select) as $row) {
            // Deliberately loose: "Feldheim" and "feldheim" are the same publisher
            if (strcasecmp(trim($row['value']), $label) === 0) {
                return (int) $row['option_id'];
            }
        }
        
        return null;
    }
    
    /**
     * Insert an option and its admin-scope label.
     *
     * Written straight to the two tables rather than through
     * $attribute->setData('option', ...)->save(), which re-saves the whole
     * attribute row and sets off everything watching a catalog attribute save,
     * to add one value. These are the same inserts that path would end up
     * making, in Mage_Eav_Model_Resource_Entity_Attribute::_processAttributeOptions().
     * 
     * @param int $attributeId
     * @param string $label
     * @return int The new option ID
     */
    protected function _createOption($attributeId, $label)
    {
        /** @var $resource Mage_Core_Model_Resource */
        $resource = Mage::getSingleton('core/resource');
        $adapter  = $resource->getConnection('core_write');
        
        $adapter->beginTransaction();
        
        try {
            $adapter->insert(
                $resource->getTableName('eav/attribute_option'),
                array('attribute_id' => $attributeId, 'sort_order' => 0)
            );
            
            $optionId = (int) $adapter->lastInsertId($resource->getTableName('eav/attribute_option'));
            
            $adapter->insert(
                $resource->getTableName('eav/attribute_option_value'),
                array('option_id' => $optionId, 'store_id' => 0, 'value' => $label)
            );
            
            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            throw $e;
        }
        
        /*
         * Mage_Eav_Model_Entity_Attribute_Source_Table caches each attribute's
         * options under the "eav" tag, so without this the new option would not
         * show up on the next page load.
         */
        Mage::app()->cleanCache(array('eav'));
        
        return $optionId;
    }
    
    public function addAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_respond($this->__('This action expects a POST request'));
            return;
        }
        
        if (!$this->_validateFormKey()) {
            $this->_respond($this->__('Your session has expired, please reload the page'));
            return;
        }
        
        $code  = trim((string) $this->getRequest()->getPost('attribute_code'));
        $label = trim((string) $this->getRequest()->getPost('label'));
        
        if ($label === '') {
            $this->_respond($this->__('Please type the value to add'));
            return;
        }
        
        if (Mage::helper('core/string')->strlen($label) > self::MAX_LABEL_LENGTH) {
            $this->_respond($this->__('This value is too long (%s characters at most)', self::MAX_LABEL_LENGTH));
            return;
        }
        
        if (!$attribute = $this->_loadAttribute($code)) {
            $this->_respond($this->__('New values cannot be added to this field'));
            return;
        }
        
        try {
            // Two people adding the same publisher should end up on one option
            if (!$optionId = $this->_findExistingOption($attribute->getId(), $label)) {
                $optionId = $this->_createOption($attribute->getId(), $label);
            }
            
            $this->_respond('', array('option_id' => $optionId, 'label' => $label));
            
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_respond($this->__('The value could not be added'));
        }
    }
}
