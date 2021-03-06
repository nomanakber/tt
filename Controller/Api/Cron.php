<?php
namespace TcsCourier\Shipping\Controller\Api;
use Magento\Framework\App\Action\Context;

class Cron extends \Magento\Framework\App\Action\Action{
	
	protected $helperData;
	protected $objectManager;
	protected $ordermanagement;
	protected $resource;
	
	
	public function __construct(
		\Magento\Backend\App\Action\Context $context
	){
		$this->objectManager =  $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$helperData = $this->objectManager->create('TcsCourier\Shipping\Helper\Data');
		$this->urlBuilder = $this->objectManager->create('Magento\Framework\UrlInterface');
      	$this->helperData = $helperData;
		$this->resource = $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
		$this->connection = $resource->getConnection();
		$this->dbTable = $resource->getTableName('TcsCourier_shipping_data'); 
		$this->orderManagement = $this->objectManager->create('Magento\Sales\Api\OrderManagementInterface');
		
		parent::__construct($context);
	}
	
	public function execute()
	{	    
			
		$rows = $this->connection->fetchAll("SELECT * FROM ".$this->dbTable." ORDER BY id DESC LIMIT 0,500");
		
		$order_codes = [];
		$i=0;		
		foreach($rows as $row){
			$cn_data = !empty($row['data']) ? json_decode($row['data'],true) : [];
			if( !empty($cn_data['order_code']) )
				$order_codes[] = '<order_code>'.$cn_data['order_code'].'</order_code>';
		}
		
		if( !empty($order_codes) ){ 
			$cnStatuses = $this->helperData->getCNStatus($order_codes);
		
			$statusList = array_unique(array_values($cnStatuses));
			$exitsStatuses = array_column($this->connection->fetchAll("SELECT status FROM ".$this->resource->getTableName('sales_order_status')),'status');
			$exitsStatuses2 = array_column($this->connection->fetchAll("SELECT status FROM ".$this->resource->getTableName('sales_order_status_state')),'status');
			
			foreach($statusList as $_sts){
				$__sts = trim(str_replace(' ','_',strtolower($_sts)));
				if($__sts == 'cancel' ) $__sts = 'canceled';						

				if( !in_array($__sts,$exitsStatuses) )
					$this->connection->query("INSERT INTO ".$this->resource->getTableName('sales_order_status')." (`status`,`label`) VALUES ('$__sts','$_sts')");

				if( !in_array($__sts,$exitsStatuses2) )
					$this->connection->query("INSERT INTO ".$this->resource->getTableName('sales_order_status_state')." (`status`,`state`,`is_default`,`visible_on_front`) VALUES ('$__sts','$__sts',0,1)");				
			}

			foreach ($rows as $row){
	
				$cn_data = !empty($row['data']) ? json_decode($row['data'],true) : [];
				if( !empty($cn_data['order_code']) ){
					$order_code = (int)@$cn_data['order_code'];
					if( !empty($cnStatuses[$order_code]) ){
						
						$cn_status = trim(str_replace(' ','_',strtolower($cnStatuses[$order_code])));									
						if( $cn_status != 'pending'){
							
							$orderstatus = $cn_status;
							if($cn_status == 'cancel' )
								$orderstatus = 'canceled';
							
							$order = $this->objectManager->create('\Magento\Sales\Model\Order')->load($row['order_id']);
							
							if ($order->getId() ) {
			
								if( strtolower($order->getState()) != $orderstatus ){ 
											
									$order->setState($orderstatus)->setStatus($orderstatus);
									if( $order->save() ){ 
										
										$i++;
										echo "<br>".$order->getId() ." = $orderstatus";
										
										if( 'canceled' != strtolower($order->getState()) ){ 
											$orderItems = $order->getAllItems();
											$itemQtys = array();
											foreach ($orderItems as $item) {
												$itemQtys[]=array('quantity'=>$item->getQtyOrdered(),'id'=>$item->getProductId());	
											}
											
											foreach($itemQtys as $itemQty){
												$product = $this->objectManager->create('\Magento\Catalog\Model\Product')->load($itemQty['id']);
												$stockItem = $this->objectManager->get('\Magento\CatalogInventory\Api\StockRegistryInterface');
						
												$stock = $stockItem->getStockItemBySku($product->getSku());
												$qty = $stock->getQty();
												
												$stock->setQty($qty+$itemQty['quantity']);
												$stockItem->updateStockItemBySku($product->getSku(), $stock);
											}  
										}
											
									}
								}
								
							}
						}
					}
				}
			}
		}
		echo "<br><br>DONE: ".$i;
		exit;
	}
}