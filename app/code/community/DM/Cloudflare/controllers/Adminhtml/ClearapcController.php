<?php

/*
 * The "clear" model.
 *
 * @author  Agli Panci <agli.panci@gmail.com>
 * @link    https://
 */

class DM_Cloudflare_Adminhtml_ClearapcController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {

            if(Mage::getModel('dm_cloudflare/observer')->purgeCache() == 'success')
            {
                  Mage::getSingleton('adminhtml/session')->addSuccess('CloudFlare cache purged successfully.');
            } else 
            {
                  Mage::getSingleton('adminhtml/session')->addError("There was an error purging CloudFlare cache");
                    
            }

            $this->_redirect('adminhtml/cache/index');
       
    }
}
