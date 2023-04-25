<?php
// TODO: Тут еще "муха не еблась" - надо что-то делать
// TODO: Пересмотреть в соответствии с новыми данными из Handlers плагина

/**
 * Класс Плагина с обработчиками хуков и вспомогательными функциями
 */
class shopUdsPlugin extends shopPlugin
{
   public function getPath($path)
    {
        $plugin_path = wa('shop')->getPlugin('uds')->path;
        return "{$plugin_path}{$path}";
    }

    public function frontendHead()
    {
        if (!$this->getSettings('enabled')) {
            return;
        }

        $this->addCss('css/uds.css');
        $this->addJs('js/jquery.mask.js');
    }

    public function handleFrontendCart()
    {
        if (!$this->getSettings('enabled')) {
            return;
        }

        return $this->renderForm();
    }


    public function orderCalculateDiscount($params)
    {
        if (!$this->getSettings('enabled')) {
            return;
        }
        // TODO: Тут перепроверяем и переписываем полностью функцию
//		if ($this->getSettings('enabled')) {

        // validation
        // Обнулили значение в сессии TotalDiscount
        wa()->getStorage()->set('shop/uds/total_discount', '');

        // Получили из сессии значение скидки
        $session_uds_discount = wa()->getStorage()->get('shop/uds/discount');
        // Получили из сессии кол-во баллов для списания
        $session_uds_points = wa()->getStorage()->get('shop/uds/points');
        // Получили из сессии все данные о пользователе UDS и прочие данные
        $session_uds_user = wa()->getStorage()->get('shop/uds/user');

        $uds_identifier = false;
        $uds_identifier_type = false;
        if ($session_uds_user) {
            $uds_identifier = $session_uds_user['uds_discount_identifier'];
            $uds_identifier_type = $session_uds_user['uds_discount_identifier_type'];
        }

        $uds_helper = new shopUdsHelperNew();

        $pass = false;

        if (!$uds_identifier or !$uds_identifier_type) {
            wa()->getStorage()->set('shop/uds/user', '');
            return;
        }

        if ($uds_identifier_type != 'phone' and $uds_identifier_type != 'code') {
            wa()->getStorage()->set('shop/uds/user', '');
            return;
        }

//        if ($uds_identifier && $uds_identifier_type) {

        if ($uds_identifier_type == 'phone') {
            $check_uds_participant = $uds_helper->customerFindByPhone($uds_identifier);
        } else {
            $check_uds_participant = $uds_helper->customerFindByCode($uds_identifier);
        }

        if ($check_uds_participant['status'] != 'success') {
            wa()->getStorage()->set('shop/uds/user', '');
            return;
        }

        if (!$session_uds_discount && !$session_uds_points) {
            return;
        }

//        if ($check_uds_participant['status'] == 'success') {

        // Проверка на валидность скидки и баллов

        $valid_discount = true;
        $valid_points = true;

        // Если скидка есть и она больше, чем у пользователя в настройках
        if ($session_uds_discount) {
            if ($session_uds_discount > $check_uds_participant['data']['user']['uds_user_discount']) {
                $valid_discount = false;
            }
        }

        if ($session_uds_points) {
            if ($session_uds_points > $check_uds_participant['data']['user']['uds_user_max_points']) {
                $valid_points = false;
            }
        }

        if ($valid_discount AND $valid_points) {
            $pass = true;
        }

        if (!$pass) {
            wa()->getStorage()->set('shop/uds/user', '');
            return;
        }

        $uds_discount = $session_uds_discount ?? 0;
        $uds_points = $session_uds_points ?? 0;

        if ($uds_points > 0) {
            $add_points = $uds_points;
        } else {
            $add_points = 0;
        }

        $total = 0;

        foreach ($params['order']['items'] as $item_id => $item) {
            if ($item['type'] == 'product') {
                $skus_model = new shopProductSkusModel;
                $sku = $skus_model->getSku($item['sku_id']);
                if (!($this->getSettings('ignore_compare_price') && ($sku['compare_price'] - $sku['price']) > 0)) {

                    $price = shop_currency($item['price'], $item['currency'], $params['order']['currency'], false);
                    $total = $total + ( $price * $item['quantity'] );

                }
            }
        }

        if ($uds_discount > 0 && $uds_points > 0) {
            $description = 'Скидка от приложения «UDS» - Скидка: ' . $uds_discount . '%, Баллы: -' . $uds_points . ', UID пользователя: ' . $check['user']['user_uid'];
        } else if ($uds_discount > 0 && $uds_points == 0) {
            $description = 'Скидка от приложения «UDS» - Скидка: ' . $uds_discount . '%, UID пользователя: ' . $check['user']['user_uid'];
        } else if ($uds_points > 0 && $uds_discount == 0) {
            $description = 'Скидка от приложения «UDS» - Баллы: -' . $uds_points . ', UID пользователя: ' . $check['user']['user_uid'];
        }

        if ($uds_discount > 0) {
            $discount_math = $total * $uds_discount / 100.00;
        }

        $out_discount = array();

        if (isset($discount_math)) {
            if ($add_points > 0) {
                $out_discount['discount'] = $discount_math + $add_points;
            } else {
                $out_discount['discount'] = $discount_math;
            }
        } else if ($add_points > 0) {
            $out_discount['discount'] = $add_points;
        }

        if ($out_discount['discount'] > 0) {
            $out_discount['description'] = $description;

            wa()->getStorage()->set('shop/uds/total_discount', $out_discount['discount']);

            return $out_discount;
        }

    }

