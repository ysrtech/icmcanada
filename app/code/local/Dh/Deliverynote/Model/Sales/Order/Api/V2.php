<?php
class Dh_Deliverynote_Model_Sales_Order_Api_V2 extends Mage_Sales_Model_Order_Api_V2
{

    public function info($orderIncrementId)
    {
        $result = parent::info($orderIncrementId);
        $order  = $this->_initOrder($orderIncrementId);


        $noteId = $order->getData('delivery_note_id');
        
        if ($noteId != '') {
        
        $note = Mage::getModel('deliverynote/note')->load($noteId)->getNote();
        }else{
            $note = '';
        }
        
        $result['order_note'] = $note;

        return $result;
    }

   
}
