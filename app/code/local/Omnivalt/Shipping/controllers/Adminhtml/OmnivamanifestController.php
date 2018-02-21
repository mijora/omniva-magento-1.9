<?php

class Omnivalt_Shipping_Adminhtml_OmnivamanifestController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
      return Mage::getSingleton('admin/session')->isAllowed('sales');
    }
    
    public function indexAction()
    {
        $this->loadLayout()->_setActiveMenu('sales')->_title($this->__('Omniva manifest'));; 
        $this->renderLayout();
    }
}