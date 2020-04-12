<?php
namespace TcsCourier\Shipping\Model\Config\Source;

class Cities implements \Magento\Framework\Option\ArrayInterface
{
 	public function toOptionArray(){
		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$cityList = $objectManager->create('TcsCourier\Shipping\Helper\Data')->getCityList();
		$cities_list = array("FAISALABAD", "GUJRANWALA", "HYDERABAD", "ISLAMABAD", "KARACHI", "LAHORE", "MULTAN", "PESHAWAR", "QUETTA", "RAWALPINDI", "SUKKUR", "SIALKOT", "KHANEWAL", "SAHIWAL", "SWAT", "JARANWALA", "ABBOTABAD", "DUBAI", "OKARA", "DEPAL PUR", "BHAI PHARU", "MARDAN", "SHEIKHUPURA", "GHAKAR", "MAILSI", "SARGODAH", "BAHAWALNAGAR", "SANGLA HILL", "BUREWALA", "SHAHKOT", "PASROOR", "TEMARGARAH", "RAIWIND", "LODHRAN", "VEHARI", "BAHAWALPUR", "CHAKWAL", "GUJARKHAN", "CHARSADDA", "MIAN CHANOO", "MIANWALI", "HAFIZABAD", "DINA", "CHINIOT", "KOTLI-A.KASHMIR", "JHELUM", "MUZAFFARABAD AK", "BHAKKAR", "GUJRAT", "JHANG", "PATOKI", "DERA GHAZI KHAN", "TAXILA", "WAH", "KASUR", "GOJRA", "LALAMUSA", "KARAK", "MURIDKEY", "KAMALIA", "KHEWRA DANDOT", "ATTOCK", "PAK PATTAN SHAR", "MIRPUR A.K.", "SHIKARPUR", "DERA ISMAIL KHAN", "NOWSHERA", "PINDI GHEB", "RAHIMYARKHAN", "MANDI BAHAUDDIN", "NAWABSHAH", "SADIQABAD", "RAJANPUR", "ARIF WALA", "LAYYAH", "TOBATEK-SINGH", "JOUHARABAD", "CHICHAWATNI", "MANKERA", "PIR MAHAL", "HASSAN ABDAL", "MIRPUR KHAS", "MUZAFFARGARH", "DASKA", "WAZIRABAD", "DIGRI", "PABBI", "AHMED PUR EAST", "KAROR PAKKA");
		$list = [];

		foreach ($cities_list as $key => $value) {
			$list[] = ['value' => $value, 'label' => ucfirst($value)];
		}
		
		return $list;
 	}
}