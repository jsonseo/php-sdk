<?php

namespace JsonSeo\Api;

/**
 * Директ, геолокация по IP и баланс.
 *
 * @see https://jsonseo.ru/docs
 */
trait AccountMethods
{
    /**
     * Прогноз показов Директа со ставками и бюджетом. Кабинет не нужен.
     *
     * 0.01 ₽ за пачку до 4000 символов (около 150 фраз); разбивка и чистка
     * дублей — на сервисе. До 1000 фраз за запрос, 100 запросов в час.
     *
     * Вид частотности задаётся операторами во фразе: ремонт айфона —
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
     * Страна, регион и координаты по IPv4. ID региона — тот же, что у Яндекса,
     * и годится для region других методов. Бесплатно, нужен ключ.
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
     * Текущий баланс. Бесплатно, нужен ключ.
     *
     * @return array{balance: float, currency: string}
     */
    public function balance()
    {
        return $this->request('balance', []);
    }
}
