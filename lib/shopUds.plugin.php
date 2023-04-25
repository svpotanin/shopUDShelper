<?php
// TODO: Тут еще "муха не еблась" - надо что-то делать
// TODO: Пересмотреть в соответствии с новыми данными из Handlers плагина
class shopUdsPlugin extends shopPlugin
{
//	const SHOP_ID = 'shop';
//	const PLUGIN_ID = 'uds';
    /** @var - Свойство хранит путь к планигу */
    protected $plugin_path;

    public function __construct($info)
    {
        parent::__construct($info);

        $this->plugin_path = wa('shop')->getPlugin('uds')->path;
    }

    public function getPath($path)
    {
        return "{$this->plugin_path}{$path}";
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
        // TODO: Переделать названия всех переменных хранилища сессий - чтобы они не пересекались своим маршрутом
        wa()->getStorage()->set('shop/udsdiscount/total_discount', '');

        $uds_discount = wa()->getStorage()->get('shop/udsdiscount');
        $uds_points = wa()->getStorage()->get('shop/udspoints');
        $uds_user = wa()->getStorage()->get('shop/udsdiscount/user');

        $uds_code = false;
        $uds_type = false;

        if ($uds_user) {
            $uds_code = $uds_user['disc_code'];
            $uds_type = $uds_user['disc_type'];
        }

        $uds_helper = new shopUdsHelperNew();

        $pass = false;

        if ($uds_code && $uds_type) {
            $check = $uds_helper->customerFind(array('code' => $uds_code, 'type' => $uds_type));
            if ($check['status'] == 'ok') {
                // check if discount from uds is not lower than storage
                $valid_discount = true;
                $valid_points = true;
                if ($uds_discount) {
                    if ($uds_discount > $check['user']['user_discount']) {
                        $valid_discount = false;
                    }
                }
                if ($uds_points) {
                    if ($uds_points > $check['user']['user_maxpoints']) {
                        $valid_points = false;
                    }
                }
                if ($valid_discount && $valid_points) {
                    $pass = true;
                } else {
                    wa()->getStorage()->set('shop/udsdiscount/user', '');
                }
            } else {
                wa()->getStorage()->set('shop/udsdiscount/user', '');
                return false;
            }
        }

        if (!$pass) {
            return;
        }

        if (!$uds_discount && !$uds_points) {
            return;
        }

        $uds_discount = $uds_discount ?? 0;
        $uds_points = $uds_points ?? 0;

        if ($uds_points > 0) {
            $add_points = $uds_points;
        } else {
            $add_points = 0;
        }

        $out_tot = 0;
        foreach ($params['order']['items'] as $item_id => $item) {
            if ($item['type'] == 'product') {
                $skus_model = new shopProductSkusModel;
                $sku = $skus_model->getSku($item['sku_id']);
                if (!($this->getSettings('ignore_compare_price') && ($sku['compare_price'] - $sku['price']) > 0)) {

                    $out_tot += shop_currency(
                            $item['price'],
                            $item['currency'],
                            $params['order']['currency'],
                            false
                        ) * $item['quantity'];
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
            $discount_math = $out_tot * $uds_discount / 100.00;
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

            wa()->getStorage()->set('shop/udsdiscount/total_discount', $out_discount['discount']);

            return $out_discount;
        }

    }

    public function orderActionCreate($params)
    {
        if (!$this->getSettings('enabled')) {
            return;
        }

        $uds_discount = wa()->getStorage()->get('shop/udsdiscount');
        $uds_points = wa()->getStorage()->get('shop/udspoints');
        $uds_user = wa()->getStorage()->get('shop/udsdiscount/user');

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

        wa()->getStorage()->set('shop/udsdiscount', '');
        wa()->getStorage()->set('shop/udspoints', '');
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


        $session['shop_udsdiscount_user'] = wa()->getStorage()->get('shop/udsdiscount/user');
        $session['shop_udsdiscount'] = wa()->getStorage()->get('shop/udsdiscount');
        $session['shop_udspoints'] = wa()->getStorage()->get('shop/udspoints');
        $session['shop_udsdiscount_total_discount'] = wa()->getStorage()->get('shop/udsdiscount/total_discount');

        if ($session['shop_udsdiscount']) {
            $view->assign('shop_udsdiscount', $session['shop_udsdiscount']);
        }

        if ($session['shop_udsdiscount_total_discount']) {
            $view->assign('shop_udsdiscount_total_discount', $session['shop_udsdiscount_total_discount']);
        }

        if ($session['shop_udsdiscount_user']) {
            $view->assign('shop_udsdiscount_user', $session['shop_udsdiscount_user']);

            if ($session['shop_udsdiscount_user']['user_cashback'] > 0) {
                $cashback_message_view = str_replace("[cashback-amount]", $session['shop_udsdiscount_user']['user_cashback'], $cashback_message);
                $view->assign('cashback_message', $cashback_message_view);
            }

        }
        if ($session['shop_udspoints']) {
            $view->assign('shop_udspoints', $session['shop_udspoints']);
        }

        return $view->fetch($this->getPath('/templates/Form.html'));
    }


}
