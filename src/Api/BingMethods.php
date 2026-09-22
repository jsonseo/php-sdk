<?php

namespace JsonSeo\Api;

/**
 * Методы Bing: органика, подсказки и вертикали картинок и видео.
 * XML-формата у Bing нет.
 *
 * @see https://jsonseo.ru/docs
 */
trait BingMethods
{
    /**
     * Органическая выдача bing.com. Без параметров локации — выдача по
     * России (mkt=ru-RU). Стоимость: 0.01 ₽ за страницу.
     *
     * При нескольких параметрах локации Bing берёт верхний по списку
     * (mkt, cc, ll), остальные игнорирует.
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
     * Подсказки Bing (autocomplete). Стоимость: 0.01 ₽ за запрос.
     *
     * @param  string|array{q: string, ll?: string, mkt?: string, cc?: string, setlang?: string}  $params
     * @return array{query: string, results: array<int, string>}
     */
    public function bingSuggest($params)
    {
        return $this->request('bing/suggest', $this->withPrimary($params, 'q'));
    }

    /**
     * Поиск по картинкам. Страница вертикали — count карточек, по умолчанию
     * 35. Дальше 700-й карточки Bing не листает.
     * Стоимость: 0.01 ₽ за страницу, от count она не зависит.
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
     * Поиск по видео. Страница вертикали — count карточек, по умолчанию 105.
     * Стоимость: 0.01 ₽ за страницу.
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
