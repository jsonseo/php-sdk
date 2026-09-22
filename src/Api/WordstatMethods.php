<?php

namespace JsonSeo\Api;

/**
 * Яндекс Вордстат: похожие запросы, частота, динамика и география.
 *
 * Вид частотности во всех четырёх методах задаётся параметром kind, операторы
 * расставляются на стороне сервиса — фразу передавайте без кавычек:
 * base (как есть), phrase ("фраза"), exact ("!слово !слово"),
 * superexact ("[!слово !слово]").
 *
 * @see https://jsonseo.ru/docs
 */
trait WordstatMethods
{
    /**
     * Списки популярных и похожих запросов — материал для расширения
     * семантики. Стоимость: 0.01 ₽ за запрос.
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
     * Частота запроса одним числом — results.totalValue.
     * Стоимость: 0.01 ₽ за запрос.
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
     * Динамика показов по месяцам, неделям или дням — сезонность и тренд.
     * month и week отдают историю с 2018 года, day — последние 60 дней.
     * Стоимость: 0.01 ₽ за запрос.
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
     * Распределение показов по регионам и городам. Поле popularity — это
     * affinity-индекс: 100 — средний интерес, выше — повышенный.
     * Параметр region здесь не применяется.
     * Стоимость: 0.01 ₽ за запрос.
     *
     * В каждой строке region_id — ID региона Яндекса, его можно сразу
     * подставлять в region других методов. null, если название неоднозначно.
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
