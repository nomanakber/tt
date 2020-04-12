<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_DeleteOrders
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace TcsCourier\Shipping\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use TcsCourier\Shipping\Helper\Data as DataHelper;

/**
 * Class MassDelete
 * @package Mageplaza\DeleteOrders\Controller\Adminhtml\Order
 */
class MassDelete extends AbstractMassAction
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::delete';

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var DataHelper
     */
    protected $helper;

    /**
     * MassDelete constructor.
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param OrderRepository $orderRepository
     * @param DataHelper $dataHelper
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        OrderRepository $orderRepository,
        DataHelper $dataHelper
    )
    {
        parent::__construct($context, $filter);
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helperData = $this->objectManager->create('TcsCourier\Shipping\Helper\Data');
        $this->urlBuilder = $this->objectManager->create('Magento\Framework\UrlInterface');
      	$this->helperData = $helperData;
        $this->collectionFactory = $collectionFactory;
        $this->orderRepository   = $orderRepository;
        $this->helper            = $dataHelper;
    }

    /**
     * @param AbstractCollection $collection
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    protected function massAction(AbstractCollection $collection)
    {
        ini_set('memory_limit','-1');
        if ($this->helper->isEnabled()) {
            $deleted = 0;

            /** @var \Magento\Sales\Api\Data\OrderInterface $order */
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

            foreach ($collection->getItems() as $order) {
                $success = $error = $response = false;
        		$cn_id = '';
        		$cn_text = '';
        		$oms = '';

        		
                $order_id = $order->getId();

                // check already cn exist
        		$resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');


        		$connection = $resource->getConnection();
        		$tableName = $resource->getTableName('TcsCourier_shipping_data');
                $check = $connection->fetchAll("SELECT id FROM $tableName WHERE order_id = $order_id");
        		if(empty($check)){
                    
                    /////////////////////////
                        $firstname = $order->getCustomerFirstname();
                        $middlename = $order->getCustomerMiddlename();
                        $lastname = $order->getCustomerLastname();
                        $billingaddress = $order->getBillingAddress();
                        $shippingaddress = $order->getShippingAddress()->getData();
                        $billAddress = $order->getBillingAddress();
                        

                        $ship_address   =   "";
                        $ship_city      =   "";
                        $ship_email     =   "";
                        $ship_telephone =   "";
                        $streets = [];
                        if(is_array($shippingaddress) && !empty($shippingaddress)){
                            if(isset($shippingaddress['street']) && isset($shippingaddress['city']) && !empty($shippingaddress['street']) && !empty($shippingaddress['city'])){
                                $ship_address     = $shippingaddress['street'];
                                $ship_city        = $shippingaddress['city'];
                                $ship_email       = $shippingaddress['email'];
                                $ship_telephone   = $shippingaddress['telephone'];
                                $streets = $order->getShippingAddress()->getStreet();
                            }else{
                                $ship_address     = $billingaddress['street'];
                                $ship_city        = $billingaddress['city'];
                                $ship_email       = $billingaddress['email'];
                                $ship_telephone   = $billingaddress['telephone'];
                                $streets = $order->getBillingAddress()->getStreet();
                            }
                        }else{
                                $ship_address     = $billingaddress['street'];
                                $ship_city        = $billingaddress['city'];
                                $ship_email       = $billingaddress['email'];
                                $ship_telephone   = $billingaddress['telephone'];
                                $streets = $order->getBillingAddress()->getStreet();

                        }


                            $items = $order->getAllItems();
                            $ship_qty = 0;
                            $weight = 0;
                            $order_details = " ";
                            foreach($items as $item) {
                                $ship_qty = $ship_qty+$item->getQtyOrdered();
                                $weight    = $weight+($item->getWeight() * $item->getQtyOrdered());
                                $product = $item->getProduct();
                                $order_details .= $product->getName();
                                $order_details .=  " - ".$product->getSku();
                                $order_details .=  " ( ".$item->getQtyOrdered()." ) , ";

                            }

                            $orderCommentHostory = $order->getStatusHistoryCollection();

                            $orderComment = "";
                            if(count($order->getStatusHistoryCollection()) > 0){
                                foreach($order->getStatusHistoryCollection() as $status) {
                                        if ($status->getComment()) {
                                            $orderComment = $status->getComment();
                                        }
                                }
                            }

                            

                            $getGrandTotal = $order->getGrandTotal();


                    $weight = ($weight < 1) ? 1 : $weight;
                    $ship_qty = ($ship_qty < 1) ? 1 : $ship_qty;
                    $fragile = ($fragile == '0' ? 'No' : 'Yes');
                    $full_name = $firstname." ".$middlename." ".$lastname;
                    $ship_address = implode(' , ', $streets);

                    $consignment = array(
                        "userName"              => $customer_name,
                        "password"              => $customer_password,
                        "costCenterCode"        =>  $cc_code,
                        "consigneeName"         =>  $full_name,
                        "consigneeAddress"      =>  $ship_address,
                        "consigneeMobNo"        =>  $ship_telephone,
                        "consigneeEmail"        =>  $ship_email,
                        "originCityName"        =>  $origin_citycode,
                        "destinationCityName"   => $ship_city,
                        "weight"                => $weight,
                        "pieces"                =>  $ship_qty,
                        "codAmount"             =>  $getGrandTotal,
                        "customerReferenceNo"   =>  $order_id,
                        "services"              =>  $service,
                        "productDetails"        =>  $order_details,
                        "fragile"               =>  $fragile,
                        "remarks"               =>  $orderComment,
                        "insuranceValue"        => $insurance
                    );



                    //print_r($ship_address);

                    $parameters = "{\"userName\":\"$customer_name\",\"password\":\"$customer_password\",\"costCenterCode\":\"$cc_code\",\"consigneeName\":\"$full_name\",\"consigneeAddress\":\"$ship_address\",\"consigneeMobNo\":\"$ship_telephone\",\"consigneeEmail\":\"$ship_email\",\"originCityName\":\"$origin_citycode\",\"destinationCityName\":\"$ship_city\",\"weight\":$weight,\"pieces\":$ship_qty,\"codAmount\":\"$getGrandTotal\",\"customerReferenceNo\":\"$order_id\",\"services\":\"$service\",\"productDetails\":\"$order_details\",\"fragile\":\"$fragile\",\"remarks\":\"$orderComment\",\"insuranceValue\":$insurance}";
                    $url = "";
                    if($live_mode == 1){
                        $url = "https://apis.tcscourier.com/production/v1/cod/create-order";
                    }else{
                        $url = "https://apis.tcscourier.com/uat/v1/cod/create-order";
                    }


                    $curl = curl_init();
                    // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
                    // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                    curl_setopt_array($curl, array(
                      CURLOPT_URL => $url,
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 400,
                      CURLOPT_SSL_VERIFYHOST => 0,
                      CURLOPT_SSL_VERIFYPEER => 0,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "POST",
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
            // $this->messageManager->addErrorMessage(__($response));
            //           print_r($response);
            //           print_r($err);
            //           exit();
                    if ($err) {
                      //echo "cURL Error #:" . $err;
                    } else {
                      $result   = json_decode($response);
                      $message  = "";
                      $cn       = "";
                      if($result->returnStatus->status == 'SUCCESS'){

                        $message = $result->returnStatus->message;
                        $str = $result->bookingReply->result;
                        $cn = preg_replace('/\D/', '', $str);

                        try{
                            $data =  [
                                //'service_type'=>$service_type,
                                'total_amount'=>$getGrandTotal,
                                'charges'=> 0,
                                'city'=>$ship_city,
                                'cod_amount'=>0,
                                'collect_cash'=>'y',
                                'insurance'=>'N',
                                'order_code'=> $order_id,
                                'cn_number'=> $cn,
                                'oms' => $enable_oms ? 'Y' : 'N',
                            ];
                        
                            $success = true;
                            $this->updateCNNumber($order_id,$cn,'TcsCourier',$data);

                            $cn_text .= $cn.' <div style="margin-top:3px;">
                                    <a href="javascript:void(0);" data-cn="'.$cn.'">Tracking</a>
                            </div>';
                            $state = "tcs";
                            $status = 'complete';
                            $logistic_type = "TcsCourier";

                                if(!empty($cn)){
                                    // Load the order
                                    $orderShip = $this->objectManager->create('Magento\Sales\Model\Order')
                                    ->load($order_id);
                                    // Check if order has already shipped or can be shipped
                                    if (!$orderShip->canShip()) {
                                      //  $this->messageManager->addErrorMessage(__('Cannot create shipment order #%1. Please try again later.',$order_id));
                                    }else{
                                    // Initialize the order shipment object
                                    $convertOrder = $this->objectManager->create('Magento\Sales\Model\Convert\Order');
                                    $shipment = $convertOrder->toShipment($orderShip);
                                    // Loop through order items
                                    foreach ($orderShip->getAllItems() AS $orderItem) {
                                    // Check if order item is virtual or has quantity to ship
                                        if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                                            continue;
                                        }
                                        $qtyShipped = $orderItem->getQtyToShip();
                                    // Create shipment item with qty
                                        $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                                    // Add shipment item to shipment
                                        $shipment->addItem($shipmentItem);
                                    }
                                    
                                        // Register shipment
                                        $shipment->register();
                                        $data = array(
                                            'carrier_code' => 'custom',
                                            'title' => $logistic_type,
                                            'number' => $cn, // Replace with your tracking number
                                        );
                                        
                                        $shipment->getOrder()->setIsInProcess(true);
                                        try {
                                        // Save created shipment and order
                                            $track = $this->objectManager->create('Magento\Sales\Model\Order\Shipment\TrackFactory')->create()->addData($data);
                                            $shipment->addTrack($track)->save();
                                            $shipment->save();
                                            $shipment->getOrder()->save();
                                        // Send email
                                            // $this->objectManager->create('Magento\Shipping\Model\ShipmentNotifier')
                                            // ->notify($shipment);
                                            $shipment->save();
                                        } catch (\Exception $e) {
                                            throw new \Magento\Framework\Exception\LocalizedException(
                                                __($e->getMessage())
                                            );
                                        }
                                    }
                                }

                            if($logistic_type == 'TcsCourier'){
                                $state = "tcs";
                                $status = 'complete';
                            }

                            $state = $state;
                            $status = $status;
                            $comment = '';
                            $isNotified = false;
                            $order->setState($state);
                            $order->setStatus($status);
                            $order->addStatusToHistory($order->getStatus(), $comment);
                            $order->save();
                            $deleted++;

                        }catch(\Exception $e){
                            $this->messageManager->addErrorMessage(__('Cannot ship order #%1. Please try again later.',$order_id));
                        }

                      }else{
                        if($result->returnStatus->code == '0408' || $result->returnStatus->code == '0500'){
                            $message   = 'TCS ERROR';
                            $this->messageManager->addErrorMessage(__('Cannot ship order #%1. Please try again later.',$order_id." ".$message));
                        }else{
                            $message   = 'TCS ERROR';
                             $this->messageManager->addErrorMessage(__('Cannot ship order #%1. Please try again later.',$order_id." ".$message));
                        }
                      }
                    }
                    
        		}else{
        		    $this->messageManager->addErrorMessage(__('Cannot Ship already CN exists order #%1. Please try again later.',$order_id));
        		}
                    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            }
            if ($deleted) {
                $this->messageManager->addSuccessMessage(__('A total of %1 order(s) has been CN GENERATE.', $deleted));
            }
        }
        
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath($this->getComponentRefererUrl());

        return $resultRedirect;
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
    
    
    private function getMultiApiCN($order_id,$service_type,$charges,$insurance,$city,$cod_amount,$collect_cash){
        $specialcharacters = array(",", ";", "'", ":", "/", "#", "^", "&", "*", "(", ")");
		$error= $response = false;
		$cn_id = false;
		try{
			$order = $this->objectManager->create('Magento\Sales\Model\Order')->load($order_id);

			$m_order_code = $order->getIncrementId();
			$auth = '<api_code>'. $this->helperData->getGeneralConfig('api_key').'</api_code>
				<acno>'. $this->helperData->getGeneralConfig('accout_number').'</acno>
				<testbit>'. ( (int)$this->helperData->getGeneralConfig('live_mode') ? 'n' : 'y').'</testbit>
				<userid>'. $this->helperData->getGeneralConfig('customer_name').'</userid>
				<password>'.$this->helperData->getGeneralConfig('customer_password').'</password>';

			$OriginCity = $this->helperData->getGeneralConfig('origin_citycode');
			$insurancePrice = 0;
			$orderStatus = "B";

			$paymentType = $order->getPayment()->getMethodInstance()->getCode();
			
			$comments = "";
			$i = 0;
			foreach ($order->getStatusHistoryCollection() as $status) {
                    if ($status->getComment() && $i ==0) {
                           $comments = $status->getComment();
                           $i++;
                    }
            }

			$billingAddress = $order->getBillingAddress();
			$shippingAddress = $order->getShippingAddress();
            $shipping_charges = $order->getShippingAmount();
            $discount_amount = $order->getBaseDiscountAmount();
			$billingCity = $billingAddress->getCity();
			
			$billingFax = $billingAddress->getFax();
			
			$shippingCity = $shippingAddress->getCity();

			$billingCity = $shippingCity = trim($city);

			$orderId = $order->getId();
			$orderIncrementId = $order->getIncrementId();
			$orderGrandTotal = $order->getGrandTotal();

			$giftCharges = 0;
			if($order->getData('base_osc_gift_wrap_amount')){
				$giftCharges = $order->getData('base_osc_gift_wrap_amount');
			}

			   $giftMessage = $this->objectManager->create('Magento\GiftMessage\Model\MessageFactory');
                $giftMessageDetails = $giftMessage->create()->load($order->getGiftMessageId());

                $giftMessageXml = '';
                if(!empty($order->getGiftMessageId())){
                	$giftMessageXml = '
                	  <id>'.$order->getGiftMessageId().'</id>
					  <sender>'.$giftMessageDetails->getSender().'</sender>
					  <recipient>'.str_replace($specialcharacters," ",$giftMessageDetails->getRecipient()).'</recipient>
					  <message>'.str_replace($specialcharacters," ",$giftMessageDetails->getMessage()).'</message>';
                }

			$addressInfo = '<shipper_name>'.str_replace($specialcharacters," ",$shippingAddress->getName()).'</shipper_name>
				<shipper_email>'.str_replace($specialcharacters," ",$shippingAddress->getEmail()).'</shipper_email>
				<shipper_contact>'.str_replace($specialcharacters," ",$shippingAddress->getTelephone()).'</shipper_contact>
				<shipper_address>'.str_replace($specialcharacters," ",$shippingAddress['street']).'</shipper_address>
				<shipper_city>'.str_replace($specialcharacters," ",$billingCity).'</shipper_city>
				<shipper_country>'.$billingAddress->getCountryId().'</shipper_country>
				<billing_name>'.str_replace($specialcharacters," ",$billingAddress->getName()).'</billing_name>
				<bill_email>'.$billingAddress->getEmail().'</bill_email>
				<billing_contact>'.$billingAddress->getTelephone().'</billing_contact>
				<billing_fax>'.$billingFax.'</billing_fax>
				<billing_address>'.str_replace($specialcharacters," ",$billingAddress['street']).'</billing_address>
				<billing_city>'.$shippingCity.'</billing_city>
				<billing_country>'.$billingAddress->getCountryId().'</billing_country>';

			$products = [];
			$items = $order->getAllItems();
			foreach($items as $item):
				$productObject = $this->objectManager->get('Magento\Catalog\Model\Product');
                $productObjectLoad = $productObject->load($item->getProductId());
				
				$itemSpecialPrice = 0;
				if($item->getSpecialPrice()){
					$itemSpecialPrice = $item->getSpecialPrice();
				}
				
				$itemFinalPrice = 0;
				if($item->getFinalPrice()){
					$itemFinalPrice = $item->getFinalPrice();
				}
				
				$products[] = '<products_detail>
					  <product_code>'.$item->getSku().'</product_code>
					  <product_name>'.str_replace($specialcharacters," ",$item->getName()).'</product_name>
					  <item_upc>'. $productObjectLoad->getItemUpc().'</item_upc>
					  <product_price>'. number_format($item->getPrice(),2,'.','').'</product_price>
					  <product_quantity>'.$item->getQtyOrdered().'</product_quantity>
					  <product_variations>None</product_variations>
					  <product_special_price>'.$itemSpecialPrice.'</product_special_price>
					  <product_final_price>'.$itemFinalPrice.'</product_final_price>
					</products_detail>';
			endforeach;

			$cn_generate = 'y';
			$oms = '';
			$enable_oms = (int)@$this->helperData->getGeneralConfig('enable_oms');

			if( $enable_oms ){
				$cn_generate = 'n';
				$oms = '<oms>1</oms>';
			}
            $comments=strip_tags($comments);
			
			$vat = 0;
			if($order->getTaxAmount()){
			    $vat = $order->getTaxAmount();
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
				  <order_total>'.$orderGrandTotal.'</order_total>
				  <gift_charges>'.$giftCharges.'</gift_charges>
				  <gift_data>'.$giftMessageXml.'</gift_data>
				  <credit_card>NC</credit_card>
				  <customer_comment>'.$comments.'</customer_comment>
				  <staff_comment></staff_comment>
				  <shipping_charges>'.$shipping_charges.'</shipping_charges>
				  <discount_amount>'.$discount_amount.'</discount_amount>
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
				  <vat>'.$vat.'</vat>
				  <cashcollect>'.($collect_cash ? 'y' : 'n').'</cashcollect>
				  <magento_order_code>'.$m_order_code.'</magento_order_code>
				</Orderdetail>
			  </AccessRequest>
			</BenefitDocument>';
			

			
			$response = $this->helperData->callHttpMulti('order/order_api_logistics',$xml);
			
			return [$cn_id,$response,@$response['result'],$enable_oms,$response];
			exit();

			if( @$response['error']	)
				$error = $response['error'];
			else {
				if( $enable_oms ){
					if( !empty($response['result']['oms']) )
						$cn_id = 'Order Sent To TcsCourier';
					else
						$error = '';//'Unabel to send Order to TcsCourier, try again';
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
	
    
    private function getApiCN($order_id,$service_type,$charges,$insurance,$city,$cod_amount,$collect_cash){
        $specialcharacters = array(",", ";", "'", ":", "/", "#", "^", "&", "*", "(", ")");
		$error= $response = false;
		$cn_id = false;
		try{
			$order = $this->objectManager->create('Magento\Sales\Model\Order')->load($order_id);

			$m_order_code = $order->getIncrementId();
			$auth = '<api_code>'. $this->helperData->getGeneralConfig('api_key').'</api_code>
				<acno>'. $this->helperData->getGeneralConfig('accout_number').'</acno>
				<testbit>'. ( (int)$this->helperData->getGeneralConfig('live_mode') ? 'n' : 'y').'</testbit>
				<userid>'. $this->helperData->getGeneralConfig('customer_name').'</userid>
				<password>'.$this->helperData->getGeneralConfig('customer_password').'</password>';

			$OriginCity = $this->helperData->getGeneralConfig('origin_citycode');
			$insurancePrice = 0;
			$orderStatus = "B";

			$paymentType = $order->getPayment()->getMethodInstance()->getCode();

			$billingAddress = $order->getBillingAddress();
			$shippingAddress = $order->getShippingAddress();
            $shipping_charges = $order->getShippingAmount();
            $discount_amount = $order->getBaseDiscountAmount();
			$billingCity = $billingAddress->getCity();
			$shippingCity = $shippingAddress->getCity();
			
			$billingFax = $billingAddress->getFax();

			$billingCity = $shippingCity = trim($city);

			$orderId = $order->getId();
			$orderIncrementId = $order->getIncrementId();
			$orderGrandTotal = $order->getGrandTotal();
			
			
			$vat = 0;
			if($order->getTaxAmount()){
			    $vat = $order->getTaxAmount();
			}

			$giftCharges = 0;
			if($order->getData('base_osc_gift_wrap_amount')){
				$giftCharges = $order->getData('base_osc_gift_wrap_amount');
			}

			   $giftMessage = $this->objectManager->create('Magento\GiftMessage\Model\MessageFactory');
                $giftMessageDetails = $giftMessage->create()->load($order->getGiftMessageId());

                $giftMessageXml = '';
                if(!empty($order->getGiftMessageId())){
                	$giftMessageXml = '
                	  <id>'.$order->getGiftMessageId().'</id>
					  <sender>'.str_replace($specialcharacters," ",$giftMessageDetails->getSender()).'</sender>
					  <recipient>'.str_replace($specialcharacters," ",$giftMessageDetails->getRecipient()).'</recipient>
					  <message>'.str_replace($specialcharacters," ",$giftMessageDetails->getMessage()).'</message>';
                }

			$addressInfo = '<shipper_name>'.str_replace($specialcharacters," ",$shippingAddress->getName()).'</shipper_name>
				<shipper_email>'.$shippingAddress->getEmail().'</shipper_email>
				<shipper_contact>'.$shippingAddress->getTelephone().'</shipper_contact>
				<shipper_address>'.str_replace($specialcharacters," ",$shippingAddress['street']).'</shipper_address>
				<shipper_city>'.$billingCity.'</shipper_city>
				<shipper_country>'.$billingAddress->getCountryId().'</shipper_country>
				<billing_name>'.str_replace($specialcharacters," ",$billingAddress->getName()).'</billing_name>
				<bill_email>'.$billingAddress->getEmail().'</bill_email>
				<billing_fax>'.$billingFax.'</billing_fax>
				<billing_contact>'.$billingAddress->getTelephone().'</billing_contact>
				<billing_address>'.str_replace($specialcharacters," ",$billingAddress['street']).'</billing_address>
				<billing_city>'.$shippingCity.'</billing_city>
				<billing_country>'.$billingAddress->getCountryId().'</billing_country>';

			$products = [];
			$items = $order->getAllItems();
			foreach($items as $item):
				$productObject = $this->objectManager->get('Magento\Catalog\Model\Product');
                $productObjectLoad = $productObject->load($item->getProductId());
				
				$itemSpecialPrice = 0;
				if($item->getSpecialPrice()){
					$itemSpecialPrice = $item->getSpecialPrice();
				}
				
				$itemFinalPrice = 0;
				if($item->getFinalPrice()){
					$itemFinalPrice = $item->getFinalPrice();
				}
				
				$products[] = '<products_detail>
					  <product_code>'.$item->getSku().'</product_code>
					  <product_name>'.str_replace($specialcharacters," ",$item->getName()).'</product_name>
					  <item_upc>'. $productObjectLoad->getItemUpc().'</item_upc>
					  <product_price>'. number_format($item->getPrice(),2,'.','').'</product_price>
					  <product_quantity>'.$item->getQtyOrdered().'</product_quantity>
					  <product_store_id>'.$item->getStoreId().'</product_store_id>
					  <product_variations>None</product_variations>
					  <product_special_price>'.$itemSpecialPrice.'</product_special_price>
					  <product_final_price>'.$itemFinalPrice.'</product_final_price>
					</products_detail>';
			endforeach;

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
				  <order_total>'.$orderGrandTotal.'</order_total>
				  <gift_charges>'.$giftCharges.'</gift_charges>
				  <gift_data>'.$giftMessageXml.'</gift_data>
				  <credit_card>NC</credit_card>
				  <customer_comment></customer_comment>
				  <staff_comment></staff_comment>
				  <shipping_charges>'.$shipping_charges.'</shipping_charges>
				  <discount_amount>'.$discount_amount.'</discount_amount>
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
				  <vat>'.$vat.'</vat>
				  <cashcollect>'.($collect_cash ? 'y' : 'n').'</cashcollect>
				  <magento_order_code>'.$m_order_code.'</magento_order_code>
				</Orderdetail>
			  </AccessRequest>
			</BenefitDocument>';
		//	$response = $this->helperData->callHttp('order/order_api_logistics_demo',$xml);
		$response = $this->helperData->callHttp('order/multiple_logistics',$xml);

			if( @$response['error']	)
				$error = $response['error'];
			else {
				if( $enable_oms ){
					if( !empty($response['result']['oms']) )
						$cn_id = 'Order Sent To TcsCourier';
					else
						$error = '';//'Unabel to send Order to TcsCourier, try again';
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
}