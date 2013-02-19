<?php

class Zipbit_Bitcoins_PaymentController extends Mage_Core_Controller_Front_Action {

	// The redirect action is triggered when someone places an order
	public function redirectAction() {
		$this->loadLayout();
    $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','zipbit',array('template' => 'zipbit/redirect.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
    $this->renderLayout();
	}
	
	// The response action is triggered when your gateway sends back a response after processing the customer's payment
	public function responseAction() {
		$this->loadLayout();
    $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','zipbit',array('template' => 'zipbit/response.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
    $this->renderLayout();
	}
  
  public function ipnAction() {
	  
    $requestVars = array();
    foreach ($_POST as $key => $value) {
      $requestVars[$key] = $value;
    }
    $requestString = http_build_query($requestVars);
    
    //DEBUG
    $this->ipnLog("requestVars: ".print_r($requestVars, true)."\n");
    
    // post back to ZIPBIT
    $header = "POST /ipn/validate HTTP/1.0\r\n";
    $header .= "Host: zipbit.co\r\n";
    $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $header .= "Content-Length: " . strlen($requestString) . "\r\n\r\n";
    
    $host = 'ssl://zipbit.co';
    $port = 443;

    $socket = fsockopen($host, $port, $errorno, $errstr, 30);
    
    if (!$socket) {
      // HTTP ERROR connecting to ZIPBIT
      $this->ipnLog("error connecting host:port - [errorno] - [error] | $host:$port - [$errorno] - [$errstr]\n");
    } else {
      // Send the request
      fputs ($socket, $header . $requestString);
      // Check the response
      $isValid = FALSE;
      while (!feof($socket)) {
        $response = fgets ($socket, 1024);
        if (strcmp($response, 'YES') == 0) {
          $isValid = TRUE;
        } else if (strcmp($response, 'NO') == 0) {
          $isValid = FALSE;
        }
      }
      fclose($socket);
    
      $this->ipnLog("transaction came from ZIPBIT: ".($isValid ? 'YES' : 'NO')."\n");
    
      //unpack $_POST variables, payment_status, transaction_id, merchant_key, merchant_ref, currency_amount, currency_code etc.
      extract($requestVars);
    
      if ($isValid) {
        $this->ipn($payment_status, $transaction_id, $merchant_key, $merchant_ref, $currency_amount, $currency_code);
        $this->ipnLog("set paid for merchant_ref $merchant_ref | ".$transaction_id."\n");
      } else {
        // log for manual investigation
        $this->ipnLog("transaction is not valid response: $response | ".$transaction_id."\n");
      }
    }
    
    fclose($socket);	  
	  
  }

  private function ipn($payment_status, $transaction_id, $merchant_key, $merchant_ref, $currency_amount, $currency_code) {

		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($merchant_ref);
		
		// make sure the merchant keys match
		if($merchant_key != Mage::getStoreConfig('payment/zipbit/merchant_key')) {
		  return false;
		}
		
		// make sure this is the correct order
		if($merchant_ref != $order->increment_id) {
		  return false;
		}
		
		// make sure it's the correct price
		if($currency_amount != $order->grand_total) {
		  return false;
		}
		
		// make sure is the correct currency
		if($currency_code != $order->order_currency_code) {
		  return false;
    }
		
    
    if($payment_status == 'completed') {
      // ZIPBIT has received the Bitcoins from the customer, and the transaction has been confirmed by the Bitcoin network.
      // Your transaction has completed and you can process your order at this point.
      // It may take 10 minutes for your transaction to reach this status. If your order is of low value and/or easily reversed,
      // you may want to consider action based on the 'pending-confirmation' status, which is reached much faster.
      //
      // You should check:
      // - you have not already processed payment for this $transaction_id
      // - $merchant_key is your merchant key
      // - $merchant_ref if correct/relevant to your order system
      // - $currency_amount/$currency_code are correct for this order

      // dont do anything if order is already in this state
      if($order->status == Mage_Sales_Model_Order::STATE_PROCESSING) {
        return false;
      }
		  $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, "Zipbit status is 'completed'. Bitcoin payment has been received and has been confirmed. (zipbit transaction id: $transaction_id)");
			$order->sendNewOrderEmail();
			$order->setEmailSent(true);
      $order->save();
    }
    elseif($payment_status == 'pending-confirmation') {
      // ZIPBIT has received the Bitcoins from the customer, but the transaction has not been confirmed by the Bitcoin network
      // It is up to you, the merchant, to decide weather or not to process your order at this point.
      // If the item you are selling is of small value, or can easily be reversed (eg, account access) then you could consider
      // processing the order at this point. However, if the item is of high value, or hard to reverse (e.g. shipping a physical
      // product) then it would be more prudent to wait for completion.
      // More information about confirmations can be found here: https://en.bitcoin.it/wiki/FAQ#Sending_and_Receiving_Payments
      $order->addStatusHistoryComment("Zipbit status is 'pending-confirmation'. Bitcoin payment has been received but not yet confirmed. (zipbit transaction id: $transaction_id)");
      $order->save();
    }
    else {
      // something else happened
      // you should log this IPN and act accordingly
    }
  
  }
  
  
  /**
   *
   * An event within the IPN mechanism which can be logged has occurred
   *
   * @param string    $message            The message to be logged
   *
   */
  private function ipnLog($message) {
    //  the following example shows how you could append a log message to a file
    /*
    $logFile = fopen('/tmp/magento-zipbit-ipn.log', 'a');
    if ($logFile) {
      fwrite($logFile, $message);
    }
    fclose($logFile);
    */
  }	
  
}