<?php
/**
 * Dot Collective - DC_Catalog
 *
 * @category   DC
 * @package    DC_Catalog
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DC_Catalog_Block_Admin_Manufacturer_Edit_Tab_Translation extends Mage_Core_Block_Abstract
{
    /**
     * Translatable fields: field_name => human label
     */
    protected $_translatableFields = array(
        'page_title'          => 'Page Title',
        'description'         => 'Content',
        'description_in_page' => 'Description in product page',
        'meta_keywords'       => 'Meta Keywords',
        'meta_description'    => 'Meta Description',
    );

    protected function _toHtml()
    {
        $model    = Mage::registry('dc_catalog_manufacturer');
        $helper   = Mage::helper('adminhtml');

        $attrCode = $model->getAttributeCode();
        $optionId = (int)($model->getAttributeOptionId() ?: $model->getOptionId());

        if (!$attrCode || !$optionId) {
            return '<p style="padding:15px;">'
                . $helper->__('Save the page first before managing store-view translations.')
                . '</p>';
        }

        // Load default (store=0) raw row for hint values
        $defaultRow = $this->_loadRawStoreRow($attrCode, $optionId, 0);

        // All store views (excludes admin store, includes store_id > 0 only)
        $stores = Mage::app()->getStores(false);
        if (empty($stores)) {
            return '<p style="padding:15px;">' . $helper->__('No store views found.') . '</p>';
        }

        ob_start();
        ?>
        <style type="text/css">
            #dc-trans-wrap { padding: 10px 15px; }
            .dc-trans-store { border: 1px solid #ccc; margin-bottom: 8px; border-radius: 3px; }
            .dc-trans-store-hd {
                background: #f0f0f0; padding: 9px 14px; cursor: pointer;
                font-weight: bold; font-size: 13px; user-select: none;
                display: flex; justify-content: space-between; align-items: center;
            }
            .dc-trans-store-hd:hover { background: #e5e5e5; }
            .dc-trans-store-hd .dc-trans-arrow { font-size: 10px; color: #666; }
            .dc-trans-store-bd { padding: 14px 18px; display: none; }
            .dc-trans-store-bd.dc-open { display: block; }
            .dc-trans-field { margin-bottom: 14px; }
            .dc-trans-field > label.dc-lbl { display: block; font-weight: bold; margin-bottom: 4px; color: #333; }
            .dc-trans-field textarea,
            .dc-trans-field input[type="text"] {
                width: 100%; box-sizing: border-box; border: 1px solid #b6b6b6;
                padding: 4px 6px; font-size: 12px;
            }
            .dc-trans-field textarea { height: 80px; resize: vertical; }
            .dc-trans-field input[type="text"] { height: 28px; }
            .dc-trans-field.dc-title-field input[type="text"] { max-width: 500px; }
            .dc-trans-use-def { margin-top: 5px; }
            .dc-trans-use-def label { cursor: pointer; color: #555; font-size: 11px; }
            .dc-trans-hint { color: #888; font-size: 11px; font-style: italic; margin-top: 2px; display: block; }
            .dc-trans-badge-new    { color: #c07700; font-size: 11px; font-weight: normal; margin-left: 8px; }
            .dc-trans-badge-exists { color: #2a7a00; font-size: 11px; font-weight: normal; margin-left: 8px; }
            .dc-field-off { background: #f5f5f5 !important; color: #aaa !important; }
        </style>

        <div id="dc-trans-wrap">
        <?php foreach ($stores as $store): ?>
        <?php
            $storeId   = (int)$store->getId();
            $storeName = $helper->escapeHtml($store->getName() . ' [' . $store->getCode() . ']');
            $storeRow  = $this->_loadRawStoreRow($attrCode, $optionId, $storeId);
            $hasRow    = !empty($storeRow);
            $isOpen    = $hasRow ? ' dc-open' : '';
            $badge     = $hasRow
                ? '<span class="dc-trans-badge-exists">&#10003; ' . $helper->__('Has translation') . '</span>'
                : '<span class="dc-trans-badge-new">+ ' . $helper->__('No translation yet') . '</span>';
        ?>
        <div class="dc-trans-store" id="dc_ts_<?php echo $storeId ?>">
            <div class="dc-trans-store-hd" onclick="dcTransToggle(<?php echo $storeId ?>)">
                <span><?php echo $storeName . $badge ?></span>
                <span class="dc-trans-arrow" id="dc_ts_arrow_<?php echo $storeId ?>"><?php echo $hasRow ? '&#9650;' : '&#9660;' ?></span>
            </div>
            <div class="dc-trans-store-bd<?php echo $isOpen ?>" id="dc_ts_bd_<?php echo $storeId ?>">
            <?php foreach ($this->_translatableFields as $fieldName => $fieldLabel): ?>
            <?php
                $inputId    = 'dc_ti_' . $storeId . '_' . $fieldName;
                $inputName  = 'translations[' . $storeId . '][' . $fieldName . ']';
                $cbId       = 'dc_tc_' . $storeId . '_' . $fieldName;
                $cbName     = 'translations[' . $storeId . '][use_default_' . $fieldName . ']';

                $curVal     = ($hasRow && isset($storeRow[$fieldName])) ? $storeRow[$fieldName] : '';
                $useDefault = !$hasRow || !empty($storeRow['use_default_' . $fieldName]);

                $defVal     = isset($defaultRow[$fieldName]) ? $defaultRow[$fieldName] : '';
                $defPlain   = strip_tags($defVal);
                $hint       = mb_strlen($defPlain) > 110 ? mb_substr($defPlain, 0, 110) . '…' : $defPlain;
                $hint       = $helper->escapeHtml($hint);

                $disabledAttr = $useDefault ? ' disabled="disabled"' : '';
                $cbChecked    = $useDefault ? ' checked="checked"' : '';
                $fieldOffCls  = $useDefault ? ' dc-field-off' : '';
                $escapedVal   = $helper->escapeHtml($curVal);
                $isTitleField = ($fieldName === 'page_title') ? ' dc-title-field' : '';
            ?>
            <div class="dc-trans-field<?php echo $isTitleField ?>">
                <label class="dc-lbl" for="<?php echo $inputId ?>"><?php echo $helper->__($fieldLabel) ?></label>
                <?php if ($fieldName === 'page_title'): ?>
                <input type="text"
                       id="<?php echo $inputId ?>"
                       name="<?php echo $inputName ?>"
                       value="<?php echo $escapedVal ?>"
                       class="<?php echo $fieldOffCls ?>"
                       <?php echo $disabledAttr ?> />
                <?php else: ?>
                <textarea id="<?php echo $inputId ?>"
                          name="<?php echo $inputName ?>"
                          class="<?php echo $fieldOffCls ?>"
                          <?php echo $disabledAttr ?>><?php echo $escapedVal ?></textarea>
                <?php endif; ?>
                <div class="dc-trans-use-def">
                    <input type="checkbox"
                           id="<?php echo $cbId ?>"
                           name="<?php echo $cbName ?>"
                           value="1"
                           <?php echo $cbChecked ?>
                           onclick="dcTransToggleField('<?php echo $inputId ?>', this)" />
                    <label for="<?php echo $cbId ?>"><?php echo $helper->__('Use Default Value') ?></label>
                    <?php if ($hint !== ''): ?>
                    <span class="dc-trans-hint"><?php echo $hint ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <script type="text/javascript">
        function dcTransToggle(storeId) {
            var bd    = $('dc_ts_bd_'    + storeId);
            var arrow = $('dc_ts_arrow_' + storeId);
            if (!bd) return;
            if (bd.hasClassName('dc-open')) {
                bd.removeClassName('dc-open');
                if (arrow) arrow.update('&#9660;');
            } else {
                bd.addClassName('dc-open');
                if (arrow) arrow.update('&#9650;');
            }
        }
        function dcTransToggleField(inputId, checkbox) {
            var el = $(inputId);
            if (!el) return;
            el.disabled = checkbox.checked;
            if (checkbox.checked) {
                el.addClassName('dc-field-off');
            } else {
                el.removeClassName('dc-field-off');
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Load the raw DB row for a specific attribute+option+store combination.
     * Returns an associative array or false if no row exists.
     *
     * @param  string $attrCode
     * @param  int    $optionId
     * @param  int    $storeId
     * @return array|false
     */
    protected function _loadRawStoreRow($attrCode, $optionId, $storeId)
    {
        /** @var Varien_Db_Adapter_Interface $read */
        $read  = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = Mage::getSingleton('core/resource')->getTableName('dc_catalog/manufacturer');

        $select = $read->select()
            ->from($table)
            ->where('attribute_code = ?',          $attrCode)
            ->where('attribute_option_id = ?',     (int)$optionId)
            ->where('attribute_value_store_id = ?', (int)$storeId)
            ->limit(1);

        $row = $read->fetchRow($select);
        return $row ?: false;
    }
}
