<?php

require wa()->getAppPath('plugins/uds/lib/vendors/vendor/autoload.php',  'shop');

use GuzzleHttp\Client;

class shopUdsHelper
{
    protected $baseUrl = 'https://api.uds.app/partner/v2';
// запрос данных пользователя
    public function customerFind($requestData)
    {
        // Получение настроек плагина с помощью функции getSettings
        $settings = $this->getSettings();
        // Создание переменной $find_type, которая будет содержать тип и значение искомого параметра
        $find_type = '';

        // Определение типа искомого параметра на основе данных, переданных в запросе, и формирование строки $find_type в соответствии с выбранным типом
        if ($requestData['type'] == 'phone') {
            $e164_format = str_replace("+", "%2b", $requestData['code']);
            $find_type = 'phone=' . $e164_format;

        } else if ($requestData['type'] == 'code') {
            $find_type = 'code=' . $requestData['code'];
   
        } else if ($requestData['type'] == 'uid') {
            $find_type = 'uid=' . $requestData['code'];
        }

        // Получение общей стоимости товаров в корзине с помощью функции getCartTotal
        $cart_total = $this->getCartTotal();

        // Если общая стоимость товаров в корзине не получена, возвращается массив с сообщением об ошибке
        if (!$cart_total) {
            return array('status' => 'error', 'message' => 'Server Error!');
        }

        // Если бонусы лояльности доступны, создается строка $skip_loyalty, содержащая значение бонусов
        $skip_loyalty = '';
        if ($cart_total['loyalty'] > 0) {
            $skip_loyalty = '&skipLoyaltyTotal=' . $cart_total['loyalty'];
        }

        // Выполнение GET-запроса, который возвращает объект ResponseInterface с данными о клиенте. Запрос включает в себя данные о типе искомого параметра, общей стоимости товаров в корзине и, если доступны, бонусах лояльности
        $response = $this->getHttpClient($this->baseUrl . '/customers/find?' . $find_type . '&total=' . $cart_total['total'] . $skip_loyalty);

        // waLog::dump($this->baseUrl . '/customers/find?' . $find_type . '&total=' . $cart_total['total'] . '&skipLoyaltyTotal=' . $cart_total['loyalty'],'dump.log');
        

        $status_code = $response->getStatusCode();
        // Получение фразы состояния ответа HTTP с помощью метода getReasonPhrase
        $status_phrase = $response->getReasonPhrase();
        // Получение сообщений об ошибках из настроек плагина
        $not_found_message_code = $settings['not_found_message_code'];
        $not_found_message_phone = $settings['not_found_message_phone'];
        $not_participant_message = $settings['not_participant_message'];
        // Если в настройках плагина UDS указана ссылка на изображение QR-кода, то заменяем в сообщении об ошибке [img-link] на ссылку на изображение QR-кода
        if (isset($settings['not_participant_qr'])) {
            $qr_company = '/wa-data/public/shop/plugins/uds/' . $settings['not_participant_qr'];
            $not_participant_message = str_replace("[img-link]", $qr_company, $not_participant_message);
        }

        // Данный код содержит блок условий, который обрабатывает ответ от сервера UDS на запрос поиска клиента. Если код ответа - 404, то в зависимости от типа искомого параметра (code, phone или uid) возвращается соответствующее сообщение об ошибке. Если код ответа - 200, то полученные данные о клиенте распаковываются из JSON-формата и сохраняются в массиве $user_info. Далее, в зависимости от настроек плагина UDS, осуществляется проверка, является ли клиент участником программы лояльности. Если клиент не является участником, и настройки плагина требуют, чтобы участники программы получали скидки на товары, возвращается сообщение об ошибке
        if ($status_code == 404) {
            if ($requestData['type'] == 'code') {
                return array('status' => 'error', 'message' => $not_found_message_code);
            } else if ($requestData['type'] == 'phone') {
                return array('status' => 'error', 'message' => $not_found_message_phone);
            } else if ($requestData['type'] == 'uid') {
                return array('status' => 'error', 'message' => 'UID не найден.');
            }
        } else if ($status_code == 200) {
            $content = json_decode($response->getBody()->getContents(), true);
            $is_participant = false;
            if ($content['user']['participant']['id']) {
                $is_participant = true;
            }
            waLog::dump($settings, 'dump.log');
            if (!$is_participant) {
                if ($settings['only_participants']) {
                    return array('status' => 'error', 'message' => $not_participant_message);
                }
            }

            waLog::dump($content, 'dump.log');

            // полученные ответы от UDS
            $user_name = $content['user']['displayName'] ?? 'No Name';
            $user_points = $content['user']['participant']['points'] ?? 0;
            $user_discount = $content['user']['participant']['DiscountRate'] ?? 0;
            $user_maxpoints = $content['purchase']['maxPoints'] ?? 0;
            $user_phone = $content['user']['phone'] ?? 0;
            $user_cashback = $content['user']['participant']['cashbackRate'];
            $user_uid = $content['user']['uid'] ?? '0';
            $user_info = array(
                'user_uid' => $user_uid,
                'user_name' => $user_name,
                'user_points' => $user_points,
                'user_discount' => $user_discount,
                'user_maxpoints' => $user_maxpoints,
                'user_cashback' => $user_cashback,
                'user_phone' => $user_phone,
                'disc_type' => $requestData['type'],
                'disc_code' => $requestData['code'],
            );

            // Add Discount to the cart 

            // Возвращается массив $data_return, содержащий информацию о статусе запроса ('status' => 'ok') и данные о клиенте ('user' => $user_info). 
            $data_return = array(
                'status' => 'ok',
                'user' => $user_info,
            );
            // Если клиент является участником программы лояльности и использует промокод, то в хранилище shop/udsdiscount устанавливается значение скидки $user_discount, а в массив $data_return добавляется ключ 'reload' => true, сигнализирующий о необходимости перезагрузки страницы после применения скидки
            if ($user_discount > 0 && $requestData['type'] == 'code') {
                wa()->getStorage()->set('shop/udsdiscount', $user_discount);
                $data_return['reload'] = true;
            }
            // В хранилище shop/udsdiscount/user устанавливается информация о клиенте $user_info
            wa()->getStorage()->set('shop/udsdiscount/user', $user_info);

            // Возвращается массив $data_return с информацией о статусе запроса и данных о клиенте
            return $data_return;
        } else {
            // Если произошла ошибка при выполнении запроса, возвращается массив с информацией об ошибке ('status' => 'error'), а также кодом и описанием ошибки
            return array('status' => 'error', 'message' => $status_code . ': ' . $status_phrase);
        }
    }
    // запрос данных о компании в UDS
    public function getCompanyInfo()
    {
        $date = new DateTime();
        $url = $this->baseUrl . "/settings";
        $settings = $this->getSettings();
        if (!isset($settings['company_id']) && !isset($settings['api_key'])) {
            return false;
        }

        $client = new Client();

        $response = $client->request('GET', $url, [
            'auth' => [
                $settings['company_id'],
                $settings['api_key']
            ],
            'headers' => [
                'Accept-Charset' => 'utf-8',
                'Content-type' => 'application/json',
                'X-Timestamp' => $date->format(DateTime::ATOM),
            ],
            'http_errors' => false,
        ]);

        $response_body = json_decode($response->getBody()->getContents(), true);
        return $response_body;
    }

    
// ################################################################################################  
// ################################################################################################  
// ################################################################################################  
    
