<?php

namespace JsonSeo\Api;

/**
 * Методы Яндекса: органика, подсказки, справочник регионов и вертикали
 * картинок и видео.
 *
 * @see https://jsonseo.ru/docs
 */
trait YandexMethods
{
    /**
     * Органическая выдача Яндекса: мобильная, регион 213. 0.01 ₽ за страницу.
     *
     * @param string|array{
     *     text: string,
     *     region?: int,
     *     pages?: int,
     *     page?: int,
     *     p?: int,
     *     filter?: string,
     *     hlword?: int|bool,
     *     noreask?: bool,
     *     zone?: string,
     *     device?: string,
     *     break_domain?: string,
     *     ai?: int|bool,
     *     ads?: int|bool
     * } $params Строка — сразу поисковый запрос
     * @return array{
     *     pages: int,
     *     exhausted: bool,
     *     breakDomainHit: bool,
     *     query: string,
     *     rawQuery: string,
     *     found: int|null,
     *     found_human: string,
     *     lr: int,
     *     url: string,
     *     results: array<int, array<string, mixed>>,
     *     aiAnswer?: array<string, mixed>,
     *     ads?: array<int, array<string, mixed>>
     * }
     */
    public function yandex($params)
    {
        return $this->request('yandex', $this->withPrimary($params, 'text'));
    }

    /**
     * Подсказки Яндекса: до 50 фраз с учётом региона. 0.01 ₽ за запрос.
     *
     * @param  string|array{text: string, region?: int, zone?: string}  $params
     * @return array{query: string, results: array<int, string>, lr: string}
     */
    public function yandexSuggest($params)
    {
        return $this->request('yandex/suggest', $this->withPrimary($params, 'text'));
    }

    /**
     * Код региона (lr) по названию города или области. Бесплатно, нужен ключ.
     *
     * @param  string|array{name: string, lang?: string}  $params
     * @return array{
     *     name: string,
     *     lang: string,
     *     regions: array<int, array{id: int, name: string, subname: string, lat: float, lon: float}>
     * }
     */
    public function yandexRegions($params)
    {
        return $this->request('yandex/regions', $this->withPrimary($params, 'name'));
    }

    /**
     * Поиск по картинкам: 20 карточек на страницу, 0.01 ₽ за страницу.
     *
     * Общие фильтры переводятся в родные параметры Яндекса; значения,
     * которого у него нет, дают 422 с указанием замены.
     *
     * @param string|array{
     *     q: string,
     *     pages?: int,
     *     page?: int,
     *     p?: int,
     *     region?: int,
     *     zone?: string,
     *     family?: int|bool,
     *     filter?: string,
     *     size?: string,
     *     orientation?: string,
     *     color?: string,
     *     type?: string,
     *     format?: string,
     *     freshness?: string,
     *     site?: string,
     *     ads?: int|bool
     * } $params
     * @return array{
     *     pages: int,
     *     exhausted: bool,
     *     query: string,
     *     url: string,
     *     results: array<int, array<string, mixed>>,
     *     ads?: array<int, array<string, mixed>>
     * }
     */
    public function yandexImages($params)
    {
        return $this->request('yandex/images', $this->withPrimary($params, 'q'));
    }

    /**
     * Поиск по видео: 20 карточек на страницу, 0.01 ₽ за страницу.
     *
     * @param string|array{
     *     q: string,
     *     pages?: int,
     *     page?: int,
     *     p?: int,
     *     region?: int,
     *     zone?: string,
     *     family?: int|bool,
     *     filter?: string,
     *     duration?: string,
     *     hd?: string|bool,
     *     freshness?: string,
     *     site?: string
     * } $params
     * @return array{
     *     pages: int,
     *     exhausted: bool,
     *     query: string,
     *     url: string,
     *     results: array<int, array<string, mixed>>
     * }
     */
    public function yandexVideo($params)
    {
        return $this->request('yandex/video', $this->withPrimary($params, 'q'));
    }
}
