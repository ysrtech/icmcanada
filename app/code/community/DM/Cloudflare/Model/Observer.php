<?php

/*
 * The "observer" model.
 *
 * @author  Agli Panci <agli.panci@gmail.com>
 * @link    https://
 */

include 'cloudflare.php';

class DM_Cloudflare_Model_Observer {

        private $cf_api_token;
        private $cf_zone_id;

        public $cf;

     public function __construct()
      {
        $this->cf_api_token = trim(Mage::getStoreConfig('general/cf_group/cf_api_token'));
        $this->cf_zone_id   = trim(Mage::getStoreConfig('general/cf_group/cf_zone_id'));
        $this->cf           = new cloudflare_api($this->cf_api_token);
      }

    public function purgeCache(){
        if (empty($this->cf_api_token) || empty($this->cf_zone_id)) {
            Mage::getSingleton('adminhtml/session')->addError(
                'Cloudflare: API Token or Zone ID is not configured. Check System → Configuration → General → CloudFlare.'
            );
            return 'error';
        }

        $obj = $this->cf->purge_everything($this->cf_zone_id);

        if (isset($obj->success) && $obj->success) {
            return 'success';
        }

        // Surface the actual error from Cloudflare
        $errorMsg = 'Cloudflare API error.';
        if (!empty($obj->errors) && is_array($obj->errors)) {
            $parts = array();
            foreach ($obj->errors as $e) {
                $parts[] = isset($e->message) ? '[' . $e->code . '] ' . $e->message : (string)$e;
            }
            $errorMsg = implode(' | ', $parts);
        }
        $tokenHint = strlen($this->cf_api_token) > 8
            ? substr($this->cf_api_token, 0, 4) . '...' . substr($this->cf_api_token, -4)
            : '(empty or too short)';
        Mage::getSingleton('adminhtml/session')->addError(
            'Cloudflare: ' . $errorMsg . ' (token: ' . $tokenHint . ', zone: ' . $this->cf_zone_id . ')'
        );
        return 'error';
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
