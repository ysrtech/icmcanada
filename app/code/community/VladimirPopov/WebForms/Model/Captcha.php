<?php
class VladimirPopov_WebForms_Model_Captcha extends Zend_Service_ReCaptcha{

	public function getHtml(){
		/*if(Mage::getStoreConfig('webforms/captcha/api') === 'ajax'){
			return parent::getHtml();
                }*/
		$return = "";
		
		$div_id = "webform_recaptcha";
		if(Mage::registry('webform')){
			$div_id = "webform_".Mage::registry('webform')->getId()."_recaptcha";
		}
		
		$return .= <<<HTML
		<div id="{$div_id}"></div>
HTML;

		$return .= <<<SCRIPT
<script type="text/javascript" src="https://www.google.com/recaptcha/api.js"></script>
SCRIPT;
		
		
		
		$return .= '<div class="g-recaptcha" data-sitekey="'.$this->_publicKey.'"></div>';
		
		return $return;
	}
}
?>
