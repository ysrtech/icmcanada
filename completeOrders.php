<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1); 
require_once("app/Mage.php");
$app = Mage::app("default");

$orders = Mage::getModel('sales/order')->getCollection()
      ->addFieldToFilter('status',array('neq' => 'complete'));
$i=0;
$p=0;
    foreach ($orders as $order) {

        $payment = $order->getPayment();
        
        
        
        if($order->canInvoice() && $payment){
        
        $invoice = $order->prepareInvoice()
                ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)
                               ->setTransactionId($order->getId())
                               ->addComment("Invoice created automaticaly by batch complete")
                               ->register()
                               ->pay();

            $transaction_save = Mage::getModel('core/resource_transaction')
                                        ->addObject($invoice)
                                        ->addObject($invoice->getOrder());

            $transaction_save->save();
            //now create shipment
            //after creation of shipment, the order auto gets status COMPLETE
             if ($order->canShip()) {
            $shipment = $order->prepareShipment();
            if( $shipment ) {
                 $shipment->register();
                 $order->setIsInProcess(true);

                 $transaction_save = Mage::getModel('core/resource_transaction')
                                            ->addObject($shipment)
                                            ->addObject($shipment->getOrder())
                                            ->save();
            }
             }
             $i++;
        }
        
        
        if(!$payment){continue;};
        $paymentMethod = $payment->getMethodInstance()->getCode();
        if($paymentMethod == 'ccsave' && $payment->getCcNumberEnc() != NULL){
            $payment->setCcNumberEnc(NULL);
            $payment->setCcExpMonth(NULL);
            $payment->setCcExpYear(NULL);
            $payment->setCcOwner(NULL);
            $payment->save();
            $p++;
        }
    }
echo $i.' orders marked as complete, '.$p.' orders cleared cc data';    