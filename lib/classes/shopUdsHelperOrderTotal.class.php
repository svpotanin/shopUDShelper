<?php

//require wa()->getAppPath('plugins/uds/lib/vendors/vendor/autoload.php',  'shop');

/**
 * Класс-помошник, для расчета сумм на которые начисляется скидка или вознаграждение. Рассчет происходит в конструкторе класса
 */
class shopUdsHelperOrderTotal extends shopUdsHelperItemsTotal
{

    /**
     * Конструктор
     * @param $order_id - Номер заказа
     */
    static public function calc($order_id)
    {
        $shopOrderItemsModel = new shopOrderItemsModel();
        $items = $shopOrderItemsModel->getItems($order_id);

        if (!$items) {
            return false;
        }

        return new shopUdsHelperCartTotal($items);
    }

}