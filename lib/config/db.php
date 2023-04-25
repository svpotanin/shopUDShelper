<?php
// TODO: Пересмотреть структуру базы данных с учетом новых данных
return array(
    'shop_uds_orders' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'order_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        'uds_uid' => array('varchar', 255),
        'user_phone' => array('varchar', 255),
        'discount' => array('float', '14,2', 'null' => 0, 'default' => '0'),
        'points' => array('float', '14,2', 'null' => 0, 'default' => '0'),
        'type' => array('varchar', 255),
        'code' => array('varchar', 255),
        'uds_id' => array('varchar', 255, 'null' => 1),
        'refund_uds_id' => array('varchar', 255, 'null' => 1),
        'status' => array('int', 11, 'null' => 0, 'default' => '0'),
        'refunded' => array('int', 11, 'null' => 1, 'default' => '0'),
        'substracted' => array('int', 11, 'null' => 1, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
);