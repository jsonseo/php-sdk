<?php

namespace JsonSeo\Api;

/**
 * Методы Google: органика, подсказки, справочник регионов и вертикали
 * картинок и видео.
 *
 * @see https://jsonseo.ru/docs
 */
trait GoogleMethods
{
    /**
     * Органическая выдача google.com: мобильная, 0.01 ₽ за страницу.
     *
     * Регион — параметром region из googleRegions(): по нему соберутся
     * uule и gl.
     *
     * @param string|array{
     *     q: string,
     *     region?: int,
     *     pages?: int,
     *     page?: int,
     *     start?: int,
     *     gl?: string,
     *     hl?: string,
     *     uule?: string,
     *     ll?: string,
     *     nfpr?: bool,
     *     device?: string,
     *     filter?: int|bool,
     *     safe?: string,
     *     break_domain?: string,
     *     zone?: string,
     *     ai?: int|bool,
     *     ads?: int|bool
     * } $params Строка — сразу поисковый запрос
     * @return array{
     *     pages: int,
     *     exhausted: bool,
     *     breakDomainHit: bool,
     *     query: string,
     *     rawQuery: string,
     *     region: string,
     *     filter_description: string,
     *     url: string,
     *     results: array<int, array<string, mixed>>,
     *     aiAnswer?: array<string, mixed>,
     *     ads?: array<int, array<string, mixed>>
     * }
     */
    public function google($params)
    {
        return $this->request('google', $this->withPrimary($params, 'q'));
    }

    /**
     * Подсказки Google: до ~15 фраз. 0.01 ₽ за запрос.
     *
     * @param  string|array{q: string, region?: int, gl?: string, hl?: string, uule?: string, zone?: string}  $params
     * @return array{query: string, results: array<int, string>}
     */
    public function googleSuggest($params)
    {
        return $this->request('google/suggest', $this->withPrimary($params, 'q'));
    }

    /**
     * ID региона Google по названию и готовый uule. Бесплатно, нужен ключ.
     *
     * @param  string|array{name: string, lang?: string}  $params
     * @return array{
     *     name: string,
     *     lang: string,
     *     regions: array<int, array{
     *         id: int,
     *         name: string,
     *         subname: string,
     *         type: string,
     *         type_name: string,
     *         canonical_name: string,
     *         uule: string,
     *         lat: float,
     *         lon: float
     *     }>
     * }
     */
    public function googleRegions($params)
    {
        return $this->request('google/regions', $this->withPrimary($params, 'name'));
    }

    /**
     * Поиск по картинкам: 100 карточек на страницу, 0.01 ₽ за страницу.
     *
     * @param string|array{
     *     q: string,
     *     pages?: int,
     *     page?: int,
     *     start?: int,
     *     region?: int,
     *     uule?: string,
     *     ll?: string,
     *     hl?: string,
     *     gl?: string,
     *     zone?: string,
     *     safe?: string,
     *     filter?: int|bool,
     *     size?: string,
     *     orientation?: string,
     *     color?: string,
     *     type?: string,
     *     format?: string,
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
    public function googleImages($params)
    {
        return $this->request('google/images', $this->withPrimary($params, 'q'));
    }

    /**
     * Поиск по видео: 10 карточек на страницу, 0.01 ₽ за страницу.
     *
     * @param string|array{
     *     q: string,
     *     pages?: int,
     *     page?: int,
     *     start?: int,
     *     region?: int,
     *     uule?: string,
     *     ll?: string,
     *     hl?: string,
     *     gl?: string,
     *     zone?: string,
     *     safe?: string,
     *     filter?: int|bool,
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
    public function googleVideo($params)
    {
        return $this->request('google/video', $this->withPrimary($params, 'q'));
    }
}
