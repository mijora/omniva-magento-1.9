<?php



class Omnivalt_Shipping_Model_Source_Method
{
    public function toOptionArray()
    {
        $omnivalt = Mage::getSingleton('Omnivalt_Shipping_Model_Carrier');
        $arr = array();
        foreach ($omnivalt->getCode('method') as $k => $v) {
            $arr[] = array('value' => $k, 'label' => $v);
        }
        return $arr;
    }
}