    // Функция createOperation() принимает данные о заказе и действии, которое будет выполнено.
    public function createOperation($data, $action)
    {
        // получаем настройки текущего плагина.
        $settings = $this->getSettings();
        // создаем объект shopOrderLogModel() для сохранения логов заказов
        $orderLog = new shopOrderLogModel();
        // создаем объект shopUdsOrdersModel() для взаимодействия с базой данных, содержащей информацию о заказах UDS
        $uds_orders_model = new shopUdsOrdersModel();
        // Создаем объект shopOrder() для доступа к данным о заказе
        $order = new shopOrder($data['order_id']);
        // Создаем объект DateTime() для работы с датой и временем
        $date = new DateTime();
        // формируем URL-адрес для запроса к API UDS
        $url = $this->baseUrl . "/operations";

        // Если не указаны параметры аутентификации в настройках плагина, функция возвращает false
        if (!isset($settings['company_id']) && !isset($settings['api_key'])) {
            return false;
        }
        
        // Вызываем метод getOrderTotal(), чтобы получить общую стоимость заказа. Метод принимает идентификатор заказа, общую стоимость заказа, данные о заказе и действии. В итоге мы получаем массив с данными о стоимости заказа
        $order_totals = $this->getOrderTotal($data['order_id'], $order->data['total'], $data, $action);

        // Set request body

        // формируем массив $postData, который будет отправлен в качестве тела запроса. Массив содержит информацию о кассире и описании чека. Данные о стоимости заказа и использованных бонусах лояльности также добавляются в этот массив
        $postData = array(
            // 'nonce' => $this->generateRandomString(),
            'cashier' => array(
                'externalId' => '0',
                'name' => 'Webasyst'
            ),

            'receipt' => array(
                'total' => number_format((float)$order_totals['total'], 2, '.', ''),
                'cash' => number_format((float)$order_totals['cash'], 2, '.', ''),
                'points' => number_format((float)$data['points'], 2, '.', ''),
                'number' => $data['order_id'],
                // добавленная строка для неначисления на сумму оплаты - баллы не начислились, сумма оплаты пришла в UDS
                // 'skipLoyaltyTotal' => number_format((float)$order_totals['cash'], 2, '.', ''),
            )
        );
        // 
      //  $postData = json_encode(
      //    array(
      //      'comment' => 'string',
     //       'points' => 50.0,
     //       'participants' => array(1, 3, 21),
     //       'silent' => false
     //     )
    //    );

        // type
        // В этом коде мы формируем массив $postData, который будет отправлен в качестве тела запроса при взаимодействии с UDS API. В зависимости от действия (начисление или вычитание бонусов лояльности) мы добавляем в массив $postData различные данные, такие как код промокода или номер телефона клиента, и указываем количество использованных бонусов лояльности. Затем мы преобразуем массив $postData в формат JSON.
 

        // Если действие "substract" (вычитание бонусов лояльности)
        if ($action == 'substract') {
            // Если тип кода - код промокода, добавляем его в массив $postData
            if ($data['type'] == 'code') {
                $postData[$data['type']] = $data['code'];
                
            // Если тип кода - номер телефона, добавляем его в массив $postData
            } else if ($data['type'] == 'phone') {
                $postData['participant']['phone'] = $data['code'];
            }
        // Если действие не "substract" (например, начисление бонусов лояльности)    
        } else {
            $phone = false;
            // Если тип кода - номер телефона, добавляем его в массив $postData
            if ($data['type'] == 'phone') {
                $postData['phone'] = $data['code'];
                $phone = true;
            } else {
                // Если у пользователя есть номер телефона, добавляем его в массив $postData
                if($data['user_phone']){
                $postData['phone'] = $data['user_phone'];
                $phone = true;
                // Иначе добавляем идентификатор пользователя UDS в массив $postData    
                } else {
              //  $postData['uds'] = $data['uds_uid'];
                }
            }
            // Добавляем идентификатор пользователя UDS в массив $postData
            $postData['participant']['uid'] = $data['uds_uid'];
            // Если номер телефона был добавлен в массив $postData, добавляем его еще раз в массив $postData
            if($phone){
            $postData['participant']['phone'] = $postData['phone'];
            }
        }

        // Если действие "substract" (вычитание бонусов лояльности), указываем в массиве $postData, что нужно игнорировать количество бонусов лояльности, которые будут использованы на оплату товаров
        if ($action == 'substract') {
            $postData['receipt']['skipLoyaltyTotal'] = number_format((float)$order_totals['total'], 2, '.', '');
        
        // Если действие не "substract" (например, начисление бонусов лояльности), проверяем, были ли использованы бонусы лояльности на оплату товаров. Если да, указываем в массиве $postData, что нужно игнорировать количество использованных бонусов лояльности    
        } else {
            if ($order_totals['loyalty'] > 0) {
                $postData['receipt']['skipLoyaltyTotal'] = number_format((float)$order_totals['loyalty'], 2, '.', '');
            }
        }

        // Если бонусы лояльности были вычтены из счета, указываем в массиве $postData, что количество бонусов лояльности равно 0
        if ($data['substracted'] == 1) {
            $postData['receipt']['points'] = 0;
        }
        
        // Преобразуем массив $postData в формат JSON
        $postData = json_encode($postData);

        // Create a stream
        
        // Создается объект класса Client - $client = new Client();
        $client = new Client();

        // Выполняется POST-запрос на определенный URL-адрес ($url), используя заданные параметры в массиве
        $response = $client->request('POST', $url, [
            'auth' => [
                $settings['company_id'],
                $settings['api_key']
            ],
            'headers' => [
                'Accept-Charset' => 'utf-8',
                'Content-type' => 'application/json',
                'X-Timestamp' => $date->format(DateTime::ATOM),
            ],
            'body' => $postData,
            'http_errors' => false,
        ]);
        
        // Результат ответа на запрос декодируется из формата JSON в массив PHP
        $response_body = json_decode($response->getBody()->getContents(), true);

        waLog::dump($response_body, 'dump.log');

        // Если в ответе содержится ошибка, то информация об ошибке добавляется в логи заказа.
        if (isset($response_body['errorCode'])) {
            // Add to order logs
            $data_log = array(
                'order_id' => $data['order_id'],
                'action_id' => '',
                'datetime' => date('Y-m-d H:i:s'),
                'before_state_id' => '',
                'after_state_id' => '',
                'text' => '<code style="color: red;">UDS - ' . print_r($response_body, true) . '</code>',
            );
            $orderLog->insert($data_log);
        // Если в ответе нет ошибки, то полученный идентификатор используется для обновления данных в модели заказов. Обновление происходит в зависимости от переданного в запросе параметра $action.    
        } else {
            $purchase_id = $response_body['id'];
            if ($action == 'paid') {
                $uds_orders_model->updateById($data['id'], array('status' => 1, 'uds_id' => $purchase_id));
            } else if ($action == 'substract') {
                $uds_orders_model->updateById($data['id'], array('substracted' => 1, 'refund_uds_id' => $purchase_id));
            }
        }
    }
    
// ################################################################################################  
// ################################################################################################  
// ################################################################################################ 
    
