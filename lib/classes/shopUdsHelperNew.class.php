<?php

class shopUdsHelperNew
{
    /** @var mixed настройки плагина */
    protected array $pluginSettings;
    /** @var mixed Инициализированный объект класса для работы с API UDS */
    protected shopUdsHelperApi $api;
    /** @var array настройки компании в системе UDS */
    protected array $udsCompanySettings;

    /** Конструктор */
    public function __construct()
    {
        $plugin = wa('shop')->getPlugin('uds');
        $this->pluginSettings = $plugin->getSettings();

        $this->api = new shopUdsHelperApi($this->pluginSettings['api_key'], $this->pluginSettings['company_id']);
        $this->udsCompanySettings = $this->api->settings();
    }

    /** Вспомогательная функция - Формирование ответе Success */
    protected function returnSuccess($message = 'OK', $data = null)
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];
    }

    /** Вспомогательная функция - Формирование ответе Error */
    protected function returnError($message = 'Error!', $data = null)
    {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ];
    }

    /** Базовая функция поиска Клиента UDS по идентификатору (код/телефон) и фиксации данных в хранилище сессии */
    protected function customerFind($identifier, $identifier_type)
    {
        if ($identifier_type == 'phone') {
            $identifier = str_replace("+", "%2b", $identifier);
        }

        $cart_total = shopUdsHelperCartTotal::calc();
        if (!$cart_total) {
            return $this->returnError('Server Error!');
        }

        $response = $this->api->customerFind(
            $identifier,
            $identifier_type,
            $cart_total->getTotal(),
            $cart_total->getSkipLoyaltyTotal(),
        );

        if (!$response) {
            return $this->returnError('Remote UDS Server Error!');
        }

        $not_found_message = '';
        if ($identifier_type == 'phone') {
            $not_found_message = $this->pluginSettings['not_found_message_phone'];
        } elseif ($identifier_type == 'code') {
            $not_found_message = $this->pluginSettings['not_found_message_code'];
        }

        $not_participant_message = $this->pluginSettings['not_participant_message'];

        if (isset($this->pluginSettings['not_participant_qr'])) {
            $qr_company = '/wa-data/public/shop/plugins/uds/' . $this->pluginSettings['not_participant_qr'];
            $not_participant_message = str_replace("[img-link]", $qr_company, $not_participant_message);
        }

        if ($response['status']['code'] == 404) {
            return $this->returnError($not_found_message);
        }

        if ($response['status']['code'] != 200) {
            return $this->returnError($response['status']['code'] . ': ' . $response['status']['phrase']);
        }


        $content = $response['content'];
        $is_participant = false;
        if ($content['user']['participant']['id']) {
            $is_participant = true;
        }
        waLog::dump($this->pluginSettings, 'dump.log');
        if (!$is_participant) {
            if ($this->pluginSettings['only_participants']) {
                return $this->returnError($not_participant_message);
            }
        }
        waLog::dump($content, 'dump.log');

        // полученные ответы от UDS
        $uds_user_info = [
            'uds_user_uid' => $content['user']['uid'] ?? '0',
            'uds_user_name' => $content['user']['displayName'] ?? 'No Name',
            'uds_user_points' => $content['user']['participant']['points'] ?? 0,
            'uds_user_discount_rate' => $content['user']['participant']['DiscountRate'] ?? 0,
            'uds_user_max_points' => $content['purchase']['maxPoints'] ?? 0,
            'uds_user_cashback' => $content['user']['participant']['cashbackRate'],
            'uds_user_phone' => $content['user']['phone'] ?? '',

            'uds_discount_identifier_type' => $identifier_type,
            'uds_discount_identifier' => $identifier,
        ];

        $result = [
            'user' => $uds_user_info,
            'reload' => false,
        ];

        if ($uds_user_info['uds_user_discount_rate'] > 0 && $identifier_type == 'code') {
            wa()->getStorage()->set('shop/uds/discount', $uds_user_info['uds_user_discount_rate']);
            $result['reload'] = true;
        }
        // В хранилище shop/udsdiscount/user устанавливается информация о клиенте $user_info
        wa()->getStorage()->set('shop/uds/user', $uds_user_info);

        // Возвращается массив $data_return с информацией о статусе запроса и данных о клиенте
        return $this->returnSuccess('return uds_user_info', $result);
    }

    /** Поиск клиента UDS по телефону и фиксация данных в сессии */
    public function customerFindByPhone($phone)
    {
        $phone = str_replace("+", "%2b", $phone);

        return $this->customerFind($phone, 'phone');
    }

    /** Поиск клиента UDS по коду, и фиксация данных в сессии */
    public function customerFindByCode($code)
    {
        return $this->customerFind($code, 'code');
    }

    /** Возвращает настройки плагина */
    public function getPluginSettings()
    {
        return $this->pluginSettings;
    }

    /** Возвращает настройки компниии в UDS */
    public function getUdsCompanySettings()
    {
        return $this->udsCompanySettings;
    }

    /** Cоздание покупки со списанием баллов, если по коду, или без списания, с будущим начислением через Reward */
    public function operationPurchase()
    {
        //TODO: Сделать создание покупки со списанием баллов, если по коду, или без списания, с будущим начислением через Reward
    }

    /** Отмена покупки, если заказ в магазине был отменен */
    public function operationRefund()
    {
        //TODO: Отмена покупки, если заказ в магазине был отменен
    }

    /** Вознаграждение бонусными баллами за проведение покупки в магазине */
    public function operationReward()
    {
        //TODO: Вознаграждение бонусными баллами за проведение покупки в магазине
    }



}
