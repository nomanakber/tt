<?php
namespace TcsCourier\Shipping\Model;
use TcsCourier\Shipping\Api\BluexRepositoryInterface;
use TcsCourier\Shipping\Api\Data\BluexDataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Pricing\PriceCurrencyInterface;


class BluexRepository implements BluexRepositoryInterface
{

     /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var Product[]
     */
    protected $instances = [];

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product
     */
    protected $resourceModel;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $helperFactory;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * Review model
     *
     * @var \Magento\Review\Model\ReviewFactory
     */
    protected $_reviewFactory;

     /**
     * Review resource model
     *
     * @var \Magento\Review\Model\ResourceModel\Review\CollectionFactory
     */
    protected $_reviewsColFactory;

    /**
     * @var \Magento\Framework\Api\DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    protected $orderRepository;
    protected $searchCriteriaBuilder;

    /**
     * ProductRepository constructor.
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product $resourceModel
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Api\DataObjectHelper $dataObjectHelper
     * @param  \Magento\Review\Model\ReviewFactory $reviewFactory
     * @param  \Magento\Review\Model\ResourceModel\Review\CollectionFactory $collectionFactory
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\ResourceModel\Product $resourceModel,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        \Magento\Catalog\Helper\ImageFactory $helperFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Review\Model\ReviewFactory $reviewFactory,
        \Magento\Review\Model\ResourceModel\Review\CollectionFactory $collectionFactory,
        \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\Product $product,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->productFactory       =  $productFactory;
        $this->productRepository    = $productRepository;
        $this->storeManager         =  $storeManager;
        $this->resourceModel        =  $resourceModel;
        $this->helperFactory        =  $helperFactory;
        $this->appEmulation         =  $appEmulation;
        $this->dataObjectHelper     = $dataObjectHelper;
        $this->_reviewFactory       =  $reviewFactory;
        $this->_reviewsColFactory   =  $collectionFactory;
        $this->_objectManager       =  $objectManager;
        $this->priceCurrency        =  $priceCurrency;
        $this->_product = $product;
        $this->_stockStateInterface = $stockStateInterface;
        $this->_stockRegistry = $stockRegistry;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;

    }

    /**
     * updateOrderQty
     * @param int $order_id The order ID.
     * @param string $sku .
     * @param int $qty.
     * @return array
     */
    public function updateOrderItemQty($order_id,$sku,$qty){

        $_order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($order_id);
        if (!$_order->getEntityId()) {
                throw new NoSuchEntityException(__('Requested order doesn\'t exist'));
        }

         //echo '<pre>'; print_r($_order->getBaseGrandTotal());exit;

        $productSkuCheck = $this->getProductRequest($sku);
        $StockState = $this->_objectManager->get('\Magento\CatalogInventory\Api\StockStateInterface');
        $qtyCheck = $StockState->getStockQty($productSkuCheck->getId(), $productSkuCheck->getStore()->getWebsiteId());

        if($qty > $qtyCheck){
             throw new NoSuchEntityException(__('Requested quantity doesn\'t exist'));
        }

        $updatedCheck = 0;
        $items = $_order->getAllItems();
        foreach($items as $item){

            $base_grand_total = $_order->getBaseGrandTotal();
            $base_subtotal = $_order->getBaseSubtotal();
            $base_tva = $_order->getBaseTaxAmount();
            $grand_total = $_order->getGrandTotal();
            $subtotal = $_order->getSubtotal();
            $tva = $_order->getTaxAmount();

            $base_subtotal_incl_tax = $_order->getBaseSubtotalInclTax();
            $subtotal_incl_tax = $_order->getSubtotalInclTax();
            $total_item_count = $_order->getTotalItemCount();

          //item detail
            if($sku == $item->getSku()){

               $item_price = $item->getPrice();
               $item_tva = $item->getTaxAmount();

                $subPrice = $item->getPrice()*$item->getQtyOrdered();
                $Price = $item->getPrice()*$qty;
                $Tax = $item->getTaxAmount()*$qty;
                $Discount = $item->getDiscountAmount()*$qty;
                $item->setQtyOrdered($qty);

                 $item->setRowTotal($Price+$Tax+$Discount);

                 $item->setBaseRowTotal($Price+$Tax+$Discount);

                 $item->setRowTotalInclTax($Price+$Tax+$Discount);
                 $item->setBaseRowTotalInclTax($Price+$Tax+$Discount);
                 $item->save();

                 $_order->setBaseGrandTotal($base_grand_total+$Price+$Tax-$subPrice);
                $_order->setBaseSubtotal($base_subtotal+$Price-$subPrice);
                $_order->setBaseTaxAmount($base_tva+$Tax);
                $_order->setGrandTotal($grand_total+$Price+$Tax-$subPrice);
                $_order->setSubtotal($subtotal+$Price-$subPrice);
                $_order->setTaxAmount($tva+$Tax);
                $_order->setBaseSubtotalInclTax($base_subtotal_incl_tax+$Price-$subPrice);
                $_order->setSubtotalInclTax($subtotal_incl_tax+$Price-$subPrice);
                $_order->setTotalItemCount(count($items));
                 $updatedCheck = 1;

               break;
            }
        }



        if($updatedCheck == 1){
            $_order->save();
            return 'Qty updated';
        }else{
            return 'Qty not updated';
        }


    }
    
