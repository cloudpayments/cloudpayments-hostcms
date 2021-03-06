# cloudpayments-hostcms

Модуль позволит с легкостью добавить на ваш сайт оплату банковскими картами через платежный сервис [CloudPayments](https://cloudpayments.ru).
Порядок регистрация сайта описан в документации CloudPayments https://cloudpayments.ru/Docs/Connect


### Возможности:  
• Одностадийная схема оплаты;  
• Двухстадийная схема оплаты;  
• Выбор дизайн виджета;  
• Поддержка онлайн-касс (ФЗ-54);  
• Отправка чеков по email;  
• Отправка чеков по SMS;  
• Отдельная настройка НДС для доставки;

### Совместимость
• Модуль оплаты Сloudpayments для HostCMS 6.x+

### Установка модуля

В меню «**Контент**» => «**Интернет-магазины**» выбираем магазин к которому необходимо подключить модуль. В верхнем горизонтальном меню переходим в раздел «**Справочники**» =-> «**Платежные системы**». В меню «Платежная система» выбираем пункт «**Добавить**». В поле «**Название**» вписываем название платежной системы «**Оплата картами Visa, MasterCard, МИР (CloudPayments)**», поле «**Описание**» заполняем произвольно, жмем «**Применить**».

![Скрин 1](http://i.imgur.com/xkS9oS4.png)

Находясь в меню «**Справочник платежных систем**» напротив пункта «**Оплата картами Visa, MasterCard, МИР (CloudPayments)**» необходимо нажать кнопку «**Редактировать**» (карандашик). В окне редактирования информации о платежной системе, во вкладке «**Дополнительные**» — запоминаем значение свойства «**Идентификатор**» (*например, 50*).

Переходим в окно редактирования информации о платежной (**вкладка «Основные»**). Поставьте галочку напротив пункта «**Активность**». В поле «Обработчик» нужно скопировать код из файла cp_payhandle.php (https://github.com/cloudpayments/cloudpayments-hostcms/blob/master/cp_payhandle.php).

Далее необходимо выполнить следующее:

а) в строке «**class Shop_Payment_System_HandlerXX extends Shop_Payment_System_Handler**» вместо символов XX необходимо указать идентификатор (из вкладки «**Дополнительные**»). В результате получится подобная строка: «**class Shop_Payment_System_Handler27 extends Shop_Payment_System_Handler**»;

б) указать свои данные в указанном ниже конфигурационном блоке кода:
```php
    /* Блок настроек модуля оплаты CloudPayments */
    // Данные продавца из личного кабинета
    private $_cp_public_id = '<ЗАПОЛНИТЕ ЭТИ ДАННЫЕ>'; // Public ID сайта
    private $_cp_api_pass = '<ЗАПОЛНИТЕ ЭТИ ДАННЫЕ>'; // Пароль для API сайта
    private $_cp_default_currency = "RUB"; // Валюта платежей
    private $_cp_skin = "classic"; //дизайн виджета возможные значения - classic, modern, mini 
    private $_cp_payment_scheme = "auth"; //схема проведения платежа возможные значения на https://developers.cloudpayments.ru/#shemy-provedeniya-platezha и https://developers.cloudpayments.ru/#parametry
    private $_cp_language = "ru-RU"; // локализация виджета возможные значения на https://developers.cloudpayments.ru/#lokalizatsiya-vidzheta
    
    // Блок настроек онлайн-кассы (ФЗ-54), подробная информация на https://cloudpayments.ru/docs/api/kassa
    private $_cp_onlinekassa_enabled = true; // Включить отправку чеков (true - да, false - нет)
    
    private $_cp_onlinekassa_taxtype = 20; /* 20 - НДС 20%, 10 - НДС 10%, null - НДС не облагается, 0 - НДС 0%, 
                                            * 110 — расчетный НДС 10/110, 120 — расчетный НДС 20/120 */
    
    private $_cp_onlinekassa_taxsystem = 0; /* 0 — Общая система налогообложения
                                                1 — Упрощенная система налогообложения (Доход)
                                                2 — Упрощенная система налогообложения (Доход минус Расход)
                                                3 — Единый налог на вмененный доход
                                                4 — Единый сельскохозяйственный налог
                                                5 — Патентная система налогообложения */
    /* Конец блока настроек модуля оплаты CloudPayments */
```

![Скрин 2](http://i.imgur.com/yQAInMa.png)
    
в) нажать кнопку «**Применить**». Окно «**Справочник платежных систем**» теперь можно закрыть;
    
г) зайти в меню «**Структура сайта**» => «**Типовые динамические страницы**» и открываем папку «**Интернет-магазин**». Далее нажимаем кнопку «**Редактировать**» (карандашик) напротив пункта «**Интернет-магазин корзина**». В открывшемся окне редактирования типовой динамической страницы переходим на вкладку «**Настройки страницы**». В поле «**Настройки типовой динамической страницы**» (перед строкой «**// Добавление товара в корзину**») необходимо вставить следующий код обработчика платежей (https://github.com/cloudpayments/cloudpayments-hostcms/blob/master/cp_carthandle.php):
```php
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
```

![Скрин 3](http://i.imgur.com/w0vAZxI.png)

д) необходимо нажать кнопку «**Применить**». Окно «Список типовых динамических страниц» теперь можно закрыть, настройка модуля завершена.

### Личный кабинет CloudPayments

В личном кабинете CloudPayments в настройках сайта необходимо включить следующие уведомления:

* **Запрос на проверку платежа** (Сheck):\
http://domain.ru/shop/cart/?action=check
* **Уведомление о принятом платеже** (Pay):\
http://domain.ru/shop/cart/?action=pay
* **Уведомление о подтверждении платежа** (Сonfirm):\
http://domain.ru/shop/cart/?action=confirm
* **Уведомление об отменене платежа** (Сancel):\
http://domain.ru/shop/cart/?action=cancel
* **Уведомление о возврате платежа** (Refund):\
http://domain.ru/shop/cart/?action=refund

Где domain.ru — доменное имя вашего сайта.
Во всех случаях требуется выбирать вариант по умолчанию: кодировка — UTF-8, HTTP-метод — POST, формат — CloudPayments
Данные URL можно скопировать из настройки модуля CloudPayments в панели администрирования

#### Changelog

= 1.1 =
* добавление двухстадийной схемы оплаты;  
* добавление выбора дизайна виджета;
* добавление выбора локализации виджета;  
* правка значений ставок НДС;

= 1.0 =
* Публикация модуля.