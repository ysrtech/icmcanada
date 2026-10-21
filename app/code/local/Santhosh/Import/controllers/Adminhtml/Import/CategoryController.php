<?php

class Santhosh_Import_Adminhtml_Import_CategoryController extends Mage_Adminhtml_Controller_Action
{

	public function categoriesAction() {
            
                $lang_code = 'fr';//chezky set store code
                $lang_label = 'French';//chezky
            
		if ($this->getRequest()->isPost()) {
			$count = $this->getRequest()->getPost('count',0);
			$result['count'] = $count;
			$error = '';
			try {
				$attributes = Mage::helper('santhosh_core/category')->getAttributes();
				$helper = Mage::helper('santhosh_import/category');
				$category = Mage::getModel('catalog/category');

				$categoryPath = $this->getRequest()->getPost('path');
				$categoryName = $this->getRequest()->getPost('name');
                                
                                //chezky
                                if($id = $this->getRequest()->getPost('id')){
                                    if($category->load($id)->getId()){
                                        $catid = $id;
                                        }else{
                                            $error = Mage::helper('santhosh_import')->__('ID "%s" does not exist.',$id);
                                            
                                        }
                                }else{
                                    $catid = $helper->getCategoryIdFromPath($categoryPath, $categoryName);
                                }
                                $transAttributes = array();
                                //
                                
				if (is_array($catid)) {
					$error = Mage::helper('santhosh_import')->__('Path provided is not a valid one.');
				} elseif(empty($error)) {
					if ($catid > 0) $category->load($catid);
					foreach($attributes as $attribute) {
                                                $fieldvalue = $this->getRequest()->getPost($attribute['field']);
                                                
                                                //chezky
                                                if (strpos($attribute['field'],'_'.$lang_code) !== false && $fieldvalue) {
                                                    $transAttributes[$attribute['field']] = $fieldvalue;
                                                    continue;
                                                }
                                                //
						
						if (isset($attribute['function'])) {
                                                        $fieldvalue = $helper->{$attribute['function']}($fieldvalue);
						}
						if (isset($attribute['required'])) {
							if (strtolower($attribute['required']) == 'yes') {
								if (!$fieldvalue) {
									$error = Mage::helper('santhosh_import')->__('Please provide the value for "%s"', $attribute['field']);
									break;
								}
							}
						}
						if (isset($attribute['importfn'])) {
							$category->$attribute['importfn']($attribute['field'], $fieldvalue);
						} else {
							$category->setData($attribute['field'], $fieldvalue);
						}
                                                
                                                
					}
				}

				if ($error) {
					$result['error'] = $error;
				} else {
                                    
					$category->save();
                                        $name = '"'.$category->getName().'"';
                                    ///chezky
                                    if(count($transAttributes) > 0){
                                        $category->setStoreId($this->_getStore($lang_code)->getId());
                                        foreach($transAttributes as $tr_k => $tr_v){
                                                $n_k = str_replace('_'.$lang_code,'',$tr_k);
                                                $category->setData($n_k, $tr_v);
                                        }
                                        if(empty($transAttributes['url_key_'.$lang_code]) && !empty($transAttributes['name_'.$lang_code])){
                                            $category->setData('url_key', Mage::getModel('catalog/product_url')->formatUrlKey($transAttributes['name_'.$lang_code]));
                                        }
                                        $category->save();
                                        $name .= ' w/ '.$lang_label.' translation "'.$category->getName().'"';
                                    }
                                    ///
                                    $result['message'] = Mage::helper('santhosh_import')->__('Imported the category %s successfully.', $name);
				}
			} catch (Exception $e) {
				$result['error'] = $e->getMessage();
			}
		} else {
			$result['error'] = Mage::helper('santhosh_import')->__('Invalid request');
		}
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}
        ///chezky
        
        protected function _getStore($storeCode)
    {
        try {
            return Mage::app()->getStore($storeCode);
        } catch (Mage_Core_Model_Store_Exception $e) {
            Mage::throwException(sprintf("Could not find store with code '%s'", $storeCode));
        }
    }
    /////

}