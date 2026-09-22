<?php

namespace JsonSeo\Api;

/**
 * Методы Bing: органика, подсказки, картинки и видео.
 *
 * @see https://jsonseo.ru/docs
 */
trait BingMethods
{
    /**
     * Органическая выдача bing.com: без локации — Россия, 0.01 ₽ за страницу.
     *
     * Из нескольких параметров локации Bing берёт верхний: mkt, cc, ll.
     *
     * @param string|array{
     *     q: string,
     *     pages?: int,
     *     mkt?: string,
     *     cc?: string,
     *     ll?: string,
     *     setlang?: string,
     *     device?: string,
     *     safesearch?: string,
     *     break_domain?: string,
     *     page?: int,
     *     first?: int,
     *     ai?: int|bool
     * } $params Строка — сразу поисковый запрос
     * @return array{
     *     pages: int,
     *     exhausted: bool,
     *     breakDomainHit: bool,
     *     query: string,
     *     rawQuery: string,
     *     region: string,
     *     mkt: string,
     *     lang: string,
     *     url: string,
     *     results: array<int, array<string, mixed>>,
     *     aiAnswer?: array<string, mixed>
     * }
     */
    public function bing($params)
    {
        return $this->request('bing', $this->withPrimary($params, 'q'));
    }

    /**
     * Подсказки Bing. 0.01 ₽ за запрос.
     *
     * @param  string|array{q: string, ll?: string, mkt?: string, cc?: string, setlang?: string}  $params
     * @return array{query: string, results: array<int, string>}
     */
    public function bingSuggest($params)
    {
        return $this->request('bing/suggest', $this->withPrimary($params, 'q'));
    }

    /**
     * Поиск по картинкам: count карточек на страницу (по умолчанию 35),
     * дальше 700-й Bing не листает. 0.01 ₽ за страницу.
     *
     * @param string|array{
     *     q: string,
     *     pages?: int,
     *     page?: int,
     *     first?: int,
     *     count?: int,
     *     mkt?: string,
     *     cc?: string,
     *     setlang?: string,
     *     ll?: string,
     *     safesearch?: string,
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
    public function bingImages($params)
    {
        return $this->request('bing/images', $this->withPrimary($params, 'q'));
    }

    /**
     * Поиск по видео: count карточек на страницу (по умолчанию 105).
     * 0.01 ₽ за страницу.
     *
     * @param string|array{
     *     q: string,
     *     pages?: int,
     *     page?: int,
     *     first?: int,
     *     count?: int,
     *     mkt?: string,
     *     cc?: string,
     *     setlang?: string,
     *     ll?: string,
     *     safesearch?: string,
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
    public function bingVideo($params)
    {
        return $this->request('bing/video', $this->withPrimary($params, 'q'));
    }
}
