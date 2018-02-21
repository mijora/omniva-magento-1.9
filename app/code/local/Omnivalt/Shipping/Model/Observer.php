<?php
class Omnivalt_Shipping_Model_Observer
{
  public function saveParcelTerminal(Varien_Event_Observer $observer)
  {
    $param = Mage::app()->getRequest()->getParam('omnivalt_parcel_terminal', '');
    $observer->getQuote()->setParcelTerminal((string) $param);
  }
  public function addMassAction($observer)
  {
    $block = $observer->getEvent()->getBlock();
    if (get_class($block) == 'Mage_Adminhtml_Block_Widget_Grid_Massaction' && $block->getRequest()->getControllerName() == 'sales_order') {
      $block->addItem('omnivaltshipment', array(
        'label' => Mage::helper('shipping')->__('Generate Omnivalt labels'),
        'url' => Mage::app()->getStore()->getUrl('omnivalt_shipping/adminhtml_label/CreateShipment')
      ));
      $block->addItem('omnivaltmanifest', array(
        'label' => Mage::helper('shipping')->__('Print Omnivalt manifest'),
        'url' => Mage::app()->getStore()->getUrl('omnivalt_shipping/adminhtml_label/CreateManifest')
      ));
    }
  }
  public function callOmnivaButton($observer)
    {   
        $container = $observer->getBlock();
        if(null !== $container && $container->getType() == 'adminhtml/sales_order') {
            $data = array(
                'label'     => Mage::helper('shipping')->__('Call Omniva'),
                'class'     => '',
                'onclick'   => "callOmniva('".Mage::helper("adminhtml")->getUrl('omnivalt_shipping/adminhtml_label/CallOmniva')."')",
            );
            $container->addButton('unique-identifier', $data);
        }

        return $this;
    }
}