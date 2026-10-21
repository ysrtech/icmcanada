<?php

class Dh_Deliverynote_Block_Adminhtml_Widget_Grid_Column_Renderer_DeliveryNote extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{
    public function render(Varien_Object $row)
    {
        $noteId = $this->_hasNote($row);
        
       if ($noteId != '') {
			$html = 'Note';
		}
        return $html;
    }

    private function _hasNote(Varien_Object $row)
    {
        $noteId ='';
        $order = Mage::getModel('sales/order')->load($row->getId());
        if (!$order->getId()) {
            return false;
        }
        $noteId = $order->getData('delivery_note_id');
        
        return $noteId ;

    }

    public function getFilter()
    {
        return false;
    }

}
