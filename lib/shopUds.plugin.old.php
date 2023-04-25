<?php

class shopUdsPlugin extends shopPlugin
{
	const SHOP_ID = 'shop';
	const PLUGIN_ID = 'uds';

	public function frontendHead()
	{
		$settings = $this->getSettings();
		if (!$settings['enabled']) {
			return;
		}
		$this->addCss('css/uds.css');
		$this->addJs('js/jquery.mask.js');
	}

	public function orderCalculateDiscount($params)
	{
		if ($this->getSettings('enabled')) {

			// validation

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

			$uds_helper = new shopUdsHelper();
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

			if ($pass) {
				if ($uds_discount || $uds_points) {
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
							$sku        = $skus_model->getSku($item['sku_id']);
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
			}
		}
	}

	public function orderActionCreate($params)
	{
		$uds_discount = wa()->getStorage()->get('shop/udsdiscount');
		$uds_points = wa()->getStorage()->get('shop/udspoints');
		$uds_user = wa()->getStorage()->get('shop/udsdiscount/user');

		$uds_orders_model = new shopUdsOrdersModel();

		if ($uds_user) {
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
		}
	}

	private function substractPoints($data)
	{
		$uds_orders_model = new shopUdsOrdersModel();
		$uds_helper = new shopUdsHelper();
		$order_id = $data['order_id'];
		$find_uds_order = $uds_orders_model->getByField('order_id', $order_id);
		if ($find_uds_order) {
			if ($find_uds_order['status'] == 0) {
				if ($find_uds_order['points'] > 0) {
					$uds_helper->createOperation($find_uds_order, 'substract');
				}
			}
		}
	}

	public function orderActionPay($params)
	{
		$uds_orders_model = new shopUdsOrdersModel();
		$uds_helper = new shopUdsHelper();
		$order_id = $params['order_id'];
		$find_uds_order = $uds_orders_model->getByField('order_id', $order_id);
		if ($find_uds_order) {
			if ($find_uds_order['status'] == 0) {
				$uds_helper->createOperation($find_uds_order, 'paid');
			}
		}
	}

	public function orderActionRefund($params)
	{
		$uds_orders_model = new shopUdsOrdersModel();
		$uds_helper = new shopUdsHelper();
		$order_id = $params['order_id'];
		$find_uds_order = $uds_orders_model->getByField('order_id', $order_id);
		if ($find_uds_order) {
			// if ($find_uds_order['status'] == 1) {
				if(!empty($find_uds_order['uds_id']) || !empty($find_uds_order['refund_uds_id'])){
					if($find_uds_order['refunded'] != 1){
						$uds_helper->refundOperation($find_uds_order, $params);
					}
				}
			// }
		}
	}

	public function handleFrontendCart()
	{
		$settings = $this->getSettings();
		if (!$settings['enabled']) {
			return;
		}
		return $this->renderForm();
	}

	private function renderForm()
	{
		$view = new waSmarty3View(wa());
		$settings = $this->getSettings();
		$cashback_message = $settings['cashback_message'];
		$uds_helper = new shopUdsHelper();

		$company_info = $uds_helper->getCompanyInfo();
		$purchase_by_phone = $company_info['purchaseByPhone'];
		$view->assign('purchase_by_phone', $purchase_by_phone);

		$old_user_info = wa()->getStorage()->get('shop/udsdiscount/user');
		$udsdiscount = wa()->getStorage()->get('shop/udsdiscount');
		$udspoints = wa()->getStorage()->get('shop/udspoints');
		$uds_total_discount = wa()->getStorage()->get('shop/udsdiscount/total_discount');

		if ($udsdiscount) {
			$view->assign('uds_discount', $udsdiscount);
		}
		if ($uds_total_discount) {
			$view->assign('uds_total_discount', $uds_total_discount);
		}
		if ($old_user_info) {
			$view->assign('uds_old_user_info', $old_user_info);
			if ($old_user_info['user_cashback'] > 0) {
				$cashback_message_view = str_replace("[cashback-amount]", $old_user_info['user_cashback'], $cashback_message);
				$view->assign('cashback_message', $cashback_message_view);
			}
		}
		if ($udspoints) {
			$view->assign('uds_points', $udspoints);
		}

		return $view->fetch($this->getPath('/templates/Form.html'));
	}

	public static function getPath($path)
	{
		/** @var shopBuy1clickPlugin $plugin */
		$plugin = wa(self::SHOP_ID)->getPlugin(self::PLUGIN_ID);

		return "{$plugin->path}{$path}";
	}
}
