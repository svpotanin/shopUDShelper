<?php 
return array(	
    'enabled' => array(
        'title' => 'Enabled',
        'control_type' => waHtmlControl::CHECKBOX,
        'value' => 0,
    ), 
	'company_id' => array(
        'title' => 'Company ID',
        'control_type' => waHtmlControl::INPUT,
        'description' => '',
    ),
	'api_key' => array(
        'title' => 'Api Key',
        'control_type' => waHtmlControl::INPUT,
        'description' => '',
    ),
    'not_found_message_code' => array(
        'title' => 'Код не найден',
        'control_type' => waHtmlControl::TEXTAREA,
        'value' => 'Недействительный код клиента или срок действия кода истек. Пожалуйста, введите новый код',
        'description' => 'Введите сообщение об ошибке, которое должно появиться у пользователя, когда введенный им код не найден. (вы можете использовать HTML)',
    ),
    'not_found_message_phone' => array(
        'title' => 'Телефон не найден',
        'control_type' => waHtmlControl::TEXTAREA,
        'value' => 'Вы еще не зарегистрированы в приложении UDS, Нажмите <a href="https://uds.app/">здесь</a>, чтобы загрузить приложение.',
        'description' => 'Введите сообщение об ошибке, которое должно появиться у пользователя, когда введенный им телефон не найден. (вы можете использовать HTML)',
    ),
    'only_participants' => array(
        'title' => 'Разрешить получение и снятие баллов только для участников кампании',
        'control_type' => waHtmlControl::CHECKBOX,
        'value' => 0,
    ), 
    'not_participant_message' => array(
        'title' => 'Если клиент не вступил в компанию',
        'control_type' => waHtmlControl::TEXTAREA,
        'value' => 'Ошибка! Вы не присоединились к нашей компании в UDS. Пожалуйста, нажмите <a href="#">эту ссылку для присоединения.</a>',
        'description' => 'Введите сообщение об ошибке, которое должно появиться у пользователя, если клиент не вступил в компанию (id=null). (вы можете использовать HTML. [img-link] = qr)',
    ),
    'not_participant_qr' => array(
        'title' => 'QR-компания Изображение',
        'control_type' => waHtmlControl::FILE,
    ),
    'cashback_message' => array(
        'title' => 'Если в компании активна опция кэшбэка',
        'control_type' => waHtmlControl::TEXTAREA,
        'value' => 'После выполнения заказа вы получите кэшбэк [cashback-amount]% в приложении UDS.',
        'description' => 'вы можете использовать HTML. "[cashback-amount]" => сколько кэшбэка получит пользователь',
    ),
    'udsnobonus_help' => array(
        'control_type' => waHtmlControl::HELP,
        'value' => 'товары, которые не должны участвовать в системе UDS (сумма этих товаров не должна отображаться в UDS) - Добавить новую строку в параметры товара: "udsnobonus=1"',
    ),
    'udsloyaltyskip_help' => array(
        'control_type' => waHtmlControl::HELP,
        'value' => 'товары, на которые не должны начисляться баллы или применяться скидка в системе UDS - Добавить новую строку в параметры товара: "udsloyaltyskip=1"',
    ),

);