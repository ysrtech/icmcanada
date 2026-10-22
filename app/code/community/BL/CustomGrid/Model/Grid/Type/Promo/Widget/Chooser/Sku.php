<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   BL
 * @package    BL_CustomGrid
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * BL_CustomGrid grid type for the promo / cart-rule SKU chooser grid.
 *
 * Extends BL_CustomGrid_Model_Grid_Type_Product so that all product attribute
 * columns (canHaveAttributeColumns, _getAvailableAttributes, filtering, etc.)
 * are available out of the box — exactly like the main product grid or the
 * catalog/category products tab.
 *
 * Block type handled: adminhtml/promo_widget_chooser_sku
 *   (Mage_Adminhtml_Block_Promo_Widget_Chooser_Sku)
 */
class BL_CustomGrid_Model_Grid_Type_Promo_Widget_Chooser_Sku
    extends BL_CustomGrid_Model_Grid_Type_Product
{
    protected function _getSupportedBlockTypes()
    {
        return array('adminhtml/promo_widget_chooser_sku');
    }

    /**
     * The chooser grid ID is generated dynamically
     * ('skuChooserGrid_' + unique hash), so we can only match by block type.
     */
    public function matchGridBlock($blockType, $blockId, BL_CustomGrid_Model_Grid $gridModel)
    {
        return ($blockType == $gridModel->getBlockType());
    }

    /**
     * Lock the in_products checkbox column so users cannot accidentally break
     * the chooser JavaScript by changing its renderer or making it sortable.
     */
    protected function _getColumnsLockedValues($blockType)
    {
        return array(
            'in_products' => array(
                'renderer' => '',
                'config_values' => array(
                    'filter'   => false,
                    'sortable' => false,
                ),
            ),
        );
    }

    /**
     * The chooser is a popup grid — export makes no sense here.
     */
    public function canExport($blockType)
    {
        return false;
    }
}
