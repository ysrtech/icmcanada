<?php

/**
 * ICM customizations
 *
 * Header "Checkout" link goes to the cart first, and to checkout only when already on the cart page.
 * Replaces the old full copy of app/code/local/Mage/Checkout/Block/Links.php.
 */
class ICM_Customizations_Block_Checkout_Links extends Mage_Checkout_Block_Links
{
    /**
     * @return $this
     */
    public function addCheckoutLink()
    {
        /** @var Mage_Checkout_Helper_Data $helper */
        $helper = $this->helper('checkout');
        if (!$helper->canOnepageCheckout()) {
            return $this;
        }

        $parentBlock = $this->getParentBlock();
        if ($parentBlock && $this->isModuleOutputEnabled('Mage_Checkout')) {
            $text = $this->__('Checkout');
            $isCartPage = $this->helper('core/url')->getCurrentUrl() == Mage::getUrl('checkout/cart');
            $parentBlock->addLink(
                $text,
                $isCartPage ? 'checkout' : 'checkout/cart',
                $text,
                true,
                $isCartPage ? ['_secure' => true] : [],
                60,
                null,
                'class="top-link-checkout"',
            );
        }

        return $this;
    }
}
