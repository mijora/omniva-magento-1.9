<?php

class Omnivalt_Shipping_Model_Carrier extends Mage_Usa_Model_Shipping_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
  
  /**
   * Code of the carrier
   *
   * @var string
   */
  const CODE = 'omnivalt';
  
  
  /**
   * Code of the carrier
   *
   * @var string
   */
  protected $_code = self::CODE;
  
  /**
   * Rate request data
   *
   * @var Mage_Shipping_Model_Rate_Request|null
   */
  protected $_request = null;
  
  /**
   * Raw rate request data
   *
   * @var Varien_Object|null
   */
  protected $_rawRequest = null;
  
  /**
   * Rate result data
   *
   * @var Mage_Shipping_Model_Rate_Result|null
   */
  protected $_result = null;
  
  /**
   * Path to locations xml
   *
   * @var string
   */
  protected $_locationFile;
  
  
  public function __construct()
  {
    parent::__construct();
    $this->_locationFile = Mage::getModuleDir('etc', 'Omnivalt_Shipping') . DS . 'locations.xml';
    
    //Mage::log($this->getConfigData('location_update'), null, 'omnivalt.log', true);
    if (!$this->getConfigData('location_update') || ($this->getConfigData('location_update') + 3600 * 24 ) < time()) {
      $url  = 'https://www.omniva.ee/locations.xml';
      $fp   = fopen($this->_locationFile, "w");
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_HEADER, false);
      curl_setopt($curl, CURLOPT_FILE, $fp);
      curl_setopt($curl, CURLOPT_TIMEOUT, 60);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      $data = curl_exec($curl);
      curl_close($curl);
      fclose($fp);
      if ($data !== false) {
        Mage::getModel('core/config')->saveConfig("carriers/omnivalt/location_update", time());
      }
    }
  }
  
  
  /**
   * Collect and get rates
   *
   * @param Mage_Shipping_Model_Rate_Request $request
   * @return Mage_Shipping_Model_Rate_Result|bool|null
   */
  public function collectRates(Mage_Shipping_Model_Rate_Request $request)
  {
    
    if (!$this->getConfigData('active')) {
      return false;
    }
    $result = Mage::getModel('shipping/rate_result');
    //allow only with omniva attribute
    /*
    $allow_omniva = true;
    foreach ($request->getAllItems() as $item){
      $_item = $item->getProduct()->getId();
      $_product = Mage::getModel('catalog/product')->load($_item);  
      $optionvalue = $_product->getOmniva();
      if (!$optionvalue){
        $allow_omniva = false;
        break;
      }
    }
    if (!$allow_omniva)
      return $result;
    */
    //end attribute check
    //Fetch the methods.
    $allowedMethods = $this->getAllowedMethods();
    $max_weight = $this->getConfigData('max_package_weight');
    foreach ($allowedMethods as $key => $title) {
      //Here check your method(carrier) if it is valid.
      //if is valid:
      if ($request->getPackageWeight() > $max_weight) continue;
      
      $method = Mage::getModel('shipping/rate_result_method');
      $method->setCarrier($this->_code);
      $method->setCarrierTitle($this->getConfigData('title'));
      $method->setMethod($key);
      $method->setMethodTitle($title);
      $method->setMethodDescription($title);
      //Calculate shipping price for rate:
      //$shippingPrice = $this->_calculateShippingPrice($key); //You need to implement this method.
      $country_id = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getData('country_id');

      if ($key == "COURIER") {
        switch($country_id) {
          case 'LV':
              $shippingPrice = $this->getConfigData('priceLV_C');
              break;
          case 'EE':
              $shippingPrice = $this->getConfigData('priceEE_C');
              break;
          default:
              $shippingPrice = $this->getConfigData('price');
        }
      }
      if ($key == "PARCEL_TERMINAL"){
        switch($country_id) {
          case 'LV':
              $shippingPrice = $this->getConfigData('priceLV_pt');
              break;
          case 'EE':
              $shippingPrice = $this->getConfigData('priceEE_pt');
              break;
          default:
              $shippingPrice = $this->getConfigData('price2');
        }
      }
      $method->setCost($shippingPrice);
      $method->setPrice($shippingPrice);
      //Finally add the method to the result.
      $result->append($method);
    }
    return $result;
  }
  
  /**
   * Get version of rates request
   *
   * @return array
   */
  public function getVersionInfo()
  {
    return array(
      'ServiceId' => 'crs',
      'Major' => '10',
      'Intermediate' => '0',
      'Minor' => '0'
    );
  }
  
  
  /**
   * Get configuration data of carrier
   *
   * @param string $type
   * @param string $code
   * @return array|bool
   */
  public function getCode($type, $code = '')
  {
    $codes = array(
      'method' => array(
        'COURIER' => __('Courier'),
        'PARCEL_TERMINAL' => __('Parcel terminal')
      ),
      'unit_of_measure' => array(
        'LB' => __('Pounds'),
        'KG' => __('Kilograms')
      ),
      'tracking' => array(
        'PACKET_EVENT_IPS_C' => __("Shipment from country of departure"),
        'PACKET_EVENT_FROM_CONTAINER' => __("Arrival to post office"),
        'PACKET_EVENT_IPS_D' => __("Arrival to destination country"),
        'PACKET_EVENT_SAVED' => __("Saving"),
        'PACKET_EVENT_DELIVERY_CANCELLED' => __("Cancelling of delivery"),
        'PACKET_EVENT_IN_POSTOFFICE' => __("Arrival to Omniva"),
        'PACKET_EVENT_IPS_E' => __("Customs clearance"),
        'PACKET_EVENT_DELIVERED' => __("Delivery"),
        'PACKET_EVENT_FROM_WAYBILL_LIST' => __("Arrival to post office"),
        'PACKET_EVENT_IPS_A' => __("Acceptance of packet from client"),
        'PACKET_EVENT_IPS_H' => __("Delivery attempt"),
        'PACKET_EVENT_DELIVERING_TRY' => __("Delivery attempt"),
        'PACKET_EVENT_DELIVERY_CALL' => __("Preliminary calling"),
        'PACKET_EVENT_IPS_G' => __("Arrival to destination post office"),
        'PACKET_EVENT_ON_ROUTE_LIST' => __("Dispatching"),
        'PACKET_EVENT_IN_CONTAINER' => __("Dispatching"),
        'PACKET_EVENT_PICKED_UP_WITH_SCAN' => __("Acceptance of packet from client"),
        'PACKET_EVENT_RETURN' => __("Returning"),
        'PACKET_EVENT_SEND_REC_SMS_NOTIF' => __("SMS to receiver"),
        'PACKET_EVENT_ARRIVED_EXCESS' => __("Arrival to post office"),
        'PACKET_EVENT_IPS_I' => __("Delivery"),
        'PACKET_EVENT_ON_DELIVERY_LIST' => __("Handover to courier"),
        'PACKET_EVENT_PICKED_UP_QUANTITATIVELY' => __("Acceptance of packet from client"),
        'PACKET_EVENT_SEND_REC_EMAIL_NOTIF' => __("E-MAIL to receiver"),
        'PACKET_EVENT_FROM_DELIVERY_LIST' => __("Arrival to post office"),
        'PACKET_EVENT_OPENING_CONTAINER' => __("Arrival to post office"),
        'PACKET_EVENT_REDIRECTION' => __("Redirection"),
        'PACKET_EVENT_IN_DEST_POSTOFFICE' => __("Arrival to receiver's post office"),
        'PACKET_EVENT_STORING' => __("Storing"),
        'PACKET_EVENT_IPS_EDD' => __("Item into sorting centre"),
        'PACKET_EVENT_IPS_EDC' => __("Item returned from customs"),
        'PACKET_EVENT_IPS_EDB' => __("Item presented to customs"),
        'PACKET_EVENT_IPS_EDA' => __("Held at inward OE"),
        'PACKET_STATE_BEING_TRANSPORTED' => __("Being transported"),
        'PACKET_STATE_CANCELLED' => __("Cancelled"),
        'PACKET_STATE_CONFIRMED' => __("Confirmed"),
        'PACKET_STATE_DELETED' => __("Deleted"),
        'PACKET_STATE_DELIVERED' => __("Delivered"),
        'PACKET_STATE_DELIVERED_POSTOFFICE' => __("Arrived at post office"),
        'PACKET_STATE_HANDED_OVER_TO_COURIER' => __("Transmitted to courier"),
        'PACKET_STATE_HANDED_OVER_TO_PO' => __("Re-addressed to post office"),
        'PACKET_STATE_IN_CONTAINER' => __("In container"),
        'PACKET_STATE_IN_WAREHOUSE' => __("At warehouse"),
        'PACKET_STATE_ON_COURIER' => __("At delivery"),
        'PACKET_STATE_ON_HANDOVER_LIST' => __("In transition sheet"),
        'PACKET_STATE_ON_HOLD' => __("Waiting"),
        'PACKET_STATE_REGISTERED' => __("Registered"),
        'PACKET_STATE_SAVED' => __("Saved"),
        'PACKET_STATE_SORTED' => __("Sorted"),
        'PACKET_STATE_UNCONFIRMED' => __("Unconfirmed"),
        'PACKET_STATE_UNCONFIRMED_NO_TARRIF' => __("Unconfirmed (No tariff)"),
        'PACKET_STATE_WAITING_COURIER' => __("Awaiting collection"),
        'PACKET_STATE_WAITING_TRANSPORT' => __("In delivery list"),
        'PACKET_STATE_WAITING_UNARRIVED' => __("Waiting, hasn't arrived"),
        'PACKET_STATE_WRITTEN_OFF' => __("Written off")
      ),
      'terminal' => array()
    );
    
    $locationsXMLArray = simplexml_load_file($this->_locationFile);
    $locations_lt         = array();
    $locations_lv         = array();
    $locations_ee         = array();
    foreach ($locationsXMLArray->LOCATION as $loc_data) {
      
      if ($loc_data->A0_NAME == 'LT'){
        $locations_lt[(string) $loc_data->ZIP] = array(
          'name' => (string)$loc_data->NAME,
          'country' => (string)$loc_data->A0_NAME,
          'city' => (string)$loc_data->A1_NAME
        );
      }
      if ($loc_data->A0_NAME == 'LV'){
        $locations_lv[(string) $loc_data->ZIP] = array(
          'name' => (string)$loc_data->NAME,
          'country' => (string)$loc_data->A0_NAME,
          'city' => (string)$loc_data->A1_NAME
        );
      }
      if ($loc_data->A0_NAME == 'EE'){
        $locations_ee[(string) $loc_data->ZIP] = array(
          'name' => (string)$loc_data->NAME,
          'country' => (string)$loc_data->A0_NAME,
          'city' => (string)$loc_data->A1_NAME
        );
      }
      
    }
    $codes['terminal']['LT'] = $locations_lt;
    $codes['terminal']['LV'] = $locations_lv;
    $codes['terminal']['EE'] = $locations_ee;
    if ($type == "terminal" && $code == '')
      $code = "LT";
    
    if (!isset($codes[$type])) {
      return false;
    } elseif ('' === $code) {
      return $codes[$type];
    }
    
    if (!isset($codes[$type][$code])) {
      return false;
    } else {
      return $codes[$type][$code];
    }
  }
  
  /**
   * Get allowed shipping methods
   *
   * @return array
   */
  public function getAllowedMethods()
  {
    $allowed = explode(',', $this->getConfigData('allowed_methods'));
    $arr     = array();
    foreach ($allowed as $k) {
      $arr[$k] = $this->getCode('method', $k);
    }
    return $arr;
  }
  
  /**
   * For multi package shipments. Delete requested shipments if the current shipment
   * request is failed
   *
   * @param array $data
   * @return bool
   */
  public function rollBack($data)
  {
    /*
    $requestData = $this->_getAuthDetails();
    $requestData['DeletionControl'] = 'DELETE_ONE_PACKAGE';
    foreach ($data as &$item) {
    $requestData['TrackingId'] = $item['tracking_number'];
    $client = $this->_createShipSoapClient();
    $client->deleteShipment($requestData);
    }
    */
    return true;
  }
  
  protected function _getShipmentLabels($barcodes)
  {
    $barcodeXML = '';
    foreach ($barcodes as $barcode) {
      $barcodeXML .= '<barcode>' . $barcode . '</barcode>';
    }
    $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:addrcardMsgRequest>
                 <partner>' . $this->getConfigData('user') . '</partner>
                 <sendAddressCardTo>response</sendAddressCardTo>
                 <barcodes>
                    ' . $barcodeXML . '
                 </barcodes>
              </xsd:addrcardMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';
    $debugData  = array(
      'request' => $xmlRequest
    );
    try {
      $url     = $this->getConfigData('gateway_url') . '/epmx/services/messagesService.wsdl';
      $headers = array(
        "Content-type: text/xml;charset=\"utf-8\"",
        "Accept: text/xml",
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        "Content-length: " . strlen($xmlRequest)
      );
      $ch      = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_USERPWD, $this->getConfigData('user') . ":" . $this->getConfigData('password'));
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      $xmlResponse         = curl_exec($ch);
      $debugData['result'] = $xmlResponse;
    }
    catch (\Exception $e) {
      $debugData['result'] = array(
        'error' => $e->getMessage(),
        'code' => $e->getCode()
      );
      $xmlResponse         = '';
    }
    $xml = $this->_parseXml(str_ireplace(array(
      'SOAP-ENV:',
      'SOAP:'
    ), '', $xmlResponse));
    $result = new Varien_Object();
    if (!is_object($xml)) {
      $errorTitle = __('Response is in the wrong format');
    } else {
      if (is_object($xml) && is_object($xml->Body->addrcardMsgResponse->successAddressCards->addressCardData->barcode)) {
        $shippingLabelContent = (string) $xml->Body->addrcardMsgResponse->successAddressCards->addressCardData->fileData;
        $trackingNumber       = (string) $xml->Body->addrcardMsgResponse->successAddressCards->addressCardData->barcode;
        $result->setShippingLabelContent(base64_decode($shippingLabelContent));
        $result->setTrackingNumber($trackingNumber);
      } else {
        $result->setErrors(__('No label received from webservice'));
      }
    }
    //$this->_debug($debugData);
    return $result;
  }
  
  protected function _formShipmentRequest(Varien_Object $request)
  {
    $itemsShipment = $request->getPackageItems();
    foreach ($itemsShipment as $itemShipment) {
      $item = new Varien_Object();
      $item->setData($itemShipment);
      //$this->_debug($item);
    }
    $send_method   = trim($request->getShippingMethod());
    $pickup_method = $this->getConfigData('pickup');
    $service       = "";
    switch ($pickup_method . ' ' . $send_method) {
      case 'COURIER PARCEL_TERMINAL':
        $service = "PU";
        break;
      case 'COURIER COURIER':
        $service = "QH";
        break;
      case 'PARCEL_TERMINAL COURIER':
        $service = "PK";
        break;
      case 'PARCEL_TERMINAL PARCEL_TERMINAL':
        $service = "PA";
        break;
      default:
        $service = "";
        break;
    }
    $parcel_terminal = "";
    if ($send_method == "PARCEL_TERMINAL")
      $parcel_terminal = 'offloadPostcode="' . $request->getOrderShipment()->getOrder()->getParcelTerminal() . '" ';
    $payment_method = $request->getOrderShipment()->getOrder()->getPayment()->getMethodInstance()->getCode();
    $cod            = "";
    if ($payment_method == 'cashondelivery') {
      $cod = '<monetary_values>
          <cod_receiver>' . $this->getConfigData('company') . '</cod_receiver>
          <values code="item_value" amount="' . round($request->getOrderShipment()->getOrder()->getGrandTotal(), 2) . '"/>
        </monetary_values>
        <account>' . $this->getConfigData('bank_account') . '</account>
        <reference_number>' . $this->getReferenceNumber($request->getOrderShipment()->getOrder()->getId()) . '</reference_number>';
    }
    $additionalService = '';
    if ($service == "PA" || $service == "PU" || $cod)
      $additionalService = '
                <add_service>
                     <option code="ST" />
                </add_service>';
    if (($service == "PA" || $service == "PU") && $cod)
      $additionalService = '
                <add_service>
                     <option code="ST" />
                     <option code="BP" />
                </add_service>';
    $pickStart  = $this->getConfigData('pick_up_time_start');
    $pickFinish = $this->getConfigData('pick_up_time_finish');
    $pickDay    = date('Y-m-d');
    if (time() > strtotime($pickDay . ' ' . $pickFinish))
      $pickDay = date('Y-m-d', strtotime($pickDay . "+1 days"));
    $name             = Mage::getStoreConfig('general/store_information/name');
    if ($parcel_terminal)
      $receiver_address = '<address ' . $parcel_terminal . ' />';                          
    else
      $receiver_address = '<address postcode="' . $request->getRecipientAddressPostalCode() . '" ' . $parcel_terminal . ' deliverypoint="' . $request->getRecipientAddressCity() . '" country="' . $request->getRecipientAddressCountryCode() . '" street="' . $request->getRecipientAddressStreet1() . '" />';                          
    $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:businessToClientMsgRequest>
                 <partner>' . $this->getConfigData('user') . '</partner>
                 <interchange msg_type="info11">
                    <header file_id="' . \Date('YmdHms') . '" sender_cd="' . $this->getConfigData('user') . '" >                
                    </header>
                    <item_list>
                       <!--1 or more repetitions:-->
                       <item service="' . $service . '" >
                          ' . $additionalService . '
                          <measures weight="' . $request->getPackageWeight() . '" />
                          ' . $cod . '
                          <receiverAddressee >
                             <person_name>' . $request->getRecipientContactPersonName() . '</person_name>
                             <mobile>' . $request->getRecipientContactPhoneNumber() . '</mobile>
                            ' . $receiver_address . '
                          </receiverAddressee>
                          <!--Optional:-->
                          <returnAddressee>
                             <person_name>' . $this->getConfigData('company') . '</person_name>
                             <!--Optional:-->
                             <phone>' . $this->getConfigData('company_phone') . '</phone>
                             <address postcode="' . $this->getConfigData('company_postcode') . '" deliverypoint="' . $this->getConfigData('company_city') . '" country="' . $this->getConfigData('company_countrycode') . '" street="' . $this->getConfigData('company_address') . '" />
                             
                          </returnAddressee>
                       </item>
                    </item_list>
                 </interchange>
              </xsd:businessToClientMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';
    //Mage::log($xmlRequest, null, 'omnivalt.log', true);
    return $xmlRequest;
  }
  
  
  public function call_omniva(){
        $service = "QH";  
        $pickStart = $this->getConfigData('pick_up_time_start')?$this->getConfigData('pick_up_time_start'):'8:00';
        $pickFinish = $this->getConfigData('pick_up_time_finish')?$this->getConfigData('pick_up_time_finish'):'17:00';
        $pickDay = date('Y-m-d');
        if (time() > strtotime($pickDay.' '.$pickFinish))
          $pickDay = date('Y-m-d',strtotime($pickDay . "+1 days"));
        $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:businessToClientMsgRequest>
                 <partner>'.$this->getConfigData('user').'</partner>
                 <interchange msg_type="info11">
                    <header file_id="'.\Date('YmdHms').'" sender_cd="'.$this->getConfigData('user').'" >                
                    </header>
                    <item_list>
                      ';
                      for ($i = 0; $i <1; $i++):
                      $xmlRequest .= '
                       <item service="'.$service.'" >
                          <measures weight="1" />
                          <receiverAddressee >
                             <person_name>'.$this->getConfigData('company').'</person_name>
                             <phone>' . $this->getConfigData('company_phone') . '</phone>
                             <address postcode="' . $this->getConfigData('company_postcode') . '" deliverypoint="' . $this->getConfigData('company_city') . '" country="' . $this->getConfigData('company_countrycode') . '" street="' . $this->getConfigData('company_address') . '" />
                          </receiverAddressee>
                          <!--Optional:-->
                          <returnAddressee>
                             <person_name>' . $this->getConfigData('company') . '</person_name>
                             <!--Optional:-->
                             <phone>' . $this->getConfigData('company_phone') . '</phone>
                             <address postcode="' . $this->getConfigData('company_postcode') . '" deliverypoint="' . $this->getConfigData('company_city') . '" country="' . $this->getConfigData('company_countrycode') . '" street="' . $this->getConfigData('company_address') . '" />
                          </returnAddressee>';
                          $xmlRequest .= '
                          <onloadAddressee>
                             <person_name>' . $this->getConfigData('company') . '</person_name>
                             <!--Optional:-->
                             <phone>' . $this->getConfigData('company_phone') . '</phone>
                             <address postcode="' . $this->getConfigData('company_postcode') . '" deliverypoint="' . $this->getConfigData('company_city') . '" country="' . $this->getConfigData('company_countrycode') . '" street="' . $this->getConfigData('company_address') . '" />
                             <pick_up_time start="' . date("c", strtotime($pickDay . ' ' . $pickStart)) . '" finish="' . date("c", strtotime($pickDay . ' ' . $pickFinish)) . '"/>
                          </onloadAddressee>';
                       $xmlRequest .= '</item>';
                      endfor; 
                       $xmlRequest .= '
                    </item_list>
                 </interchange>
              </xsd:businessToClientMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';
        
      $url = $this->getConfigData('gateway_url') . '/epmx/services/messagesService.wsdl';
    $debugData = array(
      'request' => $xmlRequest
    );
    $result     = new Varien_Object();
    $headers   = array(
      "Content-type: text/xml;charset=\"utf-8\"",
      "Accept: text/xml",
      "Cache-Control: no-cache",
      "Pragma: no-cache",
      "Content-length: " . strlen($xmlRequest)
    );
    $ch        = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERPWD, $this->getConfigData('user') . ":" . $this->getConfigData('password'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $xmlResponse = curl_exec($ch);
    if ($xmlResponse === false) {
      throw new \Exception(curl_error($ch));
    } else {
      $debugData['result'] = $xmlResponse;
      $errorTitle          = '';
      if (strlen(trim($xmlResponse)) > 0) {
        $xml = $this->_parseXml(str_ireplace(array(
          'SOAP-ENV:',
          'SOAP:'
        ), '', $xmlResponse));
        if (!is_object($xml)) {
          $errorTitle = __('Response is in the wrong format');
        }
        if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo)) {
          foreach ($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo as $data) {
            $errorTitle .= $data->clientItemId . ' - ' . $data->barcode . ' - ' . $data->message;
          }
        }
        if ($errorTitle != '')
          $result->setErrors($errorTitle);
        if (!$result->hasErrors()) {
          if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo)) {
            foreach ($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo as $data) {
              $barcodes[] = (string) $data->barcode;
              $new_barcode[] = (string) $data->barcode;
            }
          }
        }
      }
    }   
    $debugData['barcodes'] = $barcodes;
    if ($result->hasErrors() || empty($xmlResponse)) {
      return false;
    } else {
      if (!empty($barcodes)){
        return $barcodes;
      }
      $result->setErrors(__('No saved barcodes received'));
      return false;
    }
  }
  
  
  protected function getReferenceNumber($order_number)
  {
    $order_number = (string) $order_number;
    $kaal         = array(
      7,
      3,
      1
    );
    $sl           = $st = strlen($order_number);
    $total        = 0;
    while ($sl > 0 and substr($order_number, --$sl, 1) >= '0') {
      $total += substr($order_number, ($st - 1) - $sl, 1) * $kaal[($sl % 3)];
    }
    $kontrollnr = ((ceil(($total / 10)) * 10) - $total);
    return $order_number . $kontrollnr;
  }
  
  protected function _doShipmentRequest(Varien_Object $request)
  {
    $barcodes = array();
    $new_barcode = array();
    $shipment = $request->getOrderShipment();
    if ($shipment) {
      foreach ($shipment->getAllTracks() as $track) {   
        $barcodes[] = $track->getNumber();
      }
    }
    $this->_prepareShipmentRequest($request);
    $result     = new Varien_Object();
    $xmlRequest = $this->_formShipmentRequest($request);
    $url = $this->getConfigData('gateway_url') . '/epmx/services/messagesService.wsdl';
    $debugData = array(
      'request' => $xmlRequest
    );
    $headers   = array(
      "Content-type: text/xml;charset=\"utf-8\"",
      "Accept: text/xml",
      "Cache-Control: no-cache",
      "Pragma: no-cache",
      "Content-length: " . strlen($xmlRequest)
    );
    $ch        = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERPWD, $this->getConfigData('user') . ":" . $this->getConfigData('password'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $xmlResponse = curl_exec($ch);
    if ($xmlResponse === false) {
      throw new \Exception(curl_error($ch));
    } else {
      $debugData['result'] = $xmlResponse;
      $errorTitle          = '';
      if (strlen(trim($xmlResponse)) > 0) {
        $xml = $this->_parseXml(str_ireplace(array(
          'SOAP-ENV:',
          'SOAP:'
        ), '', $xmlResponse));
        if (!is_object($xml)) {
          $errorTitle = __('Response is in the wrong format');
        }
        if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo)) {
          foreach ($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo as $data) {
            $errorTitle .= $data->clientItemId . ' - ' . $data->barcode . ' - ' . $data->message;
          }
        }
        if ($errorTitle != '')
          $result->setErrors($errorTitle);
        if (!$result->hasErrors()) {
          if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo)) {
            foreach ($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo as $data) {
              $barcodes[] = (string) $data->barcode;
              $new_barcode[] = (string) $data->barcode;
            }
          }
        }
      }
    }   
    $debugData['barcodes'] = $barcodes;
    //$this->_debug($debugData);
    if ($result->hasErrors() || empty($xmlResponse)) {
      return $result;
    } else {
      if (!empty($barcodes)){
        $outputPdf = new Zend_Pdf();
        $new_data = false;
        foreach ($barcodes as $barcode) {
          $return_data = $this->_getShipmentLabels(array($barcode));
          $content = $return_data->getShippingLabelContent();
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
          if (in_array($barcode,$new_barcode)){
            $new_data = $return_data;
          }
        }
        $new_data->setShippingLabelContent($outputPdf->render());
        $order = $request->getOrderShipment()->getOrder();
        $history = Mage::getModel('sales/order_status_history')
            ->setOrder($order)
            ->setStatus('call_omniva')
            ->setComment(implode(', ',$barcodes))
            ->setIsCustomerNotified(false)
            ->setData('entity_name', Mage_Sales_Model_Order::HISTORY_ENTITY_NAME);
        $history->save();
        //$order->setStatus("call_omniva");  
        //$order->save();
        return $new_data;
      }
      $result->setErrors(__('No saved barcodes received'));
      return $result;
    }
  }
  
  protected function _parseXml($xmlContent)
  {
    try {
      try {
        return simplexml_load_string($xmlContent);
      }
      catch (Exception $e) {
        throw new Exception(Mage::helper('usa')->__('Failed to parse xml document: %s', $xmlContent));
      }
    }
    catch (Exception $e) {
      Mage::logException($e);
      return false;
    }
  }
  
  public function getTracking($trackings)
  {
    if (!is_array($trackings)) {
      $trackings = array(
        $trackings
      );
    }
    foreach ($trackings as $tracking) {
      $this->_getXMLTracking($tracking);
    }
    return $this->_result;
  }
  
  protected function _getXMLTracking($tracking)
  {
    $url               = $this->getConfigData('gateway_url') . '/epteavitus/events/from/' . date("c", strtotime("-1 week +1 day")) . '/for-client-code/' . $this->getConfigData('user');
    $process           = curl_init();
    $additionalHeaders = '';
    curl_setopt($process, CURLOPT_URL, $url);
    curl_setopt($process, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/xml',
      $additionalHeaders
    ));
    curl_setopt($process, CURLOPT_HEADER, FALSE);
    curl_setopt($process, CURLOPT_USERPWD, $this->getConfigData('user') . ":" . $this->getConfigData('password'));
    curl_setopt($process, CURLOPT_TIMEOUT, 30);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    $return = curl_exec($process);
    curl_close($process);
    $this->_parseXmlTrackingResponse($tracking, $return);
  }
  
  protected function _parseXmlTrackingResponse($trackings, $response)
  {
    $errorTitle = __('Unable to retrieve tracking');
    $resultArr  = array();
    
    if (strlen(trim($response)) > 0) {
      $xml = $this->_parseXml($response);
      if (!is_object($xml)) {
        $errorTitle = __('Response is in the wrong format');
      }
      
      if (is_object($xml) && is_object($xml->event)) {
        foreach ($xml->event as $awbinfo) {
          
          $trackNum = isset($awbinfo->packetCode) ? (string) $awbinfo->packetCode : '';
          if ($trackNum != $trackings)
            continue;
          $packageProgress = array();
          
          $shipmentEventArray             = array();
          $shipmentEventArray['activity'] = $this->getCode('tracking', (string) $awbinfo->eventCode);
          $datetime                       = \DateTime::createFromFormat('U', strtotime($awbinfo->eventDate));
          //$this->_debug(\DateTime::ISO8601);
          $shipmentEventArray['deliverydate']     = date("Y-m-d", strtotime((string) $awbinfo->eventDate));
          $shipmentEventArray['deliverytime']     = date("H:i:s", strtotime((string) $awbinfo->eventDate));
          $shipmentEventArray['deliverylocation'] = $awbinfo->eventSource;
          $packageProgress[]                      = $shipmentEventArray;
          
        }
      }
      $resultArr['progressdetail'] = $packageProgress;
    }
    
    $result = Mage::getModel('shipping/tracking_result');
    
    if (!empty($resultArr)) {
      $tracking = Mage::getModel('shipping/tracking_result_status');
      $tracking->setCarrier($this->_code);
      $tracking->setCarrierTitle($this->getConfigData('title'));
      $tracking->setTracking($trackings);
      $tracking->addData($resultArr);
      $result->append($tracking);
    }
    if (!empty($this->_errors) || empty($resultArr)) {
      $resultArr = !empty($this->_errors) ? $this->_errors : $trackings;
      $error     = Mage::getModel('shipping/tracking_result_error');
      $error->setCarrier($this->_code);
      $error->setCarrierTitle($this->getConfigData('title'));
      $error->setTracking(!empty($this->_errors) ? $trackings : $resultArr);
      $error->setErrorMessage(!empty($this->_errors) ? $resultArr : $errorTitle);
      $result->append($error);
    }
    $this->_result = $result;
  }
}
