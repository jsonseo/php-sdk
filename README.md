# JSON SEO PHP SDK

Официальный PHP-клиент [JSON SEO API](https://jsonseo.ru): выдача Яндекса, Google и Bing, вертикали картинок и видео, Вордстат, прогноз показов Яндекс Директа и геолокация по IP.

- Работает на **PHP 7.1 и выше**, включая 8.5.
- Без зависимостей: ходит через `ext-curl`, а где его нет — через потоки PHP.
- Двадцать один метод сервиса: органика, вертикали картинок и видео, подсказки, справочники регионов, Вордстат, Директ, геолокация и баланс.
- Временные отказы повторяются сами, постоянные — сразу превращаются в понятные исключения.

## Установка

```bash
composer require jsonseo/php-sdk
```

Composer-а нет? Подойдёт и `require` файла автозагрузки из архива релиза — библиотека следует PSR-4 и ничего, кроме `ext-json`, не требует.

## Быстрый старт

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$client = new JsonSeo\Client('ВАШ_КЛЮЧ');

$serp = $client->yandex([
    'text' => 'купить ноутбук',
    'region' => 213,
    'pages' => 2,
]);

foreach ($serp['results'] as $position => $result) {
    echo ($position + 1) . '. ' . $result['domain'] . ' — ' . $result['title'] . PHP_EOL;
}
```

Ключ берётся в [личном кабинете](https://jsonseo.ru). Если у метода один обязательный параметр, его можно передать строкой:

```php
$client->yandex('купить ноутбук');
$client->geoip('77.88.55.242');
$client->wordstatFrequency('ремонт айфона');
```

## Методы

Все методы возвращают ассоциативный массив — ровно то, что прислал сервис.

### Яндекс

| Метод | Путь API | Что делает |
| --- | --- | --- |
| `yandex($params)` | `/yandex` | Органическая выдача |
| `yandexSuggest($params)` | `/yandex/suggest` | Поисковые подсказки |
| `yandexRegions($params)` | `/yandex/regions` | Справочник регионов, бесплатно |
| `yandexImages($params)` | `/yandex/images` | Поиск по картинкам |
| `yandexVideo($params)` | `/yandex/video` | Поиск по видео |

### Google

| Метод | Путь API | Что делает |
| --- | --- | --- |
| `google($params)` | `/google` | Органическая выдача |
| `googleSuggest($params)` | `/google/suggest` | Подсказки (autocomplete) |
| `googleRegions($params)` | `/google/regions` | Справочник регионов и готовый `uule`, бесплатно |
| `googleImages($params)` | `/google/images` | Поиск по картинкам |
| `googleVideo($params)` | `/google/video` | Поиск по видео |

### Bing

| Метод | Путь API | Что делает |
| --- | --- | --- |
| `bing($params)` | `/bing` | Органическая выдача |
| `bingSuggest($params)` | `/bing/suggest` | Подсказки |
| `bingImages($params)` | `/bing/images` | Поиск по картинкам |
| `bingVideo($params)` | `/bing/video` | Поиск по видео |

### Вордстат, Директ и служебные

| Метод | Путь API | Что делает |
| --- | --- | --- |
| `wordstat($params)` | `/wordstat` | Популярные и похожие запросы |
| `wordstatFrequency($params)` | `/wordstat/frequency` | Частота запроса одним числом |
| `wordstatGraph($params)` | `/wordstat/graph` | Динамика по месяцам, неделям или дням |
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

## Как SDK помогает с параметрами

**Списки передаются массивами.** Фразы для Директа склеиваются переводом строки, остальные списки — запятой:

```php
$client->direct(['ремонт айфона', 'ремонт телефона', 'замена экрана']);
$client->wordstat(['text' => 'ремонт', 'region' => [213, 2], 'device' => ['desktop', 'phone']]);
```

**Флаги принимаются флагами.** `true` и `false` уезжают как `1` и `0`:

```php
$client->yandex(['text' => 'купить ноутбук', 'ai' => true, 'ads' => true]);
```

**`null` не отправляется.** Необязательный параметр, который вы ещё не посчитали, можно не вычищать из массива руками.

## Ошибки

Всё, что бросает SDK, наследуется от `JsonSeo\Exception\JsonSeoException`.

| Исключение | Статус | Когда |
| --- | --- | --- |
| `ValidationException` | 422 | Параметры не приняты. `errors()` вернёт сообщения по полям |
| `UnauthorizedException` | 403 | Ключ не передан или недействителен |
| `PaymentRequiredException` | 402 | На счёте не хватает средств |
| `RateLimitException` | 429 | Превышен лимит частоты |
| `ServiceUnavailableException` | 503 | Выдачу получить не вышло. Деньги не списаны |
| `ApiException` | прочие | Любой другой отказ сервиса |

У всех отказов сервиса есть `status()`, `body()`, разобранный `payload()` и `retryAfter()` — срок, который назвал сервис, если он его назвал.

| Исключение | Статус | Когда |
| --- | --- | --- |
| `TransportException` | — | До сервиса не достучались: сеть, DNS, TLS |
| `TimeoutException` | — | Ответа не дождались за отведённое время (наследник `TransportException`) |
| `IncompleteResponseException` | — | Соединение оборвалось посреди тела (наследник `TransportException`) |
| `InvalidArgumentException` | — | SDK забраковал аргументы, запрос не отправлялся |

```php
use JsonSeo\Exception\PaymentRequiredException;
use JsonSeo\Exception\ValidationException;

try {
    $serp = $client->yandex(['text' => 'купить ноутбук', 'pages' => 50]);
} catch (ValidationException $e) {
    foreach ($e->errors() as $field => $messages) {
        echo $field . ': ' . implode(', ', $messages) . PHP_EOL;
    }
} catch (PaymentRequiredException $e) {
    echo 'Баланс кончился: ' . $client->balance()['balance'] . PHP_EOL;
}
```

## Повторы

`429`, `5xx` и обрывы связи повторяются автоматически — это ровно те отказы, за которые сервис денег не берёт. Отказы по ключу, балансу и параметрам не повторяются: сами они не изменятся.

Таймаут и оборвавшееся посреди тела соединение не повторяются, и это намеренно: работу на стороне сервиса обрыв у клиента не отменяет — выдача будет собрана и оплачена, а повтор стоил бы ещё раз. Если ответ не успевает прийти, поднимайте `timeout`, а не `retries`.

Пауза между попытками удваивается и разбавляется случайной добавкой. Если сервис прислал `Retry-After`, SDK не вернётся раньше названного срока: проснуться раньше — значит гарантированно получить тот же отказ. Когда сервис просит ждать дольше `max_retry_delay`, SDK не ждёт вовсе, а отдаёт исключение с `retryAfter()` — решение остаётся за вами.

```php
$client = new JsonSeo\Client('ВАШ_КЛЮЧ', [
    'retries' => 5,
    'retry_delay' => 2.0,
    'max_retry_delay' => 60.0,
]);
```

## Настройки клиента

```php
$client = new JsonSeo\Client('ВАШ_КЛЮЧ', [
    'base_url' => 'https://jsonseo.ru/api', // адрес API
    'timeout' => 300.0,                     // сколько ждать ответа на попытку, секунд
    'connect_timeout' => 10.0,              // сколько ждать соединения, секунд
    'retries' => 2,                         // сколько раз повторять временный отказ
    'retry_delay' => 1.0,                   // стартовая пауза между попытками
    'max_retry_delay' => 30.0,              // потолок паузы
    'auth' => JsonSeo\Client::AUTH_HEADER,  // или AUTH_QUERY — ключ в параметре key
    'user_agent' => 'мой-проект/1.0',
    'transport' => $transport,              // свой JsonSeo\Transport\TransportInterface
]);
```

Таймаут по умолчанию намеренно большой: многостраничный запрос выдачи собирается минутами, и обрыв на стороне клиента не отменяет запрос на стороне сервиса — деньги за него уже списаны. Считается он на **каждую попытку** отдельно, а не на весь вызов.

Ключ по умолчанию едет в заголовке `Authorization: Bearer`, а не в адресе: так он не оседает в логах прокси и серверов. `AUTH_QUERY` нужен там, где заголовки до API не доходят.

## Свой транспорт

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

## Разработка

```bash
composer install
composer test
```

Тесты идут без сети: транспорт подменяется заглушкой.

## Лицензия

MIT.
