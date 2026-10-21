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

class BL_CustomGrid_Model_System_Config_Source_Searchable_Dropdowns_Style extends BL_CustomGrid_Model_Source_Fixed
{
    protected $_optionHash = array(
        BL_CustomGrid_Helper_Config::SEARCHABLE_DROPDOWNS_STYLE_COMBO  => 'Dropdown With A Search Field Inside It',
        BL_CustomGrid_Helper_Config::SEARCHABLE_DROPDOWNS_STYLE_LEGACY => 'Search Field Above The Dropdown',
    );
}
