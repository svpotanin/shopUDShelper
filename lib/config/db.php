<?php
// TODO: Пересмотреть структуру базы данных с учетом новых данных
return array(
    'shop_uds_orders' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),

        // ID Заказа
        'order_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        // UID Пользователя в UDS
        'uds_uid' => array('varchar', 255),
        // Телефон пользователя в UDS
        'user_phone' => array('varchar', 255),
        // Скидка
        'discount' => array('float', '14,2', 'null' => 0, 'default' => '0'),
        // Списываемые баллы (наверно)
        'points' => array('float', '14,2', 'null' => 0, 'default' => '0'),
        // Тип идентификатора
        'type' => array('varchar', 255),
        // Значение идентификатора
        'code' => array('varchar', 255),
        // ID Операции продажи в UDS
        'uds_id' => array('varchar', 255, 'null' => 1),
        // ID Операции возврата в UDS
        'refund_uds_id' => array('varchar', 255, 'null' => 1),
        // Статус хуй знает как используется
        'status' => array('int', 11, 'null' => 0, 'default' => '0'),
        // Флаг - значит возвращено
        'refunded' => array('int', 11, 'null' => 1, 'default' => '0'),
        // Флаг - значит баллы списаны
        'substracted' => array('int', 11, 'null' => 1, 'default' => '0'),

        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
);