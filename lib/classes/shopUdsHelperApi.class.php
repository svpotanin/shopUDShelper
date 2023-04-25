<?php /** @noinspection PhpSameParameterValueInspection */

require wa()->getAppPath('plugins/uds/lib/vendors/vendor/autoload.php', 'shop');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class shopUdsHelperApi
{

    /** @var string Api ключ для авторизации */
    protected $apiKey;
    /** @var string ID Компаниии для авторизации */
    protected $companyId;

    /**
     * Функция инициализации статического класса для работы с API USD
     * @param $apiKey string API для авторизации UDS
     * @param $companyId string ID компании в UDS
     * @return bool True - если инициализация удалась / False - если нет
     */
    public function __construct(string $apiKey, string $companyId)
    {
        $this->apiKey = $apiKey;
        $this->companyId = $companyId;
    }

    /**
     * Формирование и отправка HTTP запроса
     *
     * @param string $method GET или POST метод отправки запроса
     * @param string $url Куда отправляем запрос
     * @param array $query_params Массив передаваемых данных запроса. Ключ => Значение
     * @return array|false Либо массив данных ответа запроса, либо FALSE - если есть ошибки запроса
     * @throws GuzzleException
     */
    private function httpRequest(string $method, string $url, array $query_params = [])
    {
        if ($method !== 'GET' and $method !== 'POST') {
            return false;
        }

        if (empty($url)) {
            return false;
        }

        if (!is_array($query_params)) {
            return false;
        }

        $date = new DateTime();

        $client = new GuzzleHttp\Client();
        $options = [
            'http_errors' => false,
            'headers' => [
                'Content-type' => 'application/json',
                'Accept-Charset' => 'utf-8',
                'Accept' => 'application/json',
                'Authorization' => "Basic " . base64_encode($this->companyId . ":" . $this->apiKey),
                'X-Timestamp' => $date->format(DateTime::ATOM),
            ]
        ];
        if ($method == 'GET') {
            $options['query'] = $query_params;
        }
        if ($method == 'POST') {
            $options['body'] = json_encode($query_params);
        }

        $response = $client->request($method, $url, $options);

        return [
            'status' => [
                'code' => $response->getStatusCode(),
                'phrase' => $response->getReasonPhrase(),
            ],
            'content' => json_decode($response->getBody()->getContents(), true),
        ];
    }


    /**
     * Поиск клиента по Коду или Телефону
     *
     * @param $identifier string Код из приложения клиента UDS
     * @param $identifier_type string Тип идентификатора 'code'|'phone'
     * @param $total integer|float Полная сумма заказа
     * @param $skipLoyaltyTotal integer|float|null Сумма, на которую не надо начислять бонусы, необязательно
     * @return array|false  False - Если ошибка входных данных, Array - Массив данных ответа
     * @throws GuzzleException
     */
    public function customerFind($identifier, $identifier_type, $total, $skipLoyaltyTotal = null)
    {
        if ($identifier_type != 'code' && $identifier_type != 'phone') {
            return false;
        }

        $data[$identifier_type] = $identifier;
        $data['total'] = $total;

        if ($skipLoyaltyTotal) {
            if ($skipLoyaltyTotal > 0) {
                $data['skipLoyaltyTotal'] = $skipLoyaltyTotal;
            }
        }

        $response = $this->httpRequest('GET', "https://api.uds.app/partner/v2/customers/find", $data);

        return $response;
    }

    /**
     * Запрос настроек компании в UDS
     * @return array|false   Array - Массив данных ответа, False - Если ошибка входных данных или не инициализирован класс
     * @throws GuzzleException
     */
    public function settings()
    {
        $response = $this->httpRequest('GET', "https://api.uds.app/partner/v2/settings");

        return $response;
    }

    /**
     * @param $code - Код на оплату. клиента из приложения UDS
     * @param $receipt_total - Чек. Сумма счета в денежных единицах.
     * @param $receipt_cash - Чек. Оплачиваемая сумма в денежных единицах.
     * @param $receipt_points - Чек. Оплачиваемая сумма в бонусных баллах.
     * @param $receipt_number - Чек. Номер чека.
     * @param $receipt_skipLoyaltyTotal - Часть суммы счета, на которую не начисляется кешбэк и на которую не распространяется скидка (в денежных единицах).
     * @return array|false
     * @throws GuzzleException
     */
    public function operationCreateByCode(
        $code,

        $receipt_total,
        $receipt_cash,
        $receipt_points,
        $receipt_number,

        $receipt_skipLoyaltyTotal = null)
    {

        $data['code'] = $code;

        $data['cashier'] = [
            'externalId'    => '0',
            'name'          => 'Webasyst',
        ];

        $data['receipt'] = [
            'total' => number_format((float)$receipt_total, 2, '.', ''),
            'cash' => number_format((float)$receipt_cash, 2, '.', ''),
            'points' => number_format((float)$receipt_points, 2, '.', ''),
            'number' => $receipt_number,
            'skipLoyaltyTotal' => $receipt_skipLoyaltyTotal,
        ];

        $response = $this->httpRequest('POST', "https://api.uds.app/partner/v2/operations", $data);

        return $response;
    }

    /**
     * @param $phone - Номер телефона. клиента UDS
     * @param $receipt_total - Чек. Сумма счета в денежных единицах.
     * @param $receipt_cash - Чек. Оплачиваемая сумма в денежных единицах.
     * @param $receipt_points - Чек. Оплачиваемая сумма в бонусных баллах.
     * @param $receipt_number - Чек. Номер чека.
     * @param $receipt_skipLoyaltyTotal - Часть суммы счета, на которую не начисляется кешбэк и на которую не распространяется скидка (в денежных единицах).
     * @return array|false
     * @throws GuzzleException
     */
    public function operationCreateByPhone(
        $phone,

        $receipt_total,
        $receipt_cash,
        $receipt_points,
        $receipt_number,

        $receipt_skipLoyaltyTotal = null)
    {

        $data['participant']['phone'] = $phone;

        $data['cashier'] = [
            'externalId'    => '0',
            'name'          => 'Webasyst',
        ];

        $data['receipt'] = [
            'total' => number_format((float)$receipt_total, 2, '.', ''),
            'cash' => number_format((float)$receipt_cash, 2, '.', ''),
            'points' => number_format((float)$receipt_points, 2, '.', ''),
            'number' => $receipt_number,
            'skipLoyaltyTotal' => $receipt_skipLoyaltyTotal,
        ];

        $response = $this->httpRequest('POST', "https://api.uds.app/partner/v2/operations", $data);

        return $response;
    }

    public function operationRefund($id, $partialAmount = null)
    {

        if ($partialAmount) {
            $data['partialAmount'] = $partialAmount;
        }

        $response = $this->httpRequest('POST', "https://api.uds.app/partner/v2/operations/".$id."/refund", $data);

        return $response;
    }

    /**
     * @param $participants array|string ID клиента или Массив ID-шников клиентов
     * @param $points - Количество начисляемых баллов
     * @param $comment - Количество списываемых баллов
     * @param $silent - Не оправлять ПУШ-уведомеление
     * @return array|false
     * @throws GuzzleException
     */
    public function operationReward($participants, $points, $comment = null, $silent = null)
    {

        if (is_string($participants)) {
            $data['$participants'] = [
                '$participants',
            ];
        } else {
            $data['participants'] = $participants;
        }

        $data['points'] = $points;

        if ($comment) {
            $data['comment'] = $comment;
        }
        if ($silent) {
            $data['silent'] = $silent;
        }

        $response = $this->httpRequest('POST', "https://api.uds.app/partner/v2/operations/reward", $data);

        return $response;
    }




}
