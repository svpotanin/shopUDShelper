<?php

require 'waJsonController_UdsBase.controller.php';

class shopUdsPluginFrontendCheckCodeController extends waJsonController_UdsBase
{

    public function execute()
    {
        $uds_helper = new shopUdsHelperNew();

        // Достаем данные из Request
        $code = waRequest::get('code');

        if (!$code) {
            $this->response = $this->errorResponse();
            return;
        }

        // Чистим сессию
        $this->setClearSession();

        // Находим клиента и пишем данные в сессию
        $result = $uds_helper->customerFindByCode($code);

        // Возвращаем найденную информацию в браузер
        $this->response = $this->throughResponse($result);
    }

}
