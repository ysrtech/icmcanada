<?php

/**
 * ICM customizations
 *
 * Show checkout messages (e.g. "added to cart" from the AJAX cart) on the advanced search page.
 * Replaces the old edit to app/code/core/Mage/CatalogSearch/controllers/AdvancedController.php.
 */
require_once Mage::getModuleDir('controllers', 'Mage_CatalogSearch') . DS . 'AdvancedController.php';

class ICM_Customizations_CatalogSearch_AdvancedController extends Mage_CatalogSearch_AdvancedController
{
    public function resultAction()
    {
        $this->loadLayout();
        try {
            Mage::getSingleton('catalogsearch/advanced')->addFilters($this->getRequest()->getQuery());
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('catalogsearch/session')->addError($e->getMessage());
            $this->_redirectError(
                Mage::getModel('core/url')
                    ->setQueryParams($this->getRequest()->getQuery())
                    ->getUrl('*/*/'),
            );
            return;
        }

        $this->_initLayoutMessages('catalog/session');
        $this->_initLayoutMessages('checkout/session');
        $this->renderLayout();
    }
}