    /**
     * updateCn
     * @param string $cn .
     * @param int $order_number.
     * @param string $logistic_type.
     * @return string
     */
     
    public function updateCn($cn,$order_number,$logistic_type){
        $data ="Manual Update";
        $this->updateCNNumber($order_number,$cn,$logistic_type,$data);
        return 1;
    }


     /**
     * updateProductQty
     * @param string $sku .
     * @param int $qty.
     * @param int $storeid.
     * @return array
     */
    public function updateProductQty($sku,$qty,$storeid){
        $resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        
      $this->_objectManager->get('Psr\Log\LoggerInterface')->info("Start Updating");
      $storeIdPK = $storeid; //For PK
      $productcollection = $this->_objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection')
            ->addAttributeToFilter('item_upc',['eq'=>$sku]);
      $productcollection->addStoreFilter($storeIdPK);

      $proId = '';

      if(count($productcollection) > 0){
        $proId = $productcollection->getData()[0]['entity_id'];
      }

      if(!$proId){
        $this->_objectManager->get('Psr\Log\LoggerInterface')->info("Requested product doesn\'t exist");
         throw new NoSuchEntityException(__('Requested product doesn\'t exist'));
      }

      $oProduct = $this->productRepository->getById($proId,false, $storeIdPK, false);

      if (!$oProduct) {
         $this->_objectManager->get('Psr\Log\LoggerInterface')->info("Requested product doesn\'t exist");
         throw new NoSuchEntityException(__('Requested product doesn\'t exist'));
      }
      
      $this->_objectManager->get('Psr\Log\LoggerInterface')->info("Product id get by item_upc: ".$proId);


        // get all pending orders
        //$sql = "SELECT IFNULL(sum(sales_order_item.qty_ordered),0) as pending_qty FROM `sales_order` INNER JOIN sales_order_item ON sales_order.entity_id = sales_order_item.order_id WHERE sales_order.status != 'wh_bluex' and sales_order.status != 'canceled' and sales_order.status != 'shipbyTcsCourier' and sales_order.status != 'TcsCourier' and sales_order.status != 'ship_by_cc' and sales_order.status != 'ship_by_mnp' and sales_order.status != 'ship_by_TcsCourier' and sales_order.status != 'delivered_by_TcsCourier' and sales_order.status != 'delivered_by_mnp' and sales_order.status != 'delivered_by_cc' and sales_order.status != 'ax_locked' and sales_order.status != 'fraud' and sales_order.status != 'holded' and sales_order.status != 'dispatch' and sales_order.status != 'complete' and sales_order.status != 'completed'  and sales_order.store_id = '$storeIdPK' AND sales_order_item.product_id = '$proId'";
        $sql = "SELECT IFNULL(sum(sales_order_item.qty_ordered),0) as pending_qty FROM `sales_order` INNER JOIN sales_order_item ON sales_order.entity_id = sales_order_item.order_id WHERE sales_order.status != 'canceled' and sales_order.status != 'shipbyTcsCourier' and sales_order.status != 'TcsCourier' and sales_order.status != 'ship_by_cc' and sales_order.status != 'ship_by_mnp' and sales_order.status != 'ship_by_TcsCourier' and sales_order.status != 'delivered_by_TcsCourier' and sales_order.status != 'delivered_by_mnp' and sales_order.status != 'delivered_by_cc' and sales_order.status != 'ax_locked' and sales_order.status != 'fraud' and sales_order.status != 'holded' and sales_order.status != 'dispatch' and sales_order.status != 'complete' and sales_order.status != 'completed'  and sales_order.status != 'closed' and sales_order.store_id = '$storeIdPK' AND sales_order_item.product_id = '$proId' AND sales_order.created_at  BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
        $result = $connection->fetchAll($sql);
        $this->_objectManager->get('Psr\Log\LoggerInterface')->info("get data from query".json_encode($result));
        $pendingqty = $result[0]['pending_qty'];
        $qty = (int)$qty - (int)$pendingqty;
        // subtract pending qty
        $this->_objectManager->get('Psr\Log\LoggerInterface')->info("subtract pending qty");
      if($qty > 0){
          $this->_objectManager->get('Psr\Log\LoggerInterface')->info("updating stock");
          $status = 1;

          ### Load stock item
          $stockItem = $this->_stockRegistry->getStockItem($oProduct->getId());
            $this->_objectManager->get('Psr\Log\LoggerInterface')->info("stockRegistry");
          $stockItem->setQty($qty);
            $this->_objectManager->get('Psr\Log\LoggerInterface')->info("stockItem qty set");
          $stockItem->setData('manage_stock', $status);
          $this->_objectManager->get('Psr\Log\LoggerInterface')->info("manage_stock");
          $stockItem->setData('is_in_stock', $status);
          $this->_objectManager->get('Psr\Log\LoggerInterface')->info("is_in_stock");
          $stockItem->setData('use_config_notify_stock_qty', 1);
            $this->_objectManager->get('Psr\Log\LoggerInterface')->info("use_config_notify_stock_qty");
          $this->_stockRegistry->updateStockItemBySku($oProduct->getSku(), $stockItem);
          $this->_objectManager->get('Psr\Log\LoggerInterface')->info("Qty Updated -- ".$oProduct->getSku());
          return 'Qty Updated---'.$oProduct->getSku().','.(int)$pendingqty;

     }else{
        $sql = "SELECT sales_order_item.qty_ordered as pending_qty,sales_order.entity_id as order_code,sales_order_item.item_id FROM `sales_order` INNER JOIN sales_order_item ON sales_order.entity_id = sales_order_item.order_id WHERE sales_order.status != 'canceled' and sales_order.status != 'shipbyTcsCourier' and sales_order.status != 'TcsCourier' and sales_order.status != 'ship_by_cc' and sales_order.status != 'ship_by_mnp' and sales_order.status != 'ship_by_TcsCourier' and sales_order.status != 'delivered_by_TcsCourier' and sales_order.status != 'delivered_by_mnp' and sales_order.status != 'delivered_by_cc' and sales_order.status != 'ax_locked' and sales_order.status != 'fraud' and sales_order.status != 'holded' and sales_order.status != 'dispatch' and sales_order.status != 'complete' and sales_order.status != 'completed'  and sales_order.status != 'closed'  and sales_order.status != 'cancel' and sales_order.store_id = '$storeIdPK' AND sales_order_item.product_id = '$proId' AND sales_order.created_at  BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
        $result = $connection->fetchAll($sql);

         $this->_objectManager->get('Psr\Log\LoggerInterface')->info(json_encode(array("status"=>0,"message"=>"Qty must be greater than 0","pending_qty"=>(int)$pendingqty,"pending_data"=>$result)));
         throw new NoSuchEntityException(__(json_encode(array("status"=>0,"message"=>"Qty must be greater than 0","pending_qty"=>(int)$pendingqty,"pending_data"=>$result))));
          $this->_objectManager->get('Psr\Log\LoggerInterface')->info(json_encode(array("status"=>0,"message"=>"Qty must be greater than 0","pending_qty"=>(int)$pendingqty,"pending_data"=>$result)));
     }
    $this->_objectManager->get('Psr\Log\LoggerInterface')->info("end of class");
   }