    // операция возврата
    public function refundOperation($data, $params)
    {
        $action_id = $params['action_id'];
        $settings = $this->getSettings();
        $uds_id = $data['uds_id'];
        $refund_uds_id = $data['refund_uds_id'];
        $orderLog = new shopOrderLogModel();
        $order_model = new shopOrderModel();
        $uds_orders_model = new shopUdsOrdersModel();
        $url = $this->baseUrl . '/operations/' . $uds_id . '/refund';
        $url_refund = $this->baseUrl . '/operations/' . $refund_uds_id . '/refund';
        $date = new DateTime();

        if (isset($params['params']['refund_amount'])) {
            $refundAmount = $params['params']['refund_amount'];
        }

        $postData = array();
        if (isset($refundAmount)) {
            $postData['partialAmount'] = $refundAmount;
        }
        $postData = json_encode($postData);

        $client = new Client();

        if ($uds_id) {
            $response = $client->request('POST', $url, [
                'auth' => [
                    $settings['company_id'],
                    $settings['api_key']
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Charset' => 'utf-8',
                    'Content-type' => 'application/json',
                    'X-Timestamp' => $date->format(DateTime::ATOM),
                ],
                'body' => $postData,
                'http_errors' => false,
            ]);


            $response_body = json_decode($response->getBody()->getContents(), true);

            if (isset($response_body['errorCode'])) {
                // Add to order logs
                $data_log = array(
                    'order_id' => $params['order_id'],
                    'action_id' => $params['action_id'],
                    'datetime' => date('Y-m-d H:i:s'),
                    'before_state_id' => $params['before_state_id'],
                    'after_state_id' => $params['after_state_id'],
                    'text' => '<code style="color: red;">UDS - ' . print_r($response_body, true) . '</code>',
                );
                $orderLog->insert($data_log);
            } else {
                $data_log = array(
                    'order_id' => $params['order_id'],
                    'action_id' => $params['action_id'],
                    'datetime' => date('Y-m-d H:i:s'),
                    'before_state_id' => $params['before_state_id'],
                    'after_state_id' => $params['after_state_id'],
                    'text' => '<code style="color: green;">UDS - Возврат был успешно произведен для Транзакции: ' . $data['uds_id'] . '</code>',
                );
                $orderLog->insert($data_log);
                $uds_orders_model->updateById($data['id'], array('refunded' => 1));
            }
        }

        if ($data['substracted'] == 1 && $refund_uds_id) {
            $response_refund = $client->request('POST', $url_refund, [
                'auth' => [
                    $settings['company_id'],
                    $settings['api_key']
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Charset' => 'utf-8',
                    'Content-type' => 'application/json',
                    'X-Timestamp' => $date->format(DateTime::ATOM),
                ],
                'http_errors' => false,
            ]);
            $response_body_refund = json_decode($response_refund->getBody()->getContents(), true);

            if (isset($response_body_refund['errorCode'])) {
                // Add to order logs
                $data_log = array(
                    'order_id' => $params['order_id'],
                    'action_id' => $params['action_id'],
                    'datetime' => date('Y-m-d H:i:s'),
                    'before_state_id' => $params['before_state_id'],
                    'after_state_id' => $params['after_state_id'],
                    'text' => '<code style="color: red;">UDS - ' . print_r($response_body_refund, true) . '</code>',
                );
                $orderLog->insert($data_log);
            } else {
                $data_log = array(
                    'order_id' => $params['order_id'],
                    'action_id' => $params['action_id'],
                    'datetime' => date('Y-m-d H:i:s'),
                    'before_state_id' => $params['before_state_id'],
                    'after_state_id' => $params['after_state_id'],
                    'text' => '<code style="color: green;">UDS - Возврат был успешно произведен для Транзакции: ' . $data['refund_uds_id'] . '</code>',
                );
                $orderLog->insert($data_log);
                $uds_orders_model->updateById($data['id'], array('refunded' => 1));
            }
        }
    }

    
    protected function getHttpClient($link)
    {
        // Получение настроек плагина UDS с помощью метода getSettings
        $settings = $this->getSettings();
        // Проверка наличия company_id и api_key в настройках плагина. Если они отсутствуют, функция возвращает false
        if (!isset($settings['company_id']) && !isset($settings['api_key'])) {
            return false;
        }
        // Создание экземпляра класса GuzzleHttp\Client для отправки HTTP-запросов
        $client = new Client();
        // Отправка GET-запроса по указанной ссылке $link с использованием аутентификационных данных company_id и api_key, переданных в массиве параметров
        $response = $client->request('GET', $link, [
            'auth' => [
                $settings['company_id'],
                $settings['api_key']
            ],
            'http_errors' => false,
            'headers' => ['Content-type' => 'application/json']
        ]);

        // Возврат объекта Response с результатом выполнения запроса
        return $response;
    }