    public function orderActionCreate($params)
    {
        if (!$this->getSettings('enabled')) {
            return;
        }

        $uds_discount = wa()->getStorage()->get('shop/uds/discount');
        $uds_points = wa()->getStorage()->get('shop/uds/points');
        $uds_user = wa()->getStorage()->get('shop/uds/user');

        $uds_orders_model = new shopUdsOrdersModel();

        if (!$uds_user) {
            return false;
        }

        // TODO: Тут все переделываем и проверяем
//        if ($uds_user) {
        $data['order_id'] = $params['order_id'];
        $data['type'] = $uds_user['disc_type'];
        $data['code'] = $uds_user['disc_code'];
        $data['uds_uid'] = $uds_user['user_uid'];
        $data['user_phone'] = $uds_user['user_phone'];
        $data['points'] = 0;
        $data['discount'] = 0;

        if ($uds_points) {
            $data['points'] = $uds_points;
        }
        if ($uds_discount) {
            $data['discount'] = $uds_discount;
        }

        $uds_orders_model->insert($data);

        if ($data['points'] > 0) {
            $this->substractPoints($data);
        }

        wa()->getStorage()->set('shop/uds/discount', '');
        wa()->getStorage()->set('shop/uds/points', '');
//        }
    }

    public function orderActionComplete($params)
    {
        if (!$this->getSettings('enabled')) {
            return;
        }

        $uds_orders_model = new shopUdsOrdersModel();
        $uds_helper = new shopUdsHelperNew();
        $order_id = $params['order_id'];
        $find_uds_order = $uds_orders_model->getByField('order_id', $order_id);

        if (!$find_uds_order) {
            return false;
        }

        if ($find_uds_order['status'] != 0) {
            return false;
        }

//        if ($find_uds_order) {
//            if ($find_uds_order['status'] == 0) {
        //TODO: Переделать
        $uds_helper->createOperation($find_uds_order, 'paid');
//            }
//        }
        return true;
    }

    public function orderActionDelete($params)
    {
        if (!$this->getSettings('enabled')) {
            return;
        }

        $uds_orders_model = new shopUdsOrdersModel();
        $uds_helper = new shopUdsHelperNew();
        $order_id = $params['order_id'];
        $find_uds_order = $uds_orders_model->getByField('order_id', $order_id);
        $uds_order_id = $find_uds_order['id'];

        if (!$find_uds_order) {
            return false;
        }

        // TODO: Тут стоит все пересмотреть
//        if ($find_uds_order) {
        // if ($find_uds_order['status'] == 1) {
        if (!empty($find_uds_order['uds_id']) || !empty($find_uds_order['refund_uds_id'])) {
            if ($find_uds_order['refunded'] != 1) {
                $uds_helper->refundOperation($find_uds_order, $params);
            }
        }
        // }
//        }
        return true;
    }

    // --------------------------------------
    // --------------------------------------
    // TODO: Тут надо поработать
    private function substractPoints($data)
    {
        $uds_orders_model = new shopUdsOrdersModel();
        $uds_helper = new shopUdsHelperNew();

        $order_id = $data['order_id'];
        $find_uds_order = $uds_orders_model->getByField('order_id', $order_id);
        $uds_order_id = $find_uds_order['id'];

        if (!$find_uds_order) {
            return false;
        }

        if ($find_uds_order['status'] != 0) {
            return false;
        }

        if ($find_uds_order['points'] <= 0) {
            return false;
        }

        // TODO: Тут явно нужна доработка, вызываемая функция не сформирована
        $uds_helper->operationPurchase($order_id, $uds_order_id);

        return true;
    }

    private function renderForm()
    {
        $view = new waSmarty3View(wa());
        $settings = $this->getSettings();
        $cashback_message = $settings['cashback_message'];

//        $uds_helper = new shopUdsHelperNew();
//        $company_info = $uds_helper->getUdsCompanySettings();
//        $purchase_by_phone = $company_info['purchaseByPhone'];
//        $view->assign('purchase_by_phone', $purchase_by_phone);

        $session = [];


        $session['shop_uds_user'] = wa()->getStorage()->get('shop/uds/user');
        $session['shop_uds_discount'] = wa()->getStorage()->get('shop/uds/discount');
        $session['shop_uds_points'] = wa()->getStorage()->get('shop/uds/points');
        $session['shop_uds_total_discount'] = wa()->getStorage()->get('shop/uds/total_discount');

        if ($session['shop_uds_discount']) {
            $view->assign('shop_uds_discount', $session['shop_uds_discount']);
        }

        if ($session['shop_uds_total_discount']) {
            $view->assign('shop_uds_total_discount', $session['shop_uds_total_discount']);
        }

        if ($session['shop_uds_user']) {
            $view->assign('shop_uds_user', $session['shop_uds_user']);

            if ($session['shop_uds_user']['user_cashback'] > 0) {
                $cashback_message_view = str_replace("[cashback-amount]", $session['shop_uds_user']['user_cashback'], $cashback_message);
                $view->assign('cashback_message', $cashback_message_view);
            }

        }
        if ($session['shop_uds_points']) {
            $view->assign('shop_uds_points', $session['shop_uds_points']);
        }

        return $view->fetch($this->getPath('/templates/Form.html'));
    }


}
