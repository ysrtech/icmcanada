<?php
class VladimirPopov_WebForms_Model_Observer{
	
	public function addAssets($observer){
		$layout = $observer->getLayout();
		$update = $observer->getLayout()->getUpdate();
			
		if(in_array('cms_page', $update->getHandles())){
			
			$pageId = Mage::app()->getRequest()
				->getParam('page_id', Mage::app()->getRequest()->getParam('id', false));
			
			$page = Mage::getModel('cms/page')->load($pageId);
			
			if(stristr($page->getContent(),'webforms/form')){
				Mage::helper('webforms')->addAssets($layout);
			}
		}
		
	}
	
	public function extendAdminMenu($observer){
		$block = $observer->getBlock();
		
		if(get_class($block) == 'Mage_Adminhtml_Block_Page_Menu') {
			
			$parent = Mage::getSingleton('admin/config')->getAdminhtmlConfig()->getNode('menu');
			
			$webformsNode = $parent->children()->descend('webforms');
			
			$collection = Mage::getModel('webforms/webforms')
				->setStoreId(Mage::app()->getStore()->getId())
				->getCollection()
				->addFilter('menu','1');
			$collection->getSelect()->order('name asc');
						
			$i=1;
			
			foreach($collection as $webform){				
				$menuitem = new SimpleXMLElement('
					<webform_'.$webform->getId().' module="webforms">
						<title>'.$webform->getName().'</title>
						<sort_order>'.($i++ * 10).'</sort_order>
						<action>webforms/adminhtml_results/index/webform_id/'.$webform->getId().'/</action>
					</webform_'.$webform->getId().'>
				');
				$webformsNode->descend('children')->appendChild($menuitem);
			}
						
			$settings = new SimpleXMLElement('
					<settings module="webforms">
						<title>Settings</title>
						<sort_order>'.($i++ * 10).'</sort_order>
						<action>adminhtml/system_config/edit/section/webforms</action>
					</settings>
			');
			$webformsNode->descend('children')->appendChild($settings);
			
		}
	}
}
?>
