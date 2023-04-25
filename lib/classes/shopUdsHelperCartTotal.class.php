<?php

/**
 * Класс-помошник, для расчета сумм на которые начисляется скидка или вознаграждение. Рассчет происходит в конструкторе класса
 */
class shopUdsHelperCartTotal extends shopUdsHelperItemsTotal
{

    static public function calc()
    {
        $cart = new shopCart();
        $items = $cart->items();

        if (!$items) {
            return false;
        }

        return new shopUdsHelperCartTotal($items);
    }

}