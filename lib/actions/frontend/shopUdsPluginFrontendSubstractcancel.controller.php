<?php

require 'waJsonController_UdsBase.controller.php';

class shopUdsPluginFrontendSubstractCancelController extends waJsonController_UdsBase
{

    public function execute()
    {
        // Чистим сессию только от баллов
        wa()->getStorage()->set('shop/uds/points', '');

        $this->response = $this->successResponse();
    }

}
