<?PHP 

class ICM_Adminhtml_Block_Head extends Mage_Page_Block_Html_Head
{

        /**
     * Set title element text
     *
     * @param string $title
     * @return Mage_Page_Block_Html_Head
     */
    public function setTitle($title)
    {
        $this->_data['title'] =  $title;
           // . ' ' . Mage::getStoreConfig('design/head/title_suffix');
        return $this;
    }
    
        protected function _getUrlModelClass()
    {
        return 'adminhtml/url';
    }

    /**
     * Retrieve Session Form Key
     *
     * @return string
     */
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }
}