<?php
namespace TcsCourier\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use TcsCourier\Shipping\Helper\Data;

class SalesOrderAfterSave implements ObserverInterface
{
	protected $_productloader; 
	protected $scopeConfig;
	  
	const XML_PATH_EMAIL_RECIPIENT = 'TcsCourier_shipping/general/enable_dtb';

	public function __construct(Data $helperData,
		\Magento\Customer\Model\Session $customerSession, 
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
		\Magento\Catalog\Model\ProductFactory $_productloader,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
		)
	{
		$this->helperData = $helperData;
        $this->customerSession = $customerSession;
		$this->_productloader = $_productloader;
		$this->_customerRepositoryInterface = $customerRepositoryInterface;
		$this->scopeConfig = $scopeConfig;
		$this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$this->urlBuilder = $this->objectManager->create('Magento\Framework\UrlInterface');

	}	
	
    public function execute(\Magento\Framework\Event\Observer $observer){
    	$specialcharacters = array(",", ";", "'", ":", "/", "#", "^", "&", "*", "(", ")");
        $order = $observer->getEvent()->getOrder();
        if ($order instanceof \Magento\Framework\Model\AbstractModel) {
            $state = $order->getState();
            $status = $order->getStatus();
            $storeId = $order->getStoreId();
            $orderId = $order->getId();
			$paymentType = $order->getPayment()->getMethodInstance()->getCode();

			$billingAddress = $order->getBillingAddress();
			$shippingAddress = $order->getShippingAddress();
			$orderIncrementId = $order->getIncrementId();
			$giftCharges = 0;
			if($order->getData('base_osc_gift_wrap_amount')){
				$giftCharges = $order->getData('base_osc_gift_wrap_amount');
			}
	
			$billingCity = $billingAddress->getCity();
			$shippingCity = $shippingAddress->getCity();
			
			
			$addressInfo = '<shipper_name>'.str_replace($specialcharacters," ",$shippingAddress->getName()).'</shipper_name>
				<shipper_email>'.$shippingAddress->getEmail().'</shipper_email>
				<shipper_contact>'.$shippingAddress->getTelephone().'</shipper_contact>
				<shipper_address>'.str_replace($specialcharacters," ",$shippingAddress['street']).'</shipper_address>
				<shipper_city>'.$billingCity.'</shipper_city>
				<shipper_country>'.$billingAddress->getCountryId().'</shipper_country>
				<billing_name>'.str_replace($specialcharacters," ",$billingAddress->getName()).'</billing_name>
				<bill_email>'.$billingAddress->getEmail().'</bill_email>
				<billing_contact>'.$billingAddress->getTelephone().'</billing_contact>
				<billing_address>'.str_replace($specialcharacters," ",$billingAddress['street']).'</billing_address>
				<billing_city>'.$shippingCity.'</billing_city>
				<billing_country>'.$billingAddress->getCountryId().'</billing_country>';
			
			$products = [];
			$items = $order->getAllItems();

			foreach($items as $item):
				
				if($item->getProductType() == 'configurable'){ 
				
					$productObject = $this->objectManager->get('Magento\Catalog\Model\Product');
                    $productConf = $productObject->load($item->getProductId());
                   
                    $parentSku = $productConf->getSku();
                }else{
                    $parentSku = 'None';
                }

                	$products[] = '<products_detail>
					  <product_type>'.$item->getProductType().'</product_type>
					  <parent_sku>'.$parentSku.'</parent_sku>
					  <product_code>'.$item->getSku().'</product_code>
					  <product_id>'.$item->getProductId().'</product_id>
					  <product_name>'.str_replace($specialcharacters," ",$item->getName()).'</product_name>
					  <product_price>'. number_format($item->getPrice(),2,'.','').'</product_price>
					  <product_quantity>'.$item->getQtyOrdered().'</product_quantity>
					  <product_store_id>'.$item->getStoreId().'</product_store_id>
					</products_detail>';
					
			endforeach;
           if($status == 'pending' && $storeId == 40) {
                $success = false; 
                $error = false;
                $response = false;
        		$cn_id = '';
        		$cn_text = '';
        		$oms = '';
        		$order_id = $order->getId();
        		$resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        		$connection = $resource->getConnection();
        		$tableName = $resource->getTableName('TcsCourier_shipping_data');
        		$check = $connection->fetchAll("SELECT id FROM $tableName WHERE order_id = $order_id");
        		if(empty($check)){
            		$service_type = 'BE';
                    $charges = round($order->getGrandTotal());
                    $insurance = 'N';
                    $city = $order->getShippingAddress()->getData("city");
                    $cod_amount = 0;
                    $collect_cash = 'n';
                    list($cn_id,$error,$response,$enable_oms,$response) = $this->getApiCN($service_type,$charges,$insurance,$city,$cod_amount,$collect_cash,$paymentType,$billingAddress,$shippingAddress,$orderIncrementId,$giftCharges,$shippingCity,$billingCity,$addressInfo,$items,$parentSku,$orderId,$products);

                    if($error == ""){
                        if($response['result']['status'] == "1"){
                                $cn_id = 0;
                                $order_code = $response['result']['order_code'];
                                $logistic_type = 'blueex';
                                $data =  [
                					//'service_type'=>$service_type,
                					'total_amount'=>$charges,
                					'charges'=> 0,
                					'city'=>$city,
                					'cod_amount'=>0,
                					'collect_cash'=>'n',
                					'insurance'=>'N',
                					'order_code'=> (int)@$response['result']['order_code'],
                					'cn_number'=> 0,
                					'oms' => $enable_oms ? 'Y' : 'N',
                				];
                                $order->save();
                        }
                    }else{
                       
                    }
                    // $order->save();
                    // exit;
        		}
           }
        }
	}

