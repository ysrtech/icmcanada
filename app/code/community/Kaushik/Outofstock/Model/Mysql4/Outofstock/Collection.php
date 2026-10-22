<?php

class Kaushik_Outofstock_Model_Mysql4_Outofstock_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('outofstock/outofstock');
    }
}