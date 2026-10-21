<?php
class VladimirPopov_WebForms_Block_Adminhtml_Webforms extends Mage_Adminhtml_Block_Widget_Grid_Container{
	public function __construct(){
		if(!Mage::app()->getLayout()->createBlock('webforms/webforms','check')->check()) return;
		$this->_controller = 'adminhtml_webforms';
		$this->_blockGroup = 'webforms';
		$this->_headerText = Mage::helper('webforms')->__('Manage Forms');
		$this->_addButtonLabel = Mage::helper('webforms')->__('Add New Form');
		parent::__construct();

		$forms = Mage::getModel('webforms/webforms')->getCollection()->count();
		
		/*chezky if($forms>=3){
			$this->_removeButton('add');
			$this->_addButton('add',array(
				'label' => Mage::helper('webforms')->__('Add New Form'),
				'onclick' => 'alert(\''.Mage::helper('webforms')->__('You have reached Community Edition limit!\nCommunity Edition allows you to manage only 3 web-forms.\nUpgrade to Professional Edition.').'\')',
			));
		}*/
	}
}  


?>