<?php

/**
 * ICM customizations
 *
 * Keep the original image file name when moving/copying product images (overwrite instead of
 * creating image_1.jpg, image_2.jpg ...), so re-imported images keep their URLs.
 * Replaces the old full copy of app/code/local/Mage/Catalog/Model/Product/Attribute/Backend/Media.php.
 */
class ICM_Customizations_Model_Catalog_Product_Attribute_Backend_Media extends Mage_Catalog_Model_Product_Attribute_Backend_Media
{
    /**
     * @param  string $file
     * @param  string $dirsep
     * @return string
     */
    protected function _getUniqueFileName($file, $dirsep)
    {
        return $file;
    }
}