    /**
     * @param int $order_id The order ID.
     * @param string $sku .
     * @return array
     */
    public function removeOrderItem($order_id,$sku){

        $_order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($order_id);
        if (!$_order->getEntityId()) {
                throw new NoSuchEntityException(__('Requested order doesn\'t exist'));
        }

        $productSkuCheck = $this->getProductRequest($sku);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $stockManagement = $objectManager->create('\Magento\CatalogInventory\Api\StockManagementInterface');

        $items = $_order->getAllItems();
        $updated = 0;

        foreach ($items as $item){
            $base_grand_total = $_order->getBaseGrandTotal();
            $base_subtotal = $_order->getBaseSubtotal();
            $base_tva = $_order->getBaseTaxAmount();
            $grand_total = $_order->getGrandTotal();
            $subtotal = $_order->getSubtotal();
            $tva = $_order->getTaxAmount();

            $base_subtotal_incl_tax = $_order->getBaseSubtotalInclTax();
            $subtotal_incl_tax = $_order->getSubtotalInclTax();
            $total_item_count = $_order->getTotalItemCount();


            if($sku == $item->getSku()){
                $orderQty = (int)$item->getQtyOrdered();
                $item_price = $item->getPrice()*$orderQty;
                $item_tva = $item->getTaxAmount()*$orderQty;

                $item->delete();
                $_order->setBaseGrandTotal($base_grand_total-$item_price-$item_tva);
                $_order->setBaseSubtotal($base_subtotal-$item_price);
                $_order->setBaseTaxAmount($base_tva-$item_tva);
                $_order->setGrandTotal($grand_total-$item_price-$item_tva);
                $_order->setSubtotal($subtotal-$item_price);
                $_order->setTaxAmount($tva-$item_tva);
                $_order->setBaseSubtotalInclTax($base_subtotal_incl_tax-$item_price);
                $_order->setSubtotalInclTax($subtotal_incl_tax-$item_price);
                $_order->setTotalItemCount(count($items)-1);
                $_order->save();
                $updated = 1;

                $qty = $item->getQtyOrdered() - max($item->getQtyShipped(), $item->getQtyInvoiced()) - $item->getQtyCanceled();

                if ($item->getId() && $item->getProductId() && $qty) {
                    $stockManagement->backItemQty($item->getProductId(), $qty, $item->getStore()->getWebsiteId());
                }

             }
           }


                if($updated == 1){
                    return 'Item removed';
                }else{
                    return 'Item not removed';
            }
    }


