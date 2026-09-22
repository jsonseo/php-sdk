# JSON SEO PHP SDK

Официальный PHP-клиент [JSON SEO API](https://jsonseo.ru): выдача Яндекса, Google и Bing, картинки и видео, поисковые подсказки, Яндекс Вордстат, прогноз показов Директа и геолокация по IP.

- Работает на **PHP 7.1 и выше**, включая 8.5.
- Без зависимостей: ходит через `ext-curl`, а где его нет — через потоки PHP.
- Двадцать один метод сервиса.
- Три попытки на запрос по умолчанию: если сервис затупил, SDK сходит ещё раз сам.

## Установка

```bash
composer require jsonseo/php-sdk
```

Ключ берётся в [личном кабинете](https://jsonseo.ru).

## Быстрый старт

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$client = new JsonSeo\Client('YOUR_KEY');

$serp = $client->yandex([
    'text' => 'купить ноутбук',
    'region' => 213,
]);

foreach ($serp['results'] as $position => $result) {
    echo ($position + 1) . '. ' . $result['domain'] . ' — ' . $result['title'] . PHP_EOL;
}
```

Если у метода один обязательный параметр, его можно передать просто строкой:

```php
$client->yandex('купить ноутбук');
$client->geoip('77.88.55.242');
$client->wordstatFrequency('ремонт айфона');
```

---

# Примеры запросов

## Позиции сайта в Яндексе

`break_domain` останавливает сбор на нужном домене — платить за страницы ниже найденной позиции незачем.

```php
$serp = $client->yandex([
    'text' => 'ремонт айфона',
    'region' => 213,          // Москва
    'pages' => 10,            // до 100 позиций
    'break_domain' => 'example.com',
]);

foreach ($serp['results'] as $index => $result) {
    if (stripos($result['domain'], 'example.com') !== false) {
        echo 'Позиция: ' . ($index + 1) . PHP_EOL;
        break;
    }
}

echo 'Собрано страниц: ' . $serp['pages'] . PHP_EOL;
echo 'Нашлось всего: ' . $serp['found_human'] . PHP_EOL;
```

В ответе:

```php
$serp = [
    'pages' => 3,
    'exhausted' => false,
    'breakDomainHit' => true,        // остановились на нужном домене
    'query' => 'ремонт айфона',
    'rawQuery' => 'ремонт айфона',
    'found' => 28000000,
    'found_human' => 'нашлось 28 млн результатов',
    'lr' => 213,
    'url' => 'https://yandex.ru/search/?text=...',
    'results' => [
        [
            'url' => 'https://example.com/remont-iphone/',
            'domain' => 'example.com',
            'title' => 'Ремонт айфонов в Москве',
            'passage' => 'Починим за 30 минут...',
            'breadcrumbs' => 'example.com › услуги',
        ],
    ],
];
```

## Выдача Google по нужному городу

Регион задаётся числовым ID из справочника — сервис сам соберёт `uule` и подставит `gl`.

```php
$regions = $client->googleRegions('Казань');
$kazan = $regions['regions'][0]['id'];

$serp = $client->google([
    'q' => 'заказать пиццу',
    'region' => $kazan,
    'hl' => 'ru',
    'device' => 'desktop',
    'pages' => 2,
]);
```

Если Google схлопнул часть результатов как «очень похожие», причина придёт в `filter_description`, а вернуть их можно параметром `filter`:

```php
$serp = $client->google(['q' => 'заказать пиццу', 'filter' => 0]);
```

## Выдача Bing

```php
$serp = $client->bing([
    'q' => 'buy a laptop',
    'mkt' => 'en-US',
    'pages' => 2,
]);

echo $serp['mkt'] . ' / ' . $serp['lang'] . PHP_EOL;  // фактический рынок и язык
```

## Реклама на странице выдачи

Приходит отдельным массивом, органика не меняется. Стоит +0.01 ₽ за страницу, на которой реклама нашлась.

```php
$serp = $client->yandex([
    'text' => 'пластиковые окна',
    'region' => 213,
    'ads' => true,
]);

foreach ($serp['ads'] as $ad) {
    echo $ad['block'] . ' #' . $ad['position'] . ' — ' . $ad['domain'] . PHP_EOL;
    echo '   ' . $ad['title'] . PHP_EOL;
}
```

`block` — где стоял блок: `top` до органики, `bottom` после неё, `inline` между результатами. Пустой массив `ads` значит «рекламу просили, но её не было», а отсутствие поля — «не просили».

## Ответ нейросети над выдачей

```php
$serp = $client->yandex([
    'text' => 'чем отличается osb от фанеры',
    'ai' => true,
]);

if (isset($serp['aiAnswer'])) {
    echo $serp['aiAnswer']['markdown'] . PHP_EOL;

    foreach ($serp['aiAnswer']['sources'] as $source) {
        echo '[' . $source['id'] . '] ' . $source['domain'] . PHP_EOL;
    }
}
```

Стоит +0.01 ₽ и только когда ответ есть: если поисковик его не показал, запрос обойдётся в обычную цену. Доступен только с первой страницы.

## Картинки

```php
$images = $client->yandexImages([
    'q' => 'скандинавский интерьер',
    'orientation' => 'horizontal',
    'size' => 'large',
    'format' => 'jpg',
    'pages' => 2,
]);

foreach ($images['results'] as $image) {
    echo $image['width'] . '×' . $image['height'] . ' ' . $image['url'] . PHP_EOL;
    echo '   источник: ' . $image['sourceUrl'] . PHP_EOL;
}
```

Те же параметры работают у `googleImages()` и `bingImages()` — SDK переводит общий фильтр в родной параметр движка. Если у поисковика такого значения нет, придёт ошибка 422 с указанием, чем заменить.

## Видео

```php
$videos = $client->googleVideo([
    'q' => 'как заменить ремень грм',
    'duration' => 'long',
    'hl' => 'ru',
]);

foreach ($videos['results'] as $video) {
    echo $video['title'] . ' — ' . $video['durationText'] . PHP_EOL;
    echo '   ' . $video['url'] . ' (' . $video['provider'] . ')' . PHP_EOL;
}
```

Поле `duration` приходит в секундах, но не всегда: у прямых эфиров вместо длины стоит `LIVE`. Отбор вида `duration < 600` молча выбросит такие ролики — ориентируйтесь на `durationText`, он на месте всегда.

## Поисковые подсказки

```php
$suggest = $client->yandexSuggest(['text' => 'купить кв', 'region' => 213]);

print_r($suggest['results']);
// ['купить квартиру в москве', 'купить квартиру в новостройке', ...]
```

Есть у всех трёх поисковиков: `yandexSuggest()`, `googleSuggest()`, `bingSuggest()`.

## Справочник регионов

```php
$regions = $client->yandexRegions('Казань');

foreach ($regions['regions'] as $region) {
    echo $region['id'] . ' — ' . $region['name'] . ' (' . $region['subname'] . ')' . PHP_EOL;
}
// 43 — Казань (Республика Татарстан)
```

Бесплатно, но ключ нужен: по нему считается лимит запросов в минуту. У `googleRegions()` в ответе дополнительно приходит готовая строка `uule`.

## Вордстат: частота запроса

```php
$frequency = $client->wordstatFrequency([
    'text' => 'ремонт айфона',
    'kind' => 'exact',       // точная частотность: "!ремонт !айфона"
    'region' => 213,
]);

echo $frequency['results']['totalValue'] . PHP_EOL;  // 27356
```

Вид частотности задаётся параметром `kind`, кавычки и операторы расставит сервис — фразу передавайте как есть:

| `kind` | Что считает |
| --- | --- |
| `base` | Базовая: фраза как есть |
| `phrase` | Фразовая: `"фраза"` |
| `exact` | Точная: `"!слово !слово"` — для прогноза трафика берут её |
| `superexact` | Сверхточная: `"[!слово !слово]"` |

## Вордстат: расширение семантики

```php
$wordstat = $client->wordstat(['text' => 'ремонт айфона', 'region' => [213, 2]]);

foreach ($wordstat['results']['popular'] as $phrase) {
    echo $phrase['value'] . "\t" . $phrase['text'] . PHP_EOL;
}

foreach ($wordstat['results']['associations'] as $phrase) {
    echo $phrase['value'] . "\t" . $phrase['text'] . PHP_EOL;
}
```

`popular` — что ищут вместе с фразой, `associations` — соседняя семантика.

## Вордстат: сезонность

```php
$graph = $client->wordstatGraph([
    'text' => 'купить ёлку',
    'graph_type' => 'month',
]);

foreach ($graph['results']['graph'] as $point) {
    echo $point['text'] . "\t" . $point['absolute'] . PHP_EOL;
}
// июнь 2026    9042
// июль 2026    11780
```

`month` и `week` отдают историю с 2018 года, `day` — последние 60 дней.

## Вордстат: география спроса

```php
$map = $client->wordstatMap(['text' => 'купить ноутбук', 'map_type' => 'regions']);

foreach ($map['results']['rows'] as $row) {
    echo $row['text'] . "\t" . $row['absolute'] . "\tиндекс " . $row['popularity'] . PHP_EOL;
}
```

`popularity` — affinity-индекс: 100 означает средний по стране интерес, выше — повышенный. В каждой строке приходит `region_id`, его можно сразу подставить в `region` других методов.

## Прогноз показов Яндекс Директа

Рекламный кабинет не нужен. Список фраз передаётся массивом — SDK склеит его сам.

```php
$forecast = $client->direct([
    'phrases' => ['ремонт айфона', 'замена экрана iphone', '"ремонт айфона"'],
    'region' => 213,
    'period' => 'month',
]);

foreach ($forecast['results'] as $row) {
    echo $row['phrase'] . ': ' . $row['shows'] . ' показов' . PHP_EOL;

    foreach ($row['positions'] as $place => $bid) {
        echo '   ' . $place . ': ставка ' . $bid['bid'] . ' ₽, бюджет ' . $bid['budget'] . ' ₽'
            . ', кликов ' . $bid['clicks'] . PHP_EOL;
    }
}
```

Вид частотности задаётся операторами прямо во фразе: `ремонт айфона` — базовая, `"ремонт айфона"` — фразовая, `"!ремонт !айфона"` — точная.

Стоимость — 0.01 ₽ за пачку до 4000 символов, это около 150 обычных фраз. За один запрос принимается до 1000 фраз, на аккаунт — не больше 100 запросов в час.

## Геолокация по IP

```php
$location = $client->geoip('77.88.55.242');

echo $location['country']['name'] . ', ' . $location['region']['name'] . PHP_EOL;
echo $location['latitude'] . ', ' . $location['longitude'] . PHP_EOL;
```

ID региона тот же, что у Яндекса, — его можно сразу подставить в `region` методов выдачи и Вордстата:

```php
$serp = $client->yandex([
    'text' => 'доставка пиццы',
    'region' => $location['region']['id'],
]);
```

## Баланс

```php
$balance = $client->balance();

echo $balance['balance'] . ' ' . $balance['currency'] . PHP_EOL;  // 123.45 RUB
```

---

# Справочник методов

| Метод | Путь API | Что делает |
| --- | --- | --- |
| `yandex($params)` | `/yandex` | Органическая выдача Яндекса |
| `yandexSuggest($params)` | `/yandex/suggest` | Поисковые подсказки |
| `yandexRegions($params)` | `/yandex/regions` | Справочник регионов, бесплатно |
| `yandexImages($params)` | `/yandex/images` | Поиск по картинкам |
| `yandexVideo($params)` | `/yandex/video` | Поиск по видео |
| `google($params)` | `/google` | Органическая выдача Google |
| `googleSuggest($params)` | `/google/suggest` | Подсказки |
| `googleRegions($params)` | `/google/regions` | Регионы и готовый `uule`, бесплатно |
| `googleImages($params)` | `/google/images` | Поиск по картинкам |
| `googleVideo($params)` | `/google/video` | Поиск по видео |
| `bing($params)` | `/bing` | Органическая выдача Bing |
| `bingSuggest($params)` | `/bing/suggest` | Подсказки |
| `bingImages($params)` | `/bing/images` | Поиск по картинкам |
| `bingVideo($params)` | `/bing/video` | Поиск по видео |
| `wordstat($params)` | `/wordstat` | Популярные и похожие запросы |
| `wordstatFrequency($params)` | `/wordstat/frequency` | Частота запроса одним числом |
| `wordstatGraph($params)` | `/wordstat/graph` | Динамика по месяцам, неделям, дням |
| `wordstatMap($params)` | `/wordstat/map` | География показов |
| `direct($params)` | `/direct` | Прогноз показов Яндекс Директа |
| `geoip($params)` | `/geoip` | Геолокация по IPv4, бесплатно |
| `balance()` | `/balance` | Остаток на счёте, бесплатно |

Полный список параметров каждого метода — в [документации](https://jsonseo.ru/docs) и в PHPDoc самих методов: IDE подскажет имена прямо на месте вызова.

Появился метод, которого ещё нет в SDK? Его можно вызвать напрямую:

```php
$client->call('новый/метод', ['параметр' => 'значение']);   // разберёт JSON
$client->callRaw('новый/метод', ['параметр' => 'значение']); // вернёт тело как есть
```

# Как SDK помогает с параметрами

**Списки передаются массивами.** Фразы для Директа склеиваются переводом строки, остальные списки — запятой:

```php
$client->direct(['ремонт айфона', 'ремонт телефона', 'замена экрана']);
$client->wordstat(['text' => 'ремонт', 'region' => [213, 2], 'device' => ['desktop', 'phone']]);
```

**Флаги принимаются флагами.** `true` и `false` уезжают как `1` и `0`:

```php
$client->yandex(['text' => 'купить ноутбук', 'ai' => true, 'ads' => true]);
```

**`null` и пустой массив не отправляются.** Необязательный параметр, который вы ещё не посчитали, можно не вычищать из массива руками.

# Ошибки

Всё, что бросает SDK, наследуется от `JsonSeo\Exception\JsonSeoException`.

| Исключение | Статус | Когда |
| --- | --- | --- |
| `ValidationException` | 422 | Параметры не приняты. `errors()` вернёт сообщения по полям |
| `UnauthorizedException` | 403, 401 | Ключ не передан или недействителен |
| `PaymentRequiredException` | 402 | На счёте не хватает средств |
| `RateLimitException` | 429 | Превышен лимит частоты |
| `ServiceUnavailableException` | 503 | Выдачу получить не вышло. Деньги не списаны |
| `ApiException` | прочие | Любой другой отказ сервиса |

У всех отказов сервиса есть `status()`, `body()`, разобранный `payload()` и `retryAfter()` — срок, который назвал сервис, если он его назвал.

| Исключение | Когда |
| --- | --- |
| `TransportException` | До сервиса не достучались: сеть, DNS, TLS |
| `TimeoutException` | Ответа не дождались за отведённое время |
| `IncompleteResponseException` | Соединение оборвалось посреди тела |
| `InvalidArgumentException` | SDK забраковал аргументы, запрос не отправлялся |

```php
use JsonSeo\Exception\PaymentRequiredException;
use JsonSeo\Exception\RateLimitException;
use JsonSeo\Exception\ValidationException;

try {
    $serp = $client->yandex(['text' => 'купить ноутбук', 'pages' => 50]);
} catch (ValidationException $e) {
    foreach ($e->errors() as $field => $messages) {
        echo $field . ': ' . implode(', ', $messages) . PHP_EOL;
    }
} catch (PaymentRequiredException $e) {
    echo 'Баланс кончился: ' . $client->balance()['balance'] . PHP_EOL;
} catch (RateLimitException $e) {
    echo 'Вернуться через ' . $e->retryAfter() . ' с' . PHP_EOL;
}
```

# Повторы

**У каждого запроса три попытки по умолчанию: одна основная и две повторных.** Если сервис затупил и выдачу собрать не вышло (`503`), SDK сам сходит ещё дважды, и обычно этого хватает.

`429`, `5xx` и обрывы связи повторяются автоматически — это ровно те отказы, за которые сервис денег не берёт. Отказы по ключу, балансу и параметрам не повторяются: сами они не изменятся.

Таймаут и оборвавшееся посреди тела соединение не повторяются, и это намеренно: работу на стороне сервиса обрыв у клиента не отменяет — выдача будет собрана и оплачена, а повтор стоил бы ещё раз. Если ответ не успевает прийти, поднимайте `timeout`, а не `attempts`.

Пауза между попытками удваивается и разбавляется случайной добавкой. Если сервис прислал `Retry-After`, SDK не вернётся раньше названного срока. Когда сервис просит ждать дольше `max_retry_delay`, SDK не ждёт вовсе, а отдаёт исключение с `retryAfter()` — решение остаётся за вами.

```php
$client = new JsonSeo\Client('YOUR_KEY', [
    'attempts' => 5,           // всего попыток, вместе с первой
    'retry_delay' => 2.0,      // стартовая пауза
    'max_retry_delay' => 60.0, // потолок паузы
]);
```

`'attempts' => 1` отключает повторы совсем.

# Настройки клиента

```php
$client = new JsonSeo\Client('YOUR_KEY', [
    'base_url' => 'https://jsonseo.ru/api', // адрес API
    'timeout' => 300.0,                     // сколько ждать ответа на попытку, секунд
    'connect_timeout' => 10.0,              // сколько ждать соединения, секунд
    'attempts' => 3,                        // всего попыток, вместе с первой
    'retry_delay' => 1.0,                   // стартовая пауза между попытками
    'max_retry_delay' => 30.0,              // потолок паузы
    'auth' => JsonSeo\Client::AUTH_HEADER,  // или AUTH_QUERY — ключ в параметре key
    'user_agent' => 'мой-проект/1.0',
    'transport' => $transport,              // свой JsonSeo\Transport\TransportInterface
]);
```

Таймаут по умолчанию намеренно большой: многостраничный запрос выдачи собирается минутами, и обрыв на стороне клиента не отменяет запрос на стороне сервиса — деньги за него уже списаны. Считается он на **каждую попытку** отдельно, а не на весь вызов.

Ключ по умолчанию едет в заголовке `Authorization: Bearer`, а не в адресе: так он не оседает в логах прокси и серверов. `AUTH_QUERY` нужен там, где заголовки до API не доходят.

# Свой транспорт

Если HTTP в проекте уже ходит через Guzzle, Symfony HttpClient или что-то своё, SDK можно отдать этот клиент — достаточно объекта с одним методом:

```php
use JsonSeo\Transport\Response;
use JsonSeo\Transport\TransportInterface;

class GuzzleTransport implements TransportInterface
{
    public function send($method, $url, array $headers, $body, array $options)
    {
        $response = $this->guzzle->request($method, $url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout'],
            'http_errors' => false,
        ]);

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }

        return new Response($response->getStatusCode(), $headers, (string) $response->getBody());
    }
}
```

Тот же приём годится для тестов: подмените транспорт заглушкой, и запросы никуда не пойдут.

# Разработка

```bash
composer install
composer test
```

Тесты идут без внешней сети: часть подменяет транспорт заглушкой, часть поднимает свой сервер на loopback.

# Лицензия

MIT.
