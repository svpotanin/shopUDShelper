<?php

return array(
    'name' => "UDS",
    'description' => "модульная экосистема
     для вашего бизнеса",
    'img'=>'img/uds.ico',
    'version' => '1.0.0',
    'vendor' => 'quadro-design',
    'frontend' => true,
    'handlers' => array(
        // Подключение во фронтенде корзины нашей формы
        'frontend_cart' => 'handleFrontendCart',
        'frontend_head' => 'frontendHead',

        // Функция пользовательского расчета скидки корзины
        'order_calculate_discount' => 'orderCalculateDiscount',

        // События создания, выполнения и отмены заказа в магазине
        'order_action.create' => 'orderActionCreate',
        'order_action.complete' => 'orderActionComplete',
        'order_action.delete' => 'orderActionDelete',
    ),
);