    /**
     * @param int $order_id The order ID.
     * @param string $sku .
     * @param int $qty.
     * @return array
     */
    public function addOrderItem($order_id,$sku,$qty){

        $_order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($order_id);
        if (!$_order->getEntityId()) {
                throw new NoSuchEntityException(__('Requested order doesn\'t exist'));
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $stockManagement = $objectManager->get('\Magento\CatalogInventory\Api\StockRegistryInterface');

        $stockItem = $objectManager->get('\Magento\CatalogInventory\Model\Stock\StockItemRepository');
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();



        $product = $this->getProductRequest($sku);
        $StockState = $this->_objectManager->get('\Magento\CatalogInventory\Api\StockStateInterface');
        $qtyCheck = $StockState->getStockQty($product->getId(), $product->getStore()->getWebsiteId());

        if($qty > $qtyCheck){
             throw new NoSuchEntityException(__('Requested quantity doesn\'t exist'));
        }

        $subractQuantity = $qtyCheck - $qty;
        $status = 1;
        $id = $product->getId();

            $base_grand_total = $_order->getBaseGrandTotal();
            $base_subtotal = $_order->getBaseSubtotal();
            $base_tva = $_order->getBaseTaxAmount();
            $grand_total = $_order->getGrandTotal();
            $subtotal = $_order->getSubtotal();
            $tva = $_order->getTaxAmount();
            $base_subtotal_incl_tax = $_order->getBaseSubtotalInclTax();
            $subtotal_incl_tax = $_order->getSubtotalInclTax();
            $total_item_count = $_order->getTotalItemCount();


             $orderItem = $this->_objectManager->create(
                        'Magento\Sales\Model\Order\Item'
                    )->setStoreId($_order->getStore()->getStoreId())
            ->setQuoteItemId(NULL)
            ->setQuoteParentItemId(NULL)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setQtyBackordered(NULL)
            ->setTotalQtyOrdered($qty)
            ->setQtyOrdered($qty)
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setPrice($product->getPrice())
            ->setBasePrice($product->getPrice())
            ->setOriginalPrice($product->getPrice())
            ->setRowTotal($product->getPrice()*$qty)
            ->setBaseRowTotal($product->getPrice()*$qty)
            ->setOrder($_order);
             $orderItem->save();

                $Price = $product->getPrice()*$qty;


          $_order->setBaseGrandTotal($base_grand_total+$Price);
                $_order->setBaseSubtotal($base_subtotal+$Price);
                $_order->setBaseTaxAmount($base_tva);
                $_order->setGrandTotal($grand_total+$Price);
                $_order->setSubtotal($subtotal+$Price);
                $_order->setTaxAmount($tva);
                $_order->setBaseSubtotalInclTax($base_subtotal_incl_tax+$Price);
                $_order->setSubtotalInclTax($subtotal_incl_tax+$Price);
                $_order->setTotalItemCount(count($total_item_count)+1);

         $_order->save();


        $sqlStockItem = "UPDATE cataloginventory_stock_item SET qty = ".$subractQuantity.", is_in_stock = ".$status." WHERE product_id = ".$id;
        $connection->query($sqlStockItem);
        $sqlStockStatus = "UPDATE cataloginventory_stock_status SET qty = ".$subractQuantity.", stock_status = ".$status." WHERE product_id = ".$id;
        $connection->query($sqlStockStatus);

        /*
        Not Working using default magento process
        $stockpc->setData('is_in_stock',1);
        $stockpc->setData('qty',$subractQuantity); //set updated quantity
        $stockpc->setData('stock_qty',$subractQuantity); //set updated quantity
        $stockpc->setData('manage_stock',1);
        $stockpc->setData('use_config_notify_stock_qty',0);
        $stockpc->save();
        $product->save();*/

        return 'Item added';

    }


    public function getIdBySkuClass($sku)
    {
       $productId = $this->resourceModel->getIdBySku($sku);
       return $productId;
    }
    

    /**
     * {@inheritdoc}
     */
    public function getProductRequest($sku, $editMode = false, $storeId = null, $forceReload = false)
    {
        $cacheKey = $this->getCacheKey([$editMode, $storeId]);
        if (!isset($this->instances[$sku][$cacheKey]) || $forceReload) {
            $product = $this->productFactory->create();

            $productId = $this->resourceModel->getIdBySku($sku);

            if (!$productId) {

                throw new NoSuchEntityException(__('Requested product doesn\'t exist'));
            }
            if ($editMode) {
                $product->setData('_edit_mode', true);
            }
            if ($storeId !== null) {
                $product->setData('store_id', $storeId);
            } else {

                $storeId = $this->storeManager->getStore()->getId();
            }
            $product->load($productId);

            //Custom Attributes Data Added here
            $moreInformation = $this->getMoreInformation($product);
            $product->setCustomAttribute('additional_information', $moreInformation);
            // Custom Attributes Data Ends here
            $this->instances[$sku][$cacheKey] = $product;
            $this->instancesById[$product->getId()][$cacheKey] = $product;
        }

        return $this->instancesById[$product->getId()][$cacheKey];

   }


    public function getProductById($id, $editMode = false, $storeId = null, $forceReload = false)
    {
        if (!$id) {
            throw new InputException(__('Id required'));
        }

        $cacheKey = $this->getCacheKey([$editMode, $storeId]);
        if (!isset($this->instances[$id][$cacheKey]) || $forceReload) {
            $product = $this->productFactory->create();

            $productId = $product->load($id);

            if (!$productId->getId()) {

                throw new NoSuchEntityException(__('Requested product doesn\'t exist'));
            }
            if ($editMode) {
                $product->setData('_edit_mode', true);
            }
            if ($storeId !== null) {
                $product->setData('store_id', $storeId);
            } else {

                $storeId = $this->storeManager->getStore()->getId();
            }
            //Custom Attributes Data Added here
            $moreInformation = $this->getMoreInformation($product);
            $product->setCustomAttribute('additional_information', $moreInformation);
            // Custom Attributes Data Ends here
            $this->instances[$id][$cacheKey] = $product;
            $this->instancesById[$product->getId()][$cacheKey] = $product;
        }

        return $this->instancesById[$product->getId()][$cacheKey];

   }

   /**
     * load entity
     *
     * @param int $id
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderRequest($id , $storeId = null)
    {
        if (!$id) {
            throw new InputException(__('Id required'));
        }
            /** @var OrderInterface $entity */
            $entity = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($id);
            if (!$entity->getEntityId()) {
                throw new NoSuchEntityException(__('Requested entity doesn\'t exist'));
            }

         return $entity;
    }

