<?php

class Omnivalt_Shipping_Adminhtml_LabelController extends Mage_Adminhtml_Controller_Action
{
  /**
   * Collect post arguments
   *
   * @param Key of array
   * @return Array
   */
  private function _collectPostData($post_key = null)
  {
    return $this->getRequest()->getPost($post_key);
  }
  
  
  private function _fillDataBase($order_ids = array())
  {
    $pack_data = array();
    $order_ids = array_unique($order_ids);
    foreach ($order_ids as $order_id) {
      $pack_no = array();
      $order   = Mage::getModel('sales/order')->load($order_id); //Load order
      if (!Mage::helper('omnivalt_shipping/data')->isOmnivaltMethod($order)) {
        $text = 'Warning: Order ' . $order->getData('increment_id') . ' not Omnivalt shipping method.';
        Mage::getSingleton('adminhtml/session')->addWarning($text);
        continue;
      }
      if (!$order->getShippingAddress()) { //Is set Shipping adress?
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
          $ordered_items['sku'][]  = $item->getSku();
          $ordered_items['type'][] = $item->getProductType();
        }
        $text = 'Warning: Order ' . $order->getData('increment_id') . ' not have Shipping Address.';
        Mage::getSingleton('adminhtml/session')->addWarning($text);
        continue;
      }
      /*
      
      if ($order->getManifestGenerationDate() != NULL){
        $text = 'Warning: Order ' . $order->getData('increment_id') . ' manifest already generated at '.$order->getManifestGenerationDate().'.';
        Mage::getSingleton('adminhtml/session')->addWarning($text);
        continue;
      }
      */
      $pack_data[] = $order;
    }
    
