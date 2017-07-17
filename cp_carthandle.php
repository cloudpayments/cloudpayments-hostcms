// Обработка оплаты Cloudpayments
if(isset($_POST["TransactionId"])) {
	$order_id = intval($_POST["InvoiceId"]);	
	$oShop_Order = Core_Entity::factory("Shop_Order")->find($order_id);
  if (!is_null($oShop_Order->id)) {
		// Вызов обработчика платежной системы
		Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
		->shopOrder($oShop_Order)
		->paymentProcessing();
		exit();    
		}
}