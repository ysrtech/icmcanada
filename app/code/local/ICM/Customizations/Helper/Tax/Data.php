<?php

/**
 * ICM customizations
 *
 * Canadian tax split on invoices/credit memos (replaces the old full copy of app/code/local/Mage/Tax/Helper/Data.php).
 */
class ICM_Customizations_Helper_Tax_Data extends Mage_Tax_Helper_Data
{
    /**
     * Get calculated taxes for each tax class
     *
     * This method returns array with format:
     * array(
     *  $index => array(
     *      'tax_amount'        => $taxAmount,
     *      'base_tax_amount'   => $baseTaxAmount,
     *      'hidden_tax_amount' => $hiddenTaxAmount,
     *      'title'             => $title,
     *      'percent'           => $percent
     *  )
     * )
     *
     * @param  Mage_Sales_Model_Order $source
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getCalculatedTaxes($source)
    {
        if ($this->_getFromRegistry('current_invoice')) {
            $current = $this->_getFromRegistry('current_invoice');
        } elseif ($this->_getFromRegistry('current_creditmemo')) {
            $current = $this->_getFromRegistry('current_creditmemo');
        } else {
            $current = $source;
        }

        $taxClassAmount = [];
        if ($current && $source) {
            $rates = $this->_getTaxRateSubtotals($source);
            $totalTaxRate = 0;
            foreach ($rates['items'] as $rate) {
                $totalTaxRate += $rate['percent'];
            }

            if ($current == $source) {
                // use the actuals
                foreach ($rates['items'] as $rate) {
                    $taxClassId = $rate['tax_id'];
                    $taxClassAmount[$taxClassId]['tax_amount'] = $rate['amount'];
                    $taxClassAmount[$taxClassId]['base_tax_amount'] = $rate['base_amount'];
                    $taxClassAmount[$taxClassId]['title'] = $rate['title'];
                    $taxClassAmount[$taxClassId]['percent'] = $rate['percent'];
                }
            } else {
                // regenerate tax subtotals
                // Calculate taxes for shipping
                // ICM: split the shipping tax across the order's tax rates (GST/PST/QST) by their share
                $shippingTaxAmount = $current->getShippingTaxAmount();
                $baseShippingTaxAmount = $current->getBaseShippingTaxAmount();
                if ($shippingTaxAmount && $totalTaxRate > 0) {
                    foreach ($rates['items'] as $rate) {
                        $taxClassId = $rate['tax_id'];
                        $taxClassAmount[$taxClassId]['tax_amount'] = $shippingTaxAmount / $totalTaxRate * $rate['percent'];
                        $taxClassAmount[$taxClassId]['base_tax_amount'] = $baseShippingTaxAmount / $totalTaxRate * $rate['percent'];
                        $taxClassAmount[$taxClassId]['title'] = $rate['title'];
                        $taxClassAmount[$taxClassId]['percent'] = $rate['percent'];
                    }
                } elseif ($shippingTaxAmount) {
                    $shippingTax    = Mage::helper('tax')->getShippingTax($current);
                    $taxClassAmount = array_merge($taxClassAmount, $shippingTax);
                }

                // ICM: taxes on Amasty checkout fees, when that extension is present
                $checkoutFees = $current->getAmcheckoutfeesAmount();
                $checkoutBaseFees = $current->getBaseAmcheckoutfeesAmount();
                if ($checkoutFees) {
                    foreach ($rates['items'] as $rate) {
                        $taxClassId = $rate['tax_id'];
                        $taxClassAmount[$taxClassId]['tax_amount'] = ($taxClassAmount[$taxClassId]['tax_amount'] ?? 0)
                            + $checkoutFees * $rate['percent'] / 100;
                        $taxClassAmount[$taxClassId]['base_tax_amount'] = ($taxClassAmount[$taxClassId]['base_tax_amount'] ?? 0)
                            + $checkoutBaseFees * $rate['percent'] / 100;
                        $taxClassAmount[$taxClassId]['title'] = $rate['title'];
                        $taxClassAmount[$taxClassId]['percent'] = $rate['percent'];
                    }
                }

                foreach ($current->getItemsCollection() as $item) {
                    $taxCollection = Mage::getResourceModel('tax/sales_order_tax_item')
                        ->getTaxItemsByItemId(
                            $item->getOrderItemId() ? $item->getOrderItemId() : $item->getItemId(),
                        );

                    foreach ($taxCollection as $tax) {
                        $taxClassId = $tax['tax_id'];
                        $percent = $tax['tax_percent'];

                        $price = $item->getRowTotal();
                        $basePrice = $item->getBaseRowTotal();
                        if ($this->applyTaxAfterDiscount($item->getStoreId())) {
                            $price = $price - $item->getDiscountAmount() + $item->getHiddenTaxAmount();
                            $basePrice = $basePrice - $item->getBaseDiscountAmount() + $item->getBaseHiddenTaxAmount();
                        }

                        $taxAmount = $price * $percent / 100;
                        $baseTaxAmount = $basePrice * $percent / 100;

                        if (isset($taxClassAmount[$taxClassId])) {
                            $taxClassAmount[$taxClassId]['tax_amount'] += $taxAmount;
                            $taxClassAmount[$taxClassId]['base_tax_amount'] += $baseTaxAmount;
                        } else {
                            $taxClassAmount[$taxClassId]['tax_amount'] = $taxAmount;
                            $taxClassAmount[$taxClassId]['base_tax_amount'] = $baseTaxAmount;
                            $taxClassAmount[$taxClassId]['title'] = $tax['title'];
                            $taxClassAmount[$taxClassId]['percent'] = $tax['percent'];
                        }
                    }
                }
            }

            foreach ($taxClassAmount as $key => $tax) {
                if ($tax['tax_amount'] == 0 && $tax['base_tax_amount'] == 0) {
                    unset($taxClassAmount[$key]);
                }
            }

            $taxClassAmount = array_values($taxClassAmount);
        }

        return $taxClassAmount;
    }
}
