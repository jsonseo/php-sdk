<?php

namespace JsonSeo\Api;

/**
 * Вордстат: похожие запросы, частота, динамика и география.
 *
 * Вид частотности везде задаётся параметром kind, операторы расставляет
 * сервис — фразу передавайте без кавычек.
 *
 * @see https://jsonseo.ru/docs
 */
trait WordstatMethods
{
    /**
     * Популярные и похожие запросы. 0.01 ₽ за запрос.
     *
     * @param string|array{
     *     text: string,
     *     kind?: string,
     *     region?: string|int|array<int, int|string>,
     *     device?: string|array<int, string>
     * } $params Строка — сразу ключевая фраза
     * @return array{
     *     text: string,
     *     region: string,
     *     device: string,
     *     results: array{
     *         popular: array<int, array{text: string, value: int}>,
     *         associations: array<int, array{text: string, value: int}>
     *     }
     * }
     */
    public function wordstat($params)
    {
        return $this->request('wordstat', $this->withPrimary($params, 'text'));
    }

    /**
     * Частота запроса одним числом — results.totalValue. 0.01 ₽ за запрос.
     *
     * @param string|array{
     *     text: string,
     *     kind?: string,
     *     region?: string|int|array<int, int|string>,
     *     device?: string|array<int, string>
     * } $params
     * @return array{
     *     text: string,
     *     region: string,
     *     device: string,
     *     results: array{totalValue: int}
     * }
     */
    public function wordstatFrequency($params)
    {
        return $this->request('wordstat/frequency', $this->withPrimary($params, 'text'));
    }

    /**
     * Динамика показов: month и week — с 2018 года, day — последние 60 дней.
     * 0.01 ₽ за запрос.
     *
     * @param string|array{
     *     text: string,
     *     kind?: string,
     *     region?: string|int|array<int, int|string>,
     *     device?: string|array<int, string>,
     *     graph_type?: string
     * } $params
     * @return array{
     *     text: string,
     *     region: string,
     *     device: string,
     *     type: string,
     *     results: array{
     *         graph: array<int, array{date: string, text: string, absolute: int, relative: float}>
     *     }
     * }
     */
    public function wordstatGraph($params)
    {
        return $this->request('wordstat/graph', $this->withPrimary($params, 'text'));
    }

    /**
     * Показы по регионам и городам. popularity — affinity-индекс: 100 —
     * средний интерес. Параметр region не применяется. 0.01 ₽ за запрос.
     *
     * region_id в строке годится для region других методов; null, если
     * название неоднозначно.
     *
     * @param string|array{
     *     text: string,
     *     kind?: string,
     *     device?: string|array<int, string>,
     *     map_type?: string
     * } $params
     * @return array{
     *     text: string,
     *     device: string,
     *     type: string,
     *     results: array{
     *         rows: array<int, array{
     *             type: string,
     *             text: string,
     *             absolute: int,
     *             popularity: float,
     *             relative: float,
     *             region_id: int|null
     *         }>
     *     }
     * }
     */
    public function wordstatMap($params)
    {
        return $this->request('wordstat/map', $this->withPrimary($params, 'text'));
    }
}
