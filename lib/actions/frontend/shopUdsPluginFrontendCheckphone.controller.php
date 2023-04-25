<?php

require 'waJsonController_UdsBase.controller.php';

class shopUdsPluginFrontendCheckPhoneController extends waJsonController_UdsBase
{

    public function execute()
    {
        $uds_helper = new shopUdsHelperNew();

        // Достаем данные из Request
        $phone = waRequest::get('phone');

        if (!$phone) {
            $this->response = $this->errorResponse();
            return;
        }

        // Чистим сессию
        $this->setClearSession();

        // Находим клиента и пишем данные в сессию
        $result = $uds_helper->customerFindByPhone($phone);
        // Возвращаем найденную информацию в браузер
        $this->response = $this->throughResponse($result);
    }


}
