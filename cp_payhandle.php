<?php

class Shop_Payment_System_HandlerXX extends Shop_Payment_System_Handler {
    
    /* Блок настроек модуля оплаты CloudPayments */
    // Данные продавца из личного кабинета
    private $_cp_public_id = '<ЗАПОЛНИТЕ ЭТИ ДАННЫЕ>'; // Public ID сайта
    private $_cp_api_pass = '<ЗАПОЛНИТЕ ЭТИ ДАННЫЕ>'; // Пароль для API сайта
    private $_cp_default_currency = "RUB"; // Валюта платежей
    
    // Блок настроек онлайн-кассы (ФЗ-54), подробная информация на https://cloudpayments.ru/docs/api/kassa
    private $_cp_onlinekassa_enabled = false; // Включить отправку чеков (true - да, false - нет)
    
    private $_cp_onlinekassa_taxtype = 10; /* 18 - НДС 18%, 10 - НДС 10%, null - НДС не облагается, 0 - НДС 0%, 
                                            * 110 — расчетный НДС 10/110, 118 — расчетный НДС 18/118 */
    
    private $_cp_onlinekassa_taxsystem = 0; /* 0 — Общая система налогообложения
                                                1 — Упрощенная система налогообложения (Доход)
                                                2 — Упрощенная система налогообложения (Доход минус Расход)
                                                3 — Единый налог на вмененный доход
                                                4 — Единый сельскохозяйственный налог
                                                5 — Патентная система налогообложения */
    /* Конец блока настроек модуля оплаты CloudPayments */
    
    function __construct(\Shop_Payment_System_Model $oShop_Payment_System_Model) {
        if (!function_exists('getallheaders'))  {
            function getallheaders()
            {
                if (!is_array($_SERVER)) {
                    return array();
                }
                $headers = array();
                foreach ($_SERVER as $name => $value) {
                    if (substr($name, 0, 5) == 'HTTP_') {
                        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                    }
                }
                return $headers;
            }
        }   
        parent::__construct($oShop_Payment_System_Model);
    }

    public function execute() {
        parent::execute();

        $this->printNotification();

        return $this;
    }

    protected function _processOrder() {
        parent::_processOrder();

        $this->setXSLs();

        $this->send();

        return $this;
    }

    public function paymentProcessing() {
        if (isset($_POST['TransactionId'])) {
                $this->ShowResultMessage();
                $this->ProcessResult();
                return true;
        }
        return false;
    }

    public function ShowResultMessage() {
        echo '{"code":0}';
    }

    function ProcessResult() {
        $headers = getallheaders();
        $request = file_get_contents('php://input');
	$sign = hash_hmac('sha256', $request, $this->_cp_api_pass, true);
	$hmac = base64_encode($sign);
	if (!array_key_exists('Content-HMAC',$headers) && !array_key_exists('Content-Hmac',$headers) || (array_key_exists('Content-HMAC',$headers) && $headers['Content-HMAC'] != $hmac) || (array_key_exists('Content-Hmac',$headers) && $headers['Content-Hmac'] != $hmac)) {
            die("hmac error");
	}
        
        if (!is_null($request)) {
            $oShop_Order = Core_Entity::factory('Shop_Order')->find($_POST["InvoiceId"]);

            if (is_null($oShop_Order->id) || $oShop_Order->paid) {
                die;
            }

            if ($_POST["Amount"] == $oShop_Order->getAmount()) {
                $this->shopOrder($oShop_Order)->shopOrderBeforeAction(clone $oShop_Order);

                $oShop_Order->system_information = "Товар оплачен через CloudPayments.\nТранзакция #".$_POST["TransactionId"]."\n";
                $oShop_Order->paid();
                $this->setXSLs();
                $this->send();

                $this->changedOrder('changeStatusPaid');
            }
        }
        die;
    }

    public function getNotification() {
        $oSite_Alias = $this->_shopOrder->Shop->Site->getCurrentAlias();
        $site_alias = !is_null($oSite_Alias) ? $oSite_Alias->name : '';
        $shop_path = $this->_shopOrder->Shop->Structure->getPath();
        $handler_url = (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http') . '://' . $site_alias . $shop_path . 'cart/';

        if ($this->_cp_onlinekassa_enabled) {
            $oShop_Order = Core_Entity::factory('Shop_Order', $this->_shopOrder->id);
            $aShopOrderItems = $oShop_Order->Shop_Order_Items->findAll();
            $receipt = array("cloudPayments"=>array("customerReceipt"=>array(
                'Items' => array(),
                'taxationSystem' => 0,
                'email' => $this->_shopOrder->email,
                'phone' => $this->_shopOrder->phone
            )));
            $disc = 0;
            $osum = 0;
            foreach ($aShopOrderItems as $kk => $item) {
                if ($item->price < 0) {
                    $disc -= $item->price;
                    unset($aShopOrderItems[$kk]);
                } else {
                    if ($item->shop_item_id)
                        $osum += $item->price;
                }
            }
            unset($item);
            $disc = abs($disc) / $osum;
            print_r($disc);
            foreach ($aShopOrderItems as $item) {
                $tax_id = false;
                if ($item->shop_item_id) {
                    $oShop_Item = Core_Entity::factory('Shop_Item', $item->shop_item_id);
                    $tax_id = $oShop_Item->shop_tax_id;
                }
                if (strpos('Доставка', $item->name) != true) { 
                    $receipt["cloudPayments"]["customerReceipt"]['Items'][] = array(
                        'label' => substr($item->name, 0, 128),
                        'price' => $item->getAmount(),
                        'amount' => number_format($item->getAmount() * ($item->shop_item_id ? 1 - $disc : 1), 2, '.', ''),
                        'quantity' => $item->quantity,
                        'vat' => $this->_cp_onlinekassa_taxtype,
                    );
                }
            }
        }

        $request_data = (($this->_cp_onlinekassa_enabled == 'yes') ? json_encode($receipt) : "{}");
        $fields = array(
            'publicId' => $this->_cp_public_id,
            'description' => "Оплата заказа #" . $this->_shopOrder->invoice,
            'amount' => $this->_shopOrder->getAmount(),
            'currency' => $this->_cp_default_currency,
            'invoiceId' => $this->_shopOrder->invoice,
            'accountId' => (isset($this->_shopOrder->email) ? $this->_shopOrder->email : (isset($this->_shopOrder->phone) ? $this->_shopOrder->phone : $this->_shopOrder->id)),
            'data' => $request_data,
        );

        $form = "<script src=\"https://widget.cloudpayments.ru/bundles/cloudpayments\"></script>
			<script>
				var widget = new cp.CloudPayments();
		    	widget.charge ({
                            publicId: '" . $fields["publicId"] . "',
                            description: '" . $fields["description"] . "',
                            amount: " . $fields["amount"] . ",
                            currency: '" . $fields["currency"] . "',
                            invoiceId: '" . $fields["invoiceId"] . "', 
                            accountId: '" . $fields["accountId"] . "',  
                            data: " . $fields["data"] . "  
                        },
			        function (options) { // success
						window.location.replace('" . $handler_url . "?payment=success&order_id=" . $this->_shopOrder->invoice . "');
			        },
			        function (reason, options) { // fail
						window.location.replace('" . $handler_url . "?payment=fail&order_id=" . $this->_shopOrder->invoice . "');
		        	}
		        );
			</script>";
        return $form;
    }

    public function getInvoice() {
        return $this->getNotification();
    }
}