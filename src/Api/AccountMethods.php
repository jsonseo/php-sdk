<?php

namespace JsonSeo\Api;

/**
 * Прогноз Яндекс Директа, геолокация по IP и баланс счёта.
 *
 * @see https://jsonseo.ru/docs
 */
trait AccountMethods
{
    /**
     * Прогноз показов Яндекс Директа со ставками и бюджетом по местам
     * аукциона. Рекламный кабинет не нужен.
     *
     * Стоимость: 0.01 ₽ за пачку фраз до 4000 символов — около 150 обычных
     * фраз. Разбивка на пачки и чистка дублей на стороне сервиса. Не больше
     * 100 запросов в час на аккаунт; считаются запросы, а не фразы, и в один
     * запрос помещается до 1000 фраз.
     *
     * Вид частотности задаётся операторами прямо во фразе: «ремонт айфона» —
     * базовая, "ремонт айфона" — фразовая, "!ремонт !айфона" — точная.
     *
     * @param string|array<int, string>|array{
     *     phrases: string|array<int, string>,
     *     region?: int,
     *     period?: string,
     *     period_num?: int
     * } $params Список фраз передаётся массивом — SDK склеит его сам
     * @return array{
     *     geo: int,
     *     period: string,
     *     batches: int,
     *     processed: int,
     *     results: array<int, array{
     *         phrase: string,
     *         shows: int,
     *         positions: array<string, array{bid: float, budget: float, clicks: int, ctr: float, shows: int}>
     *     }>,
     *     errors: array<int, string>
     * }
     */
    public function direct($params)
    {
        return $this->request('direct', $this->withPrimary($params, 'phrases'));
    }

    /**
     * Страна, регион и координаты по IPv4-адресу. ID региона совпадает с ID
     * региона Яндекса: результат можно сразу подставить в region методов
     * yandex() и wordstat().
     *
     * Бесплатно, но ключ обязателен — по нему считается лимит.
     *
     * @param  string|array{ip: string}  $params
     * @return array{
     *     ip: string,
     *     latitude: float,
     *     longitude: float,
     *     region: array{id: int, name: string},
     *     country: array{id: int, name: string, iso_name: string}
     * }
     */
    public function geoip($params)
    {
        return $this->request('geoip', $this->withPrimary($params, 'ip'));
    }

    /**
     * Текущий баланс аккаунта. Бесплатно, ключ обязателен.
     *
     * @return array{balance: float, currency: string}
     */
    public function balance()
    {
        return $this->request('balance', []);
    }
}