    /**
     * Compose and get order full history.
     * Consists of the status history comments as well as of invoices, shipments and creditmemos creations
     *
     * @TODO This method requires refactoring. Need to create separate model for comment history handling
     * and avoid generating it dynamically
     *
     * @return array
     */
    public function getFullHistory($id)
    {
        $data = [];
        if (!$id) {
            throw new InputException(__('Id required'));
        }
            /** @var OrderInterface $entity */
            $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($id);
            if (!$order->getEntityId()) {
                throw new NoSuchEntityException(__('Requested entity doesn\'t exist'));
            }

        $history = [];
        foreach ($order->getAllStatusHistory() as $orderComment) {
            $history['order'][] = $this->_prepareHistoryItem(
                $orderComment->getStatusLabel(),
                $orderComment->getIsCustomerNotified(),
                $orderComment->getCreatedAt(),
                $orderComment->getComment()
            );
        }

        foreach ($order->getCreditmemosCollection() as $_memo) {
            $history['memo'][] = $this->_prepareHistoryItem(
                __('Credit memo #%1 created', $_memo->getIncrementId()),
                $_memo->getEmailSent(),
                $this->getOrderAdminDate($_memo->getCreatedAt())
            );

            foreach ($_memo->getCommentsCollection() as $_comment) {
                $history[] = $this->_prepareHistoryItem(
                    __('Credit memo #%1 comment added', $_memo->getIncrementId()),
                    $_comment->getIsCustomerNotified(),
                    $_comment->getCreatedAt(),
                    $_comment->getComment()
                );
            }
        }

        foreach ($order->getShipmentsCollection() as $_shipment) {
            $history['shipment'][] = $this->_prepareHistoryItem(
                __('Shipment #%1 created', $_shipment->getIncrementId()),
                $_shipment->getEmailSent(),
                $_shipment->getCreatedAt()
            );

            foreach ($_shipment->getCommentsCollection() as $_comment) {
                $history[] = $this->_prepareHistoryItem(
                    __('Shipment #%1 comment added', $_shipment->getIncrementId()),
                    $_comment->getIsCustomerNotified(),
                    $_comment->getCreatedAt(),
                    $_comment->getComment()
                );
            }
        }

        foreach ($order->getInvoiceCollection() as $_invoice) {
            $history['invoice'][] = $this->_prepareHistoryItem(
                __('Invoice #%1 created', $_invoice->getIncrementId()),
                $_invoice->getEmailSent(),
                $_invoice->getCreatedAt()
            );

            foreach ($_invoice->getCommentsCollection() as $_comment) {
                $history[] = $this->_prepareHistoryItem(
                    __('Invoice #%1 comment added', $_invoice->getIncrementId()),
                    $_comment->getIsCustomerNotified(),
                    $_comment->getCreatedAt(),
                    $_comment->getComment()
                );
            }
        }

        foreach ($order->getTracksCollection() as $_track) {
            $history['tracking'][] = $this->_prepareHistoryItem(
                __('Tracking number %1 for %2 assigned', $_track->getNumber(), $_track->getTitle()),
                false,
                $_track->getCreatedAt()
            );
        }

        $data[] = $history;

        return $data;
    }


    /**
     * Map history items as array
     *
     * @param string $label
     * @param bool $notified
     * @param \DateTimeInterface $created
     * @param string $comment
     * @return array
     */
    protected function _prepareHistoryItem($label, $notified, $created, $comment = '')
    {
        return ['title' => $label, 'notified' => $notified, 'comment' => $comment, 'created_at' => $created];
    }

    /**
     * Get key for cache
     *
     * @param array $data
     * @return string
     */

    protected function getCacheKey($data)
    {
        $serializeData = [];
        foreach ($data as $key => $value) {

            if (is_object($value)) {
                $serializeData[$key] = $value->getId();
            } else {
                $serializeData[$key] = $value;
            }
        }
        return md5(serialize($serializeData));
    }




    /**
     * Get More information of the product
     * @param \Magento\Catalog\Model\Product $product
     * @return array
    */

    protected function getMoreInformation($product)
    {
        $data = [];
        $excludeAttr = [];
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getIsVisibleOnFront() && !in_array($attribute->getAttributeCode(), $excludeAttr)) {
                $value = $attribute->getFrontend()->getValue($product);

                if (!$product->hasData($attribute->getAttributeCode())) {
                    $value = __('N/A');
                } elseif ((string)$value == '') {
                    $value = __('No');
                } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                    $value = $this->priceCurrency->convertAndFormat($value);
                }

                if (is_string($value) && strlen($value)) {
                    $data[$attribute->getAttributeCode()] = [
                        'label' => __($attribute->getStoreLabel()),
                        'value' => $value,
                        'code' => $attribute->getAttributeCode(),
                    ];
                }
            }
        }

        return $data;
    }


        /**
     * {@inheritdoc}
     */
    public function getConfChild($sku)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->productRepository->get($sku);
        if ($product->getTypeId() != \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return [];
        }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $StockState = $objectManager->get('\Magento\CatalogInventory\Api\StockStateInterface');
        /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable $productTypeInstance */
        $productTypeInstance = $product->getTypeInstance();
        $productTypeInstance->setStoreFilter($product->getStoreId(), $product);

        $childrenList = [];
        /** @var \Magento\Catalog\Model\Product $child */
        foreach ($productTypeInstance->getUsedProducts($product) as $child) {

            $attributes = [];
            foreach ($child->getAttributes() as $attribute) {
                $attrCode = $attribute->getAttributeCode();
                $value = $child->getDataUsingMethod($attrCode) ?: $child->getData($attrCode);
                if (null !== $value) {
                    $attributes[$attrCode] = $value;
                }
                $attributes['quantity'] = $StockState->getStockQty($child->getId(), $child->getStore()->getWebsiteId());
            }
            $attributes['store_id'] = $child->getStoreId();

            //print_r($attributes);exit;
            /** @var \Magento\Catalog\Api\Data\ProductInterface $productDataObject */
            $productDataObject = $this->productFactory->create();
            $this->dataObjectHelper->populateWithArray(
                $productDataObject,
                $attributes,
                \Magento\Catalog\Api\Data\ProductInterface::class
            );
            $childrenList[] = $attributes;
        }
        return $childrenList;
    }

            /**
     * {@inheritdoc}
     */
    public function getConfChildById($id)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        if (!$id) {
            throw new InputException(__('Id required'));
        }

        $product = $this->productFactory->create();
        $product->load($id);

        if ($product->getTypeId() != \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            throw new InputException(__('Configurable Product Id required'));
        }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $StockState = $objectManager->get('\Magento\CatalogInventory\Api\StockStateInterface');
        /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable $productTypeInstance */
        $productTypeInstance = $product->getTypeInstance();
        $productTypeInstance->setStoreFilter($product->getStoreId(), $product);

        $childrenList = [];
        /** @var \Magento\Catalog\Model\Product $child */
        foreach ($productTypeInstance->getUsedProducts($product) as $child) {

            $attributes = [];
            foreach ($child->getAttributes() as $attribute) {
                $attrCode = $attribute->getAttributeCode();
                $value = $child->getDataUsingMethod($attrCode) ?: $child->getData($attrCode);
                if (null !== $value) {
                    $attributes[$attrCode] = $value;
                }
                $attributes['quantity'] = $StockState->getStockQty($child->getId(), $child->getStore()->getWebsiteId());
            }
            $attributes['store_id'] = $child->getStoreId();

            //print_r($attributes);exit;
            /** @var \Magento\Catalog\Api\Data\ProductInterface $productDataObject */
            $productDataObject = $this->productFactory->create();
            $this->dataObjectHelper->populateWithArray(
                $productDataObject,
                $attributes,
                \Magento\Catalog\Api\Data\ProductInterface::class
            );
            $childrenList[] = $attributes;
        }
        return $childrenList;
    }
    
    private function updateCNNumber($order_id,$cn_id,$logistic_type,$data){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
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
		
		
    	if(!empty($cn_id)){
    	    
    	    try {
			// Load the order
			$orderShip = $objectManager->create('Magento\Sales\Model\Order')
			->load($order_id);
    		// Check if order has already shipped or can be shipped
            if (!$orderShip->canShip()) {
              //  $this->messageManager->addErrorMessage(__('Cannot create shipment order #%1. Please try again later.',$order_id));
			}else{
    		// Initialize the order shipment object
			$convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
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
				    'number' => $cn_id, // Replace with your tracking number
				);
				
        		$shipment->getOrder()->setIsInProcess(true);
        		
				
				// Save created shipment and order
					$track = $objectManager->create('Magento\Sales\Model\Order\Shipment\TrackFactory')->create()->addData($data);
					$shipment->addTrack($track)->save();
					$shipment->save();
					$shipment->getOrder()->save();
				// Send email
					// $objectManager->create('Magento\Shipping\Model\ShipmentNotifier')
					// ->notify($shipment);
					$shipment->save();
			}
    	    } catch (\Exception $e) {
				// 	throw new \Magento\Framework\Exception\LocalizedException(
				// 		__($e->getMessage())
				// 	);
				echo 'Order Not Found';
				}
		}
		
        $state = "shipbyTcsCourier";
        $status = 'shipbyTcsCourier';
        if($logistic_type == 'TcsCourier'){
            $state = "shipbyTcsCourier";
            $status = 'shipbyTcsCourier';
        }
        
        if($logistic_type == 'mnp'){
            $state = "ship_by_mnp";
            $status = 'ship_by_mnp';
        }
        
        if($logistic_type == 'cc'){
            $state = "ship_by_cc";
            $status = 'ship_by_cc';
        }
        
        try{
            $order = $objectManager->create('Magento\Sales\Model\Order')->load($order_id);
    		$state = $state;
            $status = $status;
            $comment = '';
            $isNotified = false;
            $order->setState($state);
            $order->setStatus($status);
            $order->addStatusToHistory($order->getStatus(), $comment);
            $order->save();
        }catch(\Exception $e) {
// 			throw new \Magento\Framework\Exception\LocalizedException(
// 				__($e->getMessage())
// 			);
            echo 'Order Not Found';
		}

	}

}
