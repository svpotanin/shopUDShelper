<?php

require 'waJsonController_UdsBase.controller.php';

class shopUdsPluginFrontendCheckCancelController extends waJsonController_UdsBase
{

    public function execute()
    {
        // Чистим сессию
        $this->setClearSession();

        $this->response = $this->successResponse();
    }

}
