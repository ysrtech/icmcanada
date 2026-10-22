<?php

/**
 * ICM customizations
 *
 * PDF tax total: only show the per-rate breakdown (GST/PST/QST lines), not the extra combined "Tax" line.
 * Replaces the old full copy of app/code/local/Mage/Tax/Model/Sales/Pdf/Tax.php.
 */
class ICM_Customizations_Model_Tax_Sales_Pdf_Tax extends Mage_Tax_Model_Sales_Pdf_Tax
{
    /**
     * @return array
     */
    public function getTotalsForDisplay()
    {
        $store = $this->getOrder()->getStore();
        $config = Mage::getSingleton('tax/config');
        if ($config->displaySalesTaxWithGrandTotal($store)) {
            return [];
        }

        $totals = [];

        if ($config->displaySalesFullSummary($store)) {
            $totals = $this->getFullTaxInfo();
        }

        return $totals;
    }
}
