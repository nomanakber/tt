<?php
namespace TcsCourier\Shipping\Controller\Api;
use Magento\Framework\App\Action\Context;
 
class BlueVoid extends \Magento\Framework\App\Action\Action{
	
	protected $helperData;
	protected $objectManager;
	protected $connection;
	
	public function __construct(
		\Magento\Backend\App\Action\Context $context
	){
	//	$this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
	    $this->objectManager = $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$helperData = $this->objectManager->create('TcsCourier\Shipping\Helper\Data');
		$this->helperData = $helperData;
		$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
		$this->connection = $resource->getConnection();
		$this->dbTable = $resource->getTableName('TcsCourier_shipping_data');
		parent::__construct($context);
	}
	
	public function execute()
	{	    
			$enable             = $this->helperData->getGeneralConfig('enable');
            $accout_number      = $this->helperData->getGeneralConfig('accout_number');
            $api_key            = $this->helperData->getGeneralConfig('api_key');
            $customer_name      = $this->helperData->getGeneralConfig('customer_name');
            $customer_password  = $this->helperData->getGeneralConfig('customer_password');
            $cc_code            = $this->helperData->getGeneralConfig('cc_code');
            $origin_citycode    = $this->helperData->getGeneralConfig('origin_citycode');
            $enable_oms         = $this->helperData->getGeneralConfig('enable_oms');
            $fragile            = $this->helperData->getGeneralConfig('fragile');
            $insurance          = $this->helperData->getGeneralConfig('insurance');
            $service            = $this->helperData->getGeneralConfig('service');
            $live_mode          = $this->helperData->getGeneralConfig('live_mode');

			$cn_number = trim(@$_REQUEST['cn_number']);
			$order_id = intval(@$_REQUEST['order_id']);
		

            $url = "";
            if($live_mode == 1){
                $url = "https://apis.tcscourier.com/production/v1/cod/cancel-order";
            }else{
                $url = "https://apis.tcscourier.com/uat/v1/cod/cancel-order";
            }

            $consignment = array(
                "userName"              => $customer_name,
                "password"              => $customer_password,
                "consignmentNumber"        =>  $cn_number
            );

            $curl = curl_init();

			curl_setopt_array($curl, array(
			  CURLOPT_URL => "https://apis.tcscourier.com/uat/v1/cod/cancel-order",
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => "",
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 30,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => "PUT",
			  CURLOPT_POSTFIELDS => json_encode($consignment),
			  CURLOPT_HTTPHEADER => array(
			    "accept: application/json",
			    "content-type: application/json",
			    "x-ibm-client-id: $api_key"
			  ),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
			  //echo "cURL Error #:" . $err;
			} else {
			   $result   = json_decode($response);
			   if($result->returnStatus->status == "SUCCESS"){

					$resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
					$connection = $resource->getConnection();
					$tableName = $resource->getTableName('TcsCourier_shipping_data'); 
					$connection->query("DELETE FROM $tableName WHERE order_id = $order_id");

					$order = $this->objectManager->create('Magento\Sales\Model\Order')->load($order_id);
		            $error = 'Invalid Request';
		            $state = "canceled";
		            $status = 'canceled';
		            $comment = '';
		            $isNotified = false;
		            $order->setState($state);
		            $order->setStatus($status);
		            $order->addStatusToHistory($order->getStatus(), $comment);
		            $order->save();

				    if ($order->canCancel()) {
		                try {
		                    $order->cancel();
		                    // remove status history set in _setState
		                    $order->getStatusHistoryCollection(true);
		                    $order->save();
		                    // do some more stuff here
		                    // ...
		                } catch (Exception $e) {
		                 //   Mage::logException($e);
		                }
		            }

			   		header("Content-type: application/json");
					echo json_encode(array("status" => "1","message"=>json_encode($result)));
			   }else{
			   		header("Content-type: application/json");
					echo json_encode(array("status" => "0","message"=>json_encode($result)));
			   }

			}

		exit;
	}	
	
}