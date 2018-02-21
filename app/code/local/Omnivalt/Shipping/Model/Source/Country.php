<?php

class Omnivalt_Shipping_Model_Source_Country
{
    public function toOptionArray()
    {
        $arr = array();
        $arr[] = array('value' => 'EE', 'label' => Mage::helper('omnivalt_shipping')->__('Estonia'));
        $arr[] = array('value' => 'LV', 'label' => Mage::helper('omnivalt_shipping')->__('Latvia'));
        $arr[] = array('value' => 'LT', 'label' => Mage::helper('omnivalt_shipping')->__('Lithuania'));
        return $arr;
    }
}
