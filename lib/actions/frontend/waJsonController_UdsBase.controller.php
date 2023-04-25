<?php

/**
 * Расширение страндартного контроллера JSON
 */
class waJsonController_UdsBase extends waJsonController
{

    protected function setClearSession()
    {
        wa()->getStorage()->set('shop/udsdiscount', '');
        wa()->getStorage()->set('shop/udspoints', '');
        wa()->getStorage()->set('shop/udsdiscount/user', '');
    }

    protected function errorResponse($message = 'Произошла ошибка. Пожалуйста, попробуйте еще раз!')
    {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ];
    }

    protected function successResponse($data = true, $message = 'OK')
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function throughResponse($response)
    {
        return $response;
    }

}
