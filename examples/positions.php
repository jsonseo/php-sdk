<?php

/**
 * Позиции сайта в Яндексе по списку запросов.
 *
 * Запуск: JSONSEO_KEY=ваш_ключ php examples/positions.php
 */

require __DIR__.'/../vendor/autoload.php';

use JsonSeo\Client;
use JsonSeo\Exception\JsonSeoException;

$client = new Client(getenv('JSONSEO_KEY'));

$domain = 'example.com';
$queries = ['купить ноутбук', 'ноутбук недорого'];

foreach ($queries as $query) {
    try {
        // break_domain останавливает поиск: платить за страницы ниже незачем.
        $serp = $client->yandex([
            'text' => $query,
            'region' => 213,
            'pages' => 10,
            'break_domain' => $domain,
        ]);
    } catch (JsonSeoException $exception) {
        echo $query.': ошибка — '.$exception->getMessage().PHP_EOL;

        continue;
    }

    $position = null;

    foreach ($serp['results'] as $index => $result) {
        if (is_same_site($result['domain'], $domain)) {
            $position = $index + 1;

            break;
        }
    }

    echo $query.': '.($position === null ? 'не найден в топ-'.count($serp['results']) : $position).PHP_EOL;
}

/**
 * Сравнивает домен из выдачи с искомым сайтом: напрямую нельзя, выдача
 * отдаёт домен с поддоменом, и example.com не совпал бы с www.example.com.
 *
 * @param  string  $found  Домен, как его вернул поисковик
 * @param  string  $wanted  Искомый сайт
 * @return bool
 */
function is_same_site($found, $wanted)
{
    $found = strtolower($found);
    $wanted = strtolower($wanted);

    return $found === $wanted || substr($found, -strlen('.'.$wanted)) === '.'.$wanted;
}
