<?php

/**
 * ICM customizations
 *
 * Canadian tax rows (replaces the old full copy of app/code/local/Mage/Tax/Model/Resource/Calculation.php).
 */
class ICM_Customizations_Model_Tax_Resource_Calculation extends Mage_Tax_Model_Resource_Calculation
{
    /**
     * Retrieve Calculation Process
     *
     * @param  null|Varien_Object $request
     * @param  null|array         $rates
     * @return array
     */
    public function getCalculationProcess($request, $rates = null)
    {
        if (is_null($rates)) {
            $rates = $this->_getRates($request);
        }

        $result = [];
        $row = [];
        $ids = [];
        $currentRate = 0;
        $totalPercent = 0;
        $countedRates = count($rates);
        for ($index = 0; $index < $countedRates; $index++) {
            $rate = $rates[$index];
            $value = ($rate['value'] ?? $rate['percent']) * 1;

            $oneRate = [
                'code' => $rate['code'],
                'title' => $rate['title'],
                'percent' => $value,
                'position' => $rate['position'],
                'priority' => $rate['priority'],
            ];
            if (isset($rate['tax_calculation_rule_id'])) {
                $oneRate['rule_id'] = $rate['tax_calculation_rule_id'];
            }

            if (isset($rate['hidden'])) {
                $row['hidden'] = $rate['hidden'];
            }

            if (isset($rate['amount'])) {
                $row['amount'] = $rate['amount'];
            }

            if (isset($rate['base_amount'])) {
                $row['base_amount'] = $rate['base_amount'];
            }

            if (isset($rate['base_real_amount'])) {
                $row['base_real_amount'] = $rate['base_real_amount'];
            }

            $row['rates'][] = $oneRate;

            $ruleId = null;
            if (isset($rates[$index + 1]['tax_calculation_rule_id'])) {
                $ruleId = $rate['tax_calculation_rule_id'];
            }

            $priority = $rate['priority'];
            $ids[] = $rate['code'];

            if (isset($rates[$index + 1]['tax_calculation_rule_id'])) {
                while (isset($rates[$index + 1]) && $rates[$index + 1]['tax_calculation_rule_id'] == $ruleId) {
                    $index++;
                }
            }

            $currentRate += $value;

            // ICM (Canada): every distinct tax code (GST, PST/QST, HST) becomes its own row so that
            // compounded provincial taxes are shown and split separately on totals, invoices and PDFs
            $nextCode = $rates[$index + 1]['code'] ?? null;
            if (!isset($rates[$index + 1]) || $rates[$index + 1]['priority'] != $priority
                || (isset($rates[$index + 1]['process']) && $rates[$index + 1]['process'] != $rate['process'])
                || $nextCode != $rate['code']
            ) {
                if ($nextCode != $rate['code']) {
                    $row['percent'] = $value;
                } else {
                    $row['percent'] = (100 + $totalPercent) * ($currentRate / 100);
                }

                $row['id'] = implode('', $ids);
                $result[] = $row;
                $row = [];
                $ids = [];

                $totalPercent += (100 + $totalPercent) * ($currentRate / 100);
                $currentRate = 0;
            }
        }

        return $result;
    }
}