    return $pack_data;
  }
  
  /**
   * Get Order data by order Id
   *
   * @param Order entity id
   * @param Order data who return
   * @return value from Sales/Order object
   */
  private function _getOrderData($order_id, $order_data)
  {
    $order = Mage::getSingleton('sales/order')->load($order_id); //Load order
    return $order->getData($order_data);
  }
  
  public function CreateShipmentAction()
  {
    $pack_data     = array();
    $success_files = array();
    $order_ids     = $this->_collectPostData('order_ids');
    if (!isset($order_ids)) {
      return false; //If something wrong
    }
    $pack_data = $this->_fillDataBase($order_ids); //Send data to server and get packs number's
    if (!count($pack_data) || $pack_data === false) { //If nothing to print
      $this->_redirectReferer();
      return;
    } else { //If found Order who can get Label so Do it
      $order_ids = array();
      foreach ($pack_data as $order) {
        $this->_createShipment($order->increment_id);
      }
      $this->_redirectReferer();
      return;
    }
  }
  
  public function _createShipment($orderIncrementId)
  {
    $label = false;
    $order         = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
    // Create Qty array
    $shipmentItems = array();
    foreach ($order->getAllItems() as $item) {
      $shipmentItems[$item->getId()] = $item->getQtyToShip();
    }
    // Prepear shipment and save ....
    if ($order->getId() && !empty($shipmentItems) && $order->canShip()) {
      $shipment = false;
      if ($order->hasShipments()) {
        foreach ($order->getShipmentsCollection() as $_shipment) {
          $shipment = $_shipment; //get last shipment            
        }
      }
      if (!$shipment)
        $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($shipmentItems);
      $label = $this->_createShippingLabel($shipment);
      if (!$label) {
        Mage::getSingleton('adminhtml/session')->addWarning('Warning: Shipment not generated for order ' . $orderIncrementId);
      } else {
        Mage::getSingleton('adminhtml/session')->addSuccess('Success: Order ' . $orderIncrementId . ' shipment generated');
      }
      $shipment->save();
      
      //$order->setIsInProcess(true);
      $order->addStatusHistoryComment('Automatically SHIPPED by Omnivalt mass action.', false);
      $order->save();
    } else {
      Mage::getSingleton('adminhtml/session')->addWarning('Warning: Order ' . $orderIncrementId . ' is empty or cannot be shipped or has been shipped already');
    }
    return $label;
  }
  protected function _createShippingLabel(Mage_Sales_Model_Order_Shipment $shipment)
  {
    if (!$shipment) {
      return false;
    }
    $carrier = $shipment->getOrder()->getShippingCarrier();
    if (!$carrier->isShippingLabelsAvailable()) {
      return false;
    }
    //for sample, not used in label
    $packages = array(
      1 => array(
        'params' => array(
          'container' => '',
          'weight' => '2',
          'customs_value' => '5',
          'length' => '',
          'width' => '',
          'height' => '',
          'weight_units' => 'POUND',
          'dimension_units' => 'INCH',
          'content_type' => '',
          'content_type_other' => ''
        ),
        'items' => array(
          8 => array(
            'qty' => '1',
            'customs_value' => '5',
            'price' => '5.0000',
            'name' => 'Krepsys',
            'weight' => '2.0000',
            'product_id' => '1',
            'order_item_id' => '20'
          )
        )
      )
    );
    $shipment->setPackages($packages);
    $response = Mage::getModel('shipping/shipping')->requestToShipment($shipment);
    if ($response->hasErrors()) {
      Mage::getSingleton('adminhtml/session')->addWarning('Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ': ' . $response->getErrors());
      return false;
    }
    if (!$response->hasInfo()) {
      return false;
    }
    $labelsContent   = array();
    $trackingNumbers = array();
    $info            = $response->getInfo();
    foreach ($info as $inf) {
      if (!empty($inf['tracking_number']) && !empty($inf['label_content'])) {
        $labelsContent[]   = $inf['label_content'];
        $trackingNumbers[] = $inf['tracking_number'];
      }
    }
    $outputPdf = $this->_combineLabelsPdfZend($labelsContent);
    $shipment->setShippingLabel($outputPdf->render());
    $carrierCode  = $carrier->getCarrierCode();
    $carrierTitle = Mage::getStoreConfig('carriers/' . $carrierCode . '/title', $shipment->getStoreId());
    if ($trackingNumbers) {
      foreach ($shipment->getAllTracks() as $track) {
        $track->delete();
      }
      foreach ($trackingNumbers as $trackingNumber) {
        $track = Mage::getModel('sales/order_shipment_track')->setNumber($trackingNumber)->setCarrierCode($carrierCode)->setTitle($carrierTitle);
        $shipment->addTrack($track);
      }
    } else {
      $text = 'Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ' has not received tracking numbers.';
      Mage::getSingleton('adminhtml/session')->addWarning($text);
    }
    return true;
  }
  
  private function _combineLabelsPdf(array $labelsContent)
  {
    $pdf = new FPDI_FPDI('P');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $tmp_files = array();
    $count = 1;
    $labels = 4;
    foreach ($labelsContent as $content) {
      if (!$content) continue;
      $prefix = rand(100,999).time();
      $label_url = realpath(dirname(__FILE__)).'/'.$prefix.'.pdf';
      file_put_contents($label_url,$content);
      $pagecount = $pdf->setSourceFile($label_url);
      for ($i = 1; $i <= $pagecount; $i++) {
        $tplidx = $pdf->ImportPage($i);
        if( $labels >= 4) {
           $pdf->AddPage();
           $labels = 0;
        }
        if($labels == 0) {
          $pdf->useTemplate($tplidx, 5, 15, 94.5, 108, false);
        } else if ($labels == 1) {
          $pdf->useTemplate($tplidx, 110, 15, 94.5, 108, false);
        } else if ($labels == 2) {
          $pdf->useTemplate($tplidx, 5, 140, 94.5, 108, false);  
        } else if ($labels == 3) {
          $pdf->useTemplate($tplidx, 110, 140, 94.5, 108, false);  
        }
        $labels++;
      }
      unlink($label_url);
    }
    return $pdf;
  }
  
  protected function _combineLabelsPdfZend(array $labelsContent)
  {
    $outputPdf = new Zend_Pdf();
    foreach ($labelsContent as $content) {
      if (stripos($content, '%PDF-') !== false) {
        $pdfLabel = Zend_Pdf::parse($content);
        foreach ($pdfLabel->pages as $page) {
          $outputPdf->pages[] = clone $page;
        }
      } else {
        $page = $this->_createPdfPageFromImageString($content);
        if ($page) {
          $outputPdf->pages[] = $page;
        }
      }
    }
    return $outputPdf;
  }
  
  protected function _isAllowed()
  {
    return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions');
  }
  
  public function PrintLabelsAction()
  {
      $order_ids = $this->_collectPostData('order_ids');
      if (!is_array($order_ids)) {
        //not array - redirect back
        $this->_redirectReferer();
        return;
      }
      $pdfs = array();
      foreach ($order_ids as $order_id) {
        $order   = Mage::getModel('sales/order')->load($order_id); 
        if (!$order->getId()) continue;
        $pdf = false;
        if (!$order->hasShipments()) {
          $shipment_create = $this->_createShipment($order->increment_id);
        } 
        foreach ($order->getShipmentsCollection() as $shipment) {
          $pdf =  $shipment->getShippingLabel();
          if (!$pdf){
            $label = $this->_createShippingLabel($shipment);
          }
          $pdf =  $shipment->getShippingLabel();
          if (!$pdf) continue;
          $pdfs[] = $pdf;          
        }
      }
      if (empty($pdfs)){
        $text = $this->__('Warning: No labels found');
        Mage::getSingleton('adminhtml/session')->addWarning($text);
        $this->_redirectReferer();
        return;
      }
      $pdf = $this->_combineLabelsPdf($pdfs);
      $pdf->Output('Omnivalt_labels.pdf','D');
      die();
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="Omniva_labels.pdf"');
      header('Cache-Control: private, max-age=0, must-revalidate');
      header('Pragma: public');
      ini_set('zlib.output_compression','0');
      echo $pdf->render();
      die();
  }
  
  public function CreateManifestAction()
  {
    $order_ids = $this->_collectPostData('order_ids');
    if (!isset($order_ids)) {
      $this->_redirectReferer();
      return;
    }
    $generation_date = date('Y-m-d H:i:s');
    $omnivalt = Mage::getSingleton('Omnivalt_Shipping_Model_Carrier');
    $pdf = new TCPDF_TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $order_table = '';
    $count = 0;
    $pack_data = $this->_fillDataBase($order_ids); //Send data to server and get packs number's
    if (!count($pack_data) || $pack_data === false) { //If nothing to print
      $this->_redirectReferer();
      return;
    } else { 
      $order_ids = array();
      foreach ($pack_data as $order) {
        $track_numer = '';
        $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')->setOrderFilter($order)->load();
        foreach ($shipmentCollection as $shipment) {
          foreach($shipment->getAllTracks() as $tracknum)
          {
            $track_numer .= $tracknum->getNumber().' ';
          }
        }
        if ($track_numer == ''){
          $text = 'Warning: Order ' . $order->getData('increment_id') . ' has no tracking number. Will not be included in manifest.';
          Mage::getSingleton('adminhtml/session')->addWarning($text);
          continue;
        }
        $count++; 
        $order->setManifestGenerationDate($generation_date);
        $order->save();
        $shippingAddress = $order->getShippingAddress();
        $country = Mage::getModel('directory/country')->loadByCode($shippingAddress->country_id);
        $parcel_terminal_address = '';
        if ($order->getData('shipping_method') == 'omnivalt_PARCEL_TERMINAL'){
            $terminal_id = $order->getParcelTerminal();
            $location_file = Mage::getModuleDir('etc', 'Omnivalt_Shipping') . DS . 'locations.xml';
            if (file_exists($location_file) && $terminal_id){
              $locationsXMLArray = simplexml_load_file($location_file);
              foreach ($locationsXMLArray->LOCATION as $loc_data) {
                if ($loc_data->ZIP == $terminal_id){
                  $parcel_terminal_address = $loc_data->NAME.', '.$loc_data->A2_NAME.', '.$loc_data->A0_NAME;
                }
              }
            }
        }
        $client_address = $shippingAddress->firstname.' '.$shippingAddress->lastname.', '.$shippingAddress->street.', '.$shippingAddress->postcode.', '.$shippingAddress->city.' '.$country->getName();
        if ($parcel_terminal_address != '')
          $client_address = '';
        $order_table .= '<tr><td width = "40" align="right">'.$count.'.</td><td>'.$track_numer.'</td><td width = "60">'.date('Y-m-d').'</td><td width = "40">1</td><td width = "60">'.$order->getWeight().'</td><td width = "210">'.$client_address.$parcel_terminal_address.'</td></tr>';

      }
    }
    $pdf->SetFont('freeserif', '', 14);
    $country = Mage::getModel('directory/country')->loadByCode(  $omnivalt->getConfigData('company_countrycode'));
    $shop_addr = '<table cellspacing="0" cellpadding="1" border="0"><tr><td>'.date('Y-m-d H:i:s').'</td><td>Siuntėjo adresas:<br/>'. $omnivalt->getConfigData('company').'<br/>'. $omnivalt->getConfigData('company_address').', '. $omnivalt->getConfigData('company_postcode').'<br/>'. $omnivalt->getConfigData('company_city').', '.$country->getName().'<br/></td></tr></table>';
    $pdf->writeHTML($shop_addr, true, false, false, false, '');
    $tbl = '
        <table cellspacing="0" cellpadding="4" border="1">
          <thead>
            <tr>
              <th width = "40" align="right">Nr.</th>
              <th>Siuntos numeris</th>
              <th width = "60">Data</th>
              <th width = "40" >Kiekis</th>
              <th width = "60">Svoris (kg)</th>
              <th width = "210">Gavėjo adresas</th>
            </tr>
          </thead>
          <tbody>
          '.$order_table.'
          </tbody>
        </table><br/><br/>
    ';
    if ($count > 0){
      //$result = $omnivalt->call_omniva();
    } else {
      $this->_redirectReferer();
      return;
    }
    $pdf->SetFont('freeserif', '', 9);
    $pdf->writeHTML($tbl, true, false, false, false, '');
    $pdf->SetFont('freeserif', '', 14);
    $sign = 'Kurjerio vardas, pavardė, parašas ________________________________________________<br/><br/>';
    $sign .= 'Siuntėjo vardas, pavardė, parašas ________________________________________________';
    $pdf->writeHTML($sign, true, false, false, false, '');
    $pdf->Output('Omnivalt_manifest_'.date('Y-m-d H.i.s').'.pdf','D');
  }
  
  public function CallOmnivaAction()
  {
    $omnivalt = Mage::getSingleton('Omnivalt_Shipping_Model_Carrier');
    $result = $omnivalt->call_omniva();
    if ($result){
      $text = $this->__('Omniva courier called');
      Mage::getSingleton('adminhtml/session')->addSuccess($text);
    } else {
      $text = $this->__('Failed to call Omniva courier');
      Mage::getSingleton('adminhtml/session')->addWarning($text);
    }
    $this->_redirectReferer();
    return;
  }
}