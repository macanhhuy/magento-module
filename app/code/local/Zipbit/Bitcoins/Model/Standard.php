<?php
class Zipbit_Bitcoins_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'zipbit';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('zipbit/payment/redirect', array('_secure' => true));
	}
}
?>