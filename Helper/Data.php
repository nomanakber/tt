<?php
namespace TcsCourier\Shipping\Helper;
use Magento\Store\Model\ScopeInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	const XML_PATH = 'TcsCourier_shipping/';
	public $apiUrl = 'http://bigazure.com/api/extensions';
	
	public function isEnabled(){
        return (bool)$this->getGeneralConfig('enable');
    }
	
	public function getConfigValue($field, $storeId = null){
		return $this->scopeConfig->getValue($field, ScopeInterface::SCOPE_STORE, $storeId);
	}
	
	public function getGeneralConfig($code, $storeId = null){
		return trim($this->getConfigValue(self::XML_PATH .'general/'. $code, $storeId));
	}	

	public function getCityList($cache = true){
		
		$xml = '<?xml version="1.0" encoding="utf-8"?> 
         <BenefitDocument>
         	<AccessRequest> 
         		<DocumentType>1</DocumentType> 
            	<Customerinfo> 
            		<account_number>'.trim($this->getGeneralConfig('accout_number')).'</account_number>
            	</Customerinfo> 
         	</AccessRequest> 
         </BenefitDocument>'; 
        
		if( empty($_SESSION['bluexshipping_citylist']) ){
		
			$r = $this->callHttp('city/get_cities',$xml,false);
			$list = (array)@$r['result']['Cities']['citiesinfo'];
			if( !empty($list) ){
				usort($list,function($l1,$l2){
					return trim(strtolower(@$l1['city_name'])) > trim(strtolower(@$l2['city_name']));
				});
			}
			$_SESSION['bluexshipping_citylist'] = $list;
		} else 
			$list = $_SESSION['bluexshipping_citylist'];
			
		return $list;
	}
	
	public function getCNStatus($order_codes){

		$list  = [];
		
		if( !empty($order_codes) ){ 
		
			$user_id =  $this->getGeneralConfig('customer_name');
			$user_pass = $this->getGeneralConfig('customer_password');
			$acno = $this->getGeneralConfig('accout_number');
			
			$xml = '<?xml version="1.0" encoding="utf-8"?>
			<BenefitDocument>
			<AccessRequest>
			<DocumentType>1</DocumentType>
			<Orderdetail>
			  <acno>'.$acno.'</acno>
			  <userid>'.$user_id.'</userid>
			  <password>'.$user_pass.'</password>
			  <Orders>
				<codes>			
				  '.implode('',$order_codes).'
				</codes>
			  </Orders>
			</Orderdetail>
			</AccessRequest>
			</BenefitDocument>';
	
			$response = $this->callHttp('omsStatus/oms_status_api',$xml);
			$orderStatus = @$response['result']['Orders']['orderStatus'];
			if( !empty($orderStatus) ){
				foreach($orderStatus as $orderStatuses){
					if(isset($orderStatuses['order_code'])){
					 $orderCode = $orderStatuses['order_code'];
					 $r_orderStatus = $orderStatuses['omsStatusMessage'];
					 $list[$orderCode] =$r_orderStatus;	
					 }		 
				}
			}
		}
		return $list;	
	}
	
	public function callHttp($endpoint,$xml,$auth = true){
		
		$error = $response = false;
		$apiUrl = rtrim($this->apiUrl.'/')."/".trim($endpoint).".php";
				
		$c = curl_init(); 
		curl_setopt($c, CURLOPT_URL, $apiUrl ); 
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1 ); 
		if( $auth )
			curl_setopt($c, CURLOPT_USERPWD, trim($this->getGeneralConfig('customer_name')).":".trim($this->getGeneralConfig('customer_password')));	
		
		curl_setopt($c, CURLOPT_POST, 1 ); 
		curl_setopt($c, CURLOPT_POSTFIELDS, array('xml'=>trim($xml)) ); 
		curl_setopt($c, CURLOPT_HTTPHEADER, array('Content-Type=text/xml','charset=utf-8'));
		
		$result = @curl_exec($c); 
		
		if( @$_REQUEST['debug'] == 'y' ){
			echo '<pre>';
			echo "URL: ".$apiUrl;
			echo "\nXML: ".htmlentities($xml);
			echo "\nResult: ".htmlentities($result); die;
		}

		if( curl_errno($c) )
			$error = 'Curl: '.curl_error($c);
		else { 
			$urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
           $getUrl = $urlInterface->getCurrentUrl();

           if (strpos($getUrl, 'onepage/success') !== false || strpos($getUrl, 'order_create') !== false) {
                return ['result'=>$response,'error'=>''];
            }
           
			if( strpos(strtolower($result),"<?xml") !== false ){
				 
				$_response = new \SimpleXMLElement($result);
				$_response = @json_decode(json_encode($_response),true);
				
				if( isset($_response['result']) ||  isset($_response['response']) ){ 
				
					if( isset($_response['response']) )
						$response = $_response['response'];
						
					if(! isset($_response['response']) && isset($_response['result']) ){
						$_response = @$_response['result'];
						
						if( (int)@$_response['status'] )						
							$response = $_response;			
										
						if( !empty($_response['message']) && @$_response['status'] == '0' )	
							$error = trim($_response['message']); 
					} 
					
				} else if( isset($_response['status']) ) {
					if( (int)$_response['status'] )
				 		$response = $_response;
				 		try {
        					if( $_response['status'] == '0' )
        						$error = $_response['message'];
				 		} catch (\Exception $e) {
				 		    //$error = "incorrect information";
				 		}
				} else
					$error = 'API: Invalid response';	
			} else
				$error = 'XML: Invalid response';
		}
		
     	return ['result'=>$response,'error'=>$error];
	}
}