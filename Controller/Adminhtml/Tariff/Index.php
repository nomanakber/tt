<?php
namespace TcsCourier\Shipping\Controller\Adminhtml\Tariff;
 
class Index extends \Magento\Backend\App\Action
{
	const ADMIN_RESOURCE = 'TcsCourier_Shipping::tariff';
	protected $resultPageFactory = false;

	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory
	){
		parent::__construct($context);
		$this->resultPageFactory = $resultPageFactory;
	}

	public function execute()
	{
		$resultPage = $this->resultPageFactory->create();
		$resultPage->getConfig()->getTitle()->prepend((__('Tariff')));

		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		
		$helperData = $objectManager->create('TcsCourier\Shipping\Helper\Data');
		$urlBuilder = $objectManager->create('Magento\Framework\UrlInterface');
		
		$citiesList = $helperData->getCityList();		
		$block = $resultPage->getLayout()->getBlock('Shipping_tariff');
		
	 	$block->setCities($citiesList);
		$block->setFormurl($urlBuilder->getBaseUrl().'TcsCouriershipping/api/tariff');
		
		return $resultPage;
	}	
}