    // Запрет на списание или начисление баллов при наличии в дополнительных настройках товара определенных значений
    public function getCartTotal()
    {
        // Создаем экземпляры классов shopCart и shopProductParamsModel
        $cart = new shopCart();
        $shopProductParamsModel = new shopProductParamsModel();
        // получает информацию о товарах, добавленных в корзину, с помощью метода items() класса shopCart
        $items = $cart->items();
        //вычисляет общую стоимость товаров в корзине с помощью метода total(), который принимает один аргумент — флаг, указывающий на необходимость учета скидок.
        $cart_total = $cart->total(false);
        // устанавливает флаг skip_loyalty в значение 0
        $skip_loyalty = 0;
        // проверяет, есть ли в корзине товары. Если корзина пуста, функция возвращает false
        if ($items) {
            // перебирает каждый товар в корзине и получает его параметры с помощью метода get() класса shopProductParamsModel
            foreach ($items as $item) {
                $p_id = $item['product_id'];
                $params = $shopProductParamsModel->get($p_id);
                if (!empty($params)) {
                    // перебирает каждый параметр товара и проверяет, есть ли у товара определенный параметр
                    foreach ($params as $pa_name => $param) {
                        // если у товара есть параметр udsnobonus со значением 1. Если есть, функция вычитает стоимость товара из общей стоимости и на товар не начисляются бонусы
                        if ($pa_name == 'udsnobonus') {
                            if ($param == '1') {
                                $itm_price = $item['quantity'] * $item['price'];
                                $cart_total = -$itm_price;
                            }
                        }
                        // если у товара есть параметр udsloyaltyskip со значением 1, функция устанавливает флаг skip_loyalty в значение, равное стоимости товара, т.е. сумма оплаты за этот товар не учавствует в бонусной системе полностью
                        if ($pa_name == 'udsloyaltyskip') {
                            if ($param == '1') {
                                $itm_price = $item['quantity'] * $item['price'];
                                $skip_loyalty = +$itm_price;
                            }
                        }
                    }
                }
            }
        } else {
            return false;
        }
        // функция проверяет, была ли общая стоимость товаров в корзине отрицательной. Если да, она устанавливает общую стоимость в 0
        if ($cart_total < 0) {
            $cart_total = 0;
        }
        // функция возвращает массив с общей стоимостью товаров в корзине и суммой товаров, за которые не начисляются бонусы лояльности
        return array('total' => $cart_total, 'loyalty' => $skip_loyalty);
    }

