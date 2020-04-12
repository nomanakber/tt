<?php
namespace TcsCourier\Shipping\Model\Config\Source;

class Services implements \Magento\Framework\Option\ArrayInterface
{
 	public function toOptionArray(){
		
		
		$list = array(
			array('value' => 'O', 'label' => ucfirst('Overnight')),
			array('value' => 'D', 'label' => ucfirst('2nd Day')),
			array('value' => 'S', 'label' => ucfirst('Same day'))
		);		
		return $list;
 	}
}