<?php

require 'waJsonController_UdsBase.controller.php';

class shopUdsPluginFrontendSubstractCodeController extends waJsonController_UdsBase
{

    public function execute()
    {
        // Подгружаем основной хелпер
        $uds_helper = new shopUdsHelperNew();

        // Получаем и проверяем данные из Request
        $code = waRequest::get('code');
        $points = waRequest::get('points');

        if (!$code) {
            $this->response = $this->errorResponse();
            return;
        }

        if (!isset($points) or $points <= 0) {
            $this->response = $this->errorResponse('Указано неверное количество баллов.');
            return;
        }

        // Находим пользователя через UDS по коду для списания баллов
        $find_user_for_check = $uds_helper->customerFindByCode($code);

        if ($find_user_for_check['status'] != 'success') {
            $this->response = $this->errorResponse();
            return;
        }

        // Проверяем, не превышает ли кол-во баллов максимально возможное для списания
        if ($points > $find_user_for_check['data']['user']['uds_user_max_points']) {
            $message = 'Вы пытаетесь применить больше баллов, которые у вас есть. На данный момент у вас '
                . $find_user_for_check['data']['user']['uds_user_max_points'] . ' доступные баллы.!';
            $this->response = $this->errorResponse();
            return;
        }

        wa()->getStorage()->set('shop/udspoints', $points);
        $this->response = $this->successResponse();
    }

}