    public function getOrderTotal($order_id, $ord_total, $data, $action)
    {
        // создают экземпляры моделей shopOrderItemsModel и shopProductParamsModel
        $shopOrderItemsModel = new shopOrderItemsModel();
        $shopProductParamsModel = new shopProductParamsModel();
        // из модели shopOrderItemsModel извлекаются все товары, относящиеся к данному заказу
        $items = $shopOrderItemsModel->getItems($order_id);
        
        // вычисляется общая стоимость заказа с учетом возможных скидок и начисления бонусов лояльности
        if ($data['discount'] > 0 && $data['points'] > 0) {
            $order_total = ($ord_total * ($data['discount'] / 100)) + $data['points'];
        } else if ($data['discount'] > 0 && $data['points'] == 0) {
            $order_total = $ord_total * ($data['discount'] / 100);
        } else if ($data['discount'] == 0 && $data['points'] > 0) {
            $order_total = $ord_total + $data['points'];
        } else {
            $order_total = $ord_total;
        }

        // обход всех товаров в заказе и проверка наличия специальных параметров товара, которые могут влиять на общую стоимость заказа и начисление бонусов лояльности
        $skip_loyalty = 0;
        if ($items) {
            foreach ($items as $item) {
                $p_id = $item['product_id'];
                $params = $shopProductParamsModel->get($p_id);
                if (!empty($params)) {
                    foreach ($params as $pa_name => $param) {
                        if ($pa_name == 'udsnobonus') {
                            if ($param == '1') {
                                $itm_price = $item['quantity'] * $item['price'];
                                $order_total = -$itm_price;
                            }
                        }
                        if ($pa_name == 'udsloyaltyskip') {
                            if ($param == '1') {
                                $itm_price = $item['quantity'] * $item['price'];
                                $skip_loyalty = +$itm_price;
                            }
                        }
                    }
                }
            }
        } else {
            return false;
        }

        // вычисляется сумма, которую необходимо оплатить или вернуть клиенту в зависимости от того, происходит ли изменение общей стоимости заказа или его части в связи с выполнением каких-либо действий
        $order_cash = 0;
        if ($order_total < 0) {
            $order_total = 0;
        } else {
            if ($data['discount'] > 0 && $data['points'] > 0) {
                $order_cash = ($order_total * ((100 - $data['discount']) / 100)) - $data['points'];
            } else if ($data['discount'] > 0 && $data['points'] == 0) {
                $order_cash = $order_total * ((100 - $data['discount']) / 100);
            } else if ($data['discount'] == 0 && $data['points'] > 0) {
                $order_cash = $order_total - $data['points'];
            } else {
                $order_cash = $order_total;
            }
        }

        if ($action != 'substract') {
            $order_total = $order_cash;
        }

        // функция возвращает массив, содержащий общую стоимость заказа, сумму бонусов лояльности и сумму денег, которую необходимо оплатить или вернуть клиенту
        return array('total' => $order_total, 'loyalty' => $skip_loyalty, 'cash' => $order_cash);
    }

    // функция getSettings() используется для получения настроек плагина Универсальная доставка и самовывоз (UDS) в интернет-магазине, использующем платформу Webasyst
    private function getSettings()
    {
        // Определение метода getSettings
        $plugin = wa('shop')->getPlugin('uds');
        // Получение экземпляра плагина UDS с помощью метода getPlugin
        $settings = $plugin->getSettings();
        // Получение настроек плагина UDS с помощью метода getSettings
        return $settings;
    }
}
