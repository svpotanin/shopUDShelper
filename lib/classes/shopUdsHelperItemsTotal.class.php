<?php

/**
 * Класс-помошник, для расчета сумм на которые начисляется скидка или вознаграждение. Рассчет происходит в конструкторе класса
 */
class shopUdsHelperItemsTotal
{
    /**
     * @var $total float Сумма без скидок
     */
    protected float $total = 0.00;

    /**
     * @var $total_for_reward float Сумма корзины, на которую рассчитывается вознаграждение
     */
    protected float $total_for_reward = 0.00;

    /**
     * @var $total_for_discount float Сумма корзины, на которую рассчитывается скидка
     */
    protected float $total_for_discount = 0.00;

    /**
     * @var $skip_loyalty_total float Сумма, на которую скидка не распространяется
     */
    protected float $skip_loyalty_total = 0.00;


    /**
     * Конструктор
     */
    public function __construct($items)
    {
        $shopProductParamsModel = new shopProductParamsModel();

        $total = 0;
        $total_for_reward = 0;
        $total_for_discount = 0;

        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $product_price = $item['quantity'] * $item['price'];

            $total = $total + $product_price;

            $params = $shopProductParamsModel->get($product_id);

            if (!isset($params['udsnobonus']) OR $params['udsnobonus'] != '1') {
                $total_for_reward = $total_for_reward + $product_price;
            }

            if (!isset($params['udsloyaltyskip']) OR $params['udsloyaltyskip'] != '1') {
                $total_for_discount = $total_for_discount + $product_price;
            }

        }

        $this->total = (float)$total;
        $this->total_for_reward = (float)$total_for_reward;
        $this->total_for_discount = (float)$total_for_discount;

        $this->skip_loyalty_total = (float)$total - (float)$total_for_discount;
    }

    /**
     * Сумма без скидок
     * @return float
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Сумма для рассчета вознаграждения
     * @return float
     */
    public function getTotalForReward()
    {
        return $this->total_for_reward;
    }

    /**
     * Сумма для рассчета скидки
     * @return float
     */
    public function getTotalForDiscount()
    {
        return $this->total_for_discount;
    }

    /**
     * Сумма, на которую скидка не распространяется
     * @return float
     */
    public function getSkipLoyaltyTotal()
    {
        return $this->skip_loyalty_total;
    }

}