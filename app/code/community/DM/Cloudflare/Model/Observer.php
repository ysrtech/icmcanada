<?php

/*
 * The "observer" model.
 *
 * @author  Agli Panci <agli.panci@gmail.com>
 * @link    https://
 */

include 'cloudflare.php';

class DM_Cloudflare_Model_Observer {

        private $cf_apikey;
        private $cf_email;
        private $cf_domain;


        public $cf;

     public function __construct()
      {

        $this->cf_apikey = Mage::getStoreConfig('general/cf_group/cf_api');
        $this->cf_email = Mage::getStoreConfig('general/cf_group/cf_email');
        $this->cf_domain = $this->clearUrl();
        $this->cf= new cloudflare_api($this->cf_email, $this->cf_apikey);
      }

    public function purgeCache(){
        $obj = $this->cf->fpurge_ts($this->cf_domain);
        return $obj->result;
    }

    public function injectHtml(Varien_Event_Observer $observer) {
        $block  = $observer->getBlock();

        if($block instanceof Mage_Adminhtml_Block_Cache_Additional) {
            $transport = $observer->getTransport();

            $insert =
                '<tr>
                    <td class="scope-label">
                        <button onclick="setLocation(\'' . Mage::helper('adminhtml')->getUrl('adminhtml/clearapc/index') . '\')" type="button" class="scalable">
                            <span>' . Mage::helper('adminhtml')->__('Purge CloudFlare Cache') . '</span>
                        </button>
                    </td>
                    <td class="scope-label">' . Mage::helper('adminhtml')->__('Immediately purge cached resources for your website.') . '</td>
                </tr>';

            $dom = new DOMDocument();

            $dom->loadHTML($transport->getHtml());

            $td = $dom->createDocumentFragment();
            $td->appendXML($insert);

            $dom->getElementsByTagName('table')->item(1)->insertBefore($td, $dom->getElementsByTagName('table')->item(1)->firstChild);

            $transport->setHtml($dom->saveHTML());
        }
    }

    public function clearUrl(){
        
        $input = trim(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/');

        if (!preg_match('#^http(s)?://#', $input)) {
            $input = 'http://' . $input;
        }

        $urlParts = parse_url($input);

        $domain = preg_replace('/^www\./', '', $urlParts['host']);

        return $domain;
    }
}
