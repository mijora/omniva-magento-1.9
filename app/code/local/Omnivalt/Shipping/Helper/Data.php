<?php

class Omnivalt_Shipping_Helper_Data extends Mage_Core_Helper_Abstract
{
  public $_omnivaltMethods = array('omnivalt_PARCEL_TERMINAL','omnivalt_COURIER');
   
  public function isOmnivaltMethod($order)
  {
    $order_shipping_method = $order->getData('shipping_method');
    return in_array($order_shipping_method, $this->_omnivaltMethods);
  }
}