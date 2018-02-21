<?php

class Omnivalt_Shipping_Model_Source_Terminal
{
    public function toOptionArray()
    {
        $omnivalt = Mage::getSingleton('Omnivalt_Shipping_Model');
        $arr = array();
        foreach ($omnivalt->getCode('terminal') as $k => $v) {
            $arr[] = array('value' => $k, 'label' => $v);
        }
        return $arr;
    }
}