	public function checkMethod(){
		return 'yes';
	}
	
	private function getApiCN($service_type,$charges,$insurance,$city,$cod_amount,$collect_cash,$paymentType,$billingAddress,$shippingAddress,$orderIncrementId,$giftCharges,$shippingCity,$billingCity,$addressInfo,$items,$parentSku,$orderId,$products){
		$billingCity = $shippingCity = trim($city);
		$error= $response = false;
		$cn_id = false;
		try{ 
			
			$auth = '<api_code>'. $this->helperData->getGeneralConfig('api_key').'</api_code>
				<acno>'. $this->helperData->getGeneralConfig('accout_number').'</acno>
				<testbit>'. ( (int)$this->helperData->getGeneralConfig('live_mode') ? 'n' : 'y').'</testbit>
				<userid>'. $this->helperData->getGeneralConfig('customer_name').'</userid>
				<password>'.$this->helperData->getGeneralConfig('customer_password').'</password>';
				
			$OriginCity = $this->helperData->getGeneralConfig('origin_citycode');
			$insurancePrice = 0;
			$orderStatus = "B";
		
			$cn_generate = 'y';
			$oms = '';
			$enable_oms = (int)@$this->helperData->getGeneralConfig('enable_oms');
			
			if( $enable_oms ){		
				$cn_generate = 'n';
				$oms = '<oms>1</oms>';	
			}
			$xml = '<?xml version="1.0" encoding="utf-8"?>
			<BenefitDocument>
			  <AccessRequest>
				<DocumentType>1</DocumentType>
				<Orderdetail>
				  '.$auth.'
				  <cn_generate>'.$cn_generate.'</cn_generate>
				  '.$oms .$addressInfo.'
				  <order_status>'.$orderStatus.'</order_status>
				  <order_id>'.$orderId.'</order_id>
				  <order_increment_id>'.$orderIncrementId.'</order_increment_id>
				  <gift_charges>'.$giftCharges.'</gift_charges>
				  <credit_card>NC</credit_card>
				  <customer_comment></customer_comment>
				  <staff_comment></staff_comment>
				  <shipping_charges>'.$charges.'</shipping_charges>
				  <payment_type>'.$paymentType.'</payment_type>
				  <current_currency>PKR</current_currency>
				  <currency_code>3</currency_code>
				  <all_products>
				  '.implode("",$products).'
				  </all_products>
				  <OriginCity>'.$OriginCity.'</OriginCity>
				  <ServiceCode>'.$service_type.'</ServiceCode>
				  <ParcelType>P</ParcelType>
				  <Fragile>1</Fragile>
				  <InsuranceRequire>'.($insurance ? 'Y' : 'N').'</InsuranceRequire>
				  <InsuranceValue>'.$insurancePrice.'</InsuranceValue>
				  <ShipperComment></ShipperComment>
				  <codamount>'.$cod_amount.'</codamount>
				  <cashcollect>'.($collect_cash ? 'y' : 'n').'</cashcollect>
				  <magento_order_code>'.$orderIncrementId.'</magento_order_code>
				</Orderdetail>
			  </AccessRequest>
			</BenefitDocument>';
			  // print_r($xml);
			  // exit();
			$response = $this->helperData->callHttp('order/order_api',$xml);
			if( @$response['error']	)
				$error = $response['error'];
			else {
				if( $enable_oms ){
					if( !empty($response['result']['oms']) )
						$cn_id = 'Order Sent To TcsCourier';
					else
						$error = 'Unabel to send Order to TcsCourier, try again';					
				}
				else {
					if( empty($response['result']['order_code']) )
						$error = 'Unabel to generate CN number, try again';
				}
			}

			$cn_id = !empty($response['result']['cn']) ? trim($response['result']['cn']) : '';	
			
		} catch(Exception $e){
			$error= $this->getMessage();	
		}

		return [$cn_id,$error,@$response['result'],$enable_oms,$response];
	}
	
	public function getCitycode($city){
		$city_code = false;
		$cities = $this->helperData->getCityList();
		foreach($cities as $key => $val){
			if( strtolower($val['city_name']) == strtolower(trim($city)))	{
				$city_code = strtoupper($val['city_code']);
			}
		}
		return $city_code;
	}
    private function updateCNNumber($order_id,$cn_id,$logistic_type,$data){

		$resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
		$connection = $resource->getConnection();
		$tableName = $resource->getTableName('TcsCourier_shipping_data');

		$data = json_encode($data);

		$check = $connection->fetchAll("SELECT id FROM $tableName WHERE order_id = $order_id");
		if( empty($check) )
			$sql = "Insert Into " . $tableName . " (order_id, cn_id,logistic_type, `data`, `datetime`) Values ($order_id,'$cn_id','$logistic_type','$data','".date("Y-m-d H:i:s")."')";
		else
			$sql = "UPDATE $tableName SET cn_id = '$cn_id',logistic_type = '$logistic_type',`data` = '$data', `datetime` = `datetime` WHERE order_id = $order_id";

		$connection->query($sql);
		$check2 = $connection->fetchAll("SELECT id FROM $tableName WHERE order_id = $order_id");

	}
}