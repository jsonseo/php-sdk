<?php

namespace JsonSeo;

use JsonSeo\Api\AccountMethods;
use JsonSeo\Api\BingMethods;
use JsonSeo\Api\GoogleMethods;
use JsonSeo\Api\WordstatMethods;
use JsonSeo\Api\YandexMethods;
use JsonSeo\Exception\ApiException;
use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\InvalidArgumentException;
use JsonSeo\Exception\JsonSeoException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;
use JsonSeo\Transport\CurlTransport;
use JsonSeo\Transport\Response;
use JsonSeo\Transport\StreamTransport;
use JsonSeo\Transport\TransportInterface;

/**
 * Клиент JSON SEO API.
 *
 * Сами методы живут в трейтах по поисковикам — здесь только то, что у них
 * общее: ключ, транспорт, подготовка параметров, повторы и разбор ответа.
 *
 * <code>
 * $client = new JsonSeo\Client('ВАШ_КЛЮЧ');
 * $serp = $client->yandex(['text' => 'купить ноутбук', 'region' => 213]);
 * </code>
 */
class Client
{
    use AccountMethods;
    use BingMethods;
    use GoogleMethods;
    use WordstatMethods;
    use YandexMethods;

    const VERSION = '1.0.0';

    const DEFAULT_BASE_URL = 'https://jsonseo.ru/api';

    /**
     * Ключ в заголовке Authorization. По умолчанию именно так: в параметре
     * запроса ключ оседает в логах прокси и серверов.
     */
    const AUTH_HEADER = 'header';

    /**
     * Ключ в параметре key. Нужен там, где заголовки в запрос не пробрасываются.
     */
    const AUTH_QUERY = 'query';

    /**
     * @var array<int, string>
     */
    private static $knownOptions = [
        'base_url', 'timeout', 'connect_timeout', 'retries',
        'retry_delay', 'max_retry_delay', 'auth', 'user_agent', 'transport',
    ];

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var float
     */
    private $timeout;

    /**
     * @var float
     */
    private $connectTimeout;

    /**
     * @var int
     */
    private $retries;

    /**
     * @var float
     */
    private $retryDelay;

    /**
     * @var float
     */
    private $maxRetryDelay;

    /**
     * @var string
     */
    private $auth;

    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var TransportInterface|null
     */
    private $transport;

    /**
     * @param  string  $apiKey  Ключ из личного кабинета на jsonseo.ru
     * @param array{
     *     base_url?: string,
     *     timeout?: float,
     *     connect_timeout?: float,
     *     retries?: int,
     *     retry_delay?: float,
     *     max_retry_delay?: float,
     *     auth?: string,
     *     user_agent?: string,
     *     transport?: TransportInterface
     * } $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct($apiKey, array $options = [])
    {
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new InvalidArgumentException('Нужен API-ключ: возьмите его в личном кабинете на https://jsonseo.ru.');
        }

        $unknown = array_diff(array_keys($options), self::$knownOptions);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Неизвестные опции клиента: '.implode(', ', $unknown).'. Доступны: '.implode(', ', self::$knownOptions).'.'
            );
        }

        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim(isset($options['base_url']) ? $options['base_url'] : self::DEFAULT_BASE_URL, '/');

        // Методы выдачи собирают страницы последовательно, и многостраничный
        // запрос идёт минутами. Короткий таймаут здесь обрывал бы запрос,
        // который сервис всё равно досчитает и оплатит.
        $this->timeout = isset($options['timeout']) ? (float) $options['timeout'] : 300.0;
        $this->connectTimeout = isset($options['connect_timeout']) ? (float) $options['connect_timeout'] : 10.0;
        $this->retries = isset($options['retries']) ? max(0, (int) $options['retries']) : 2;
        $this->retryDelay = isset($options['retry_delay']) ? (float) $options['retry_delay'] : 1.0;
        $this->maxRetryDelay = isset($options['max_retry_delay']) ? (float) $options['max_retry_delay'] : 30.0;
        $this->userAgent = isset($options['user_agent'])
            ? (string) $options['user_agent']
            : 'jsonseo-php/'.self::VERSION.' php/'.PHP_VERSION;

        $auth = isset($options['auth']) ? (string) $options['auth'] : self::AUTH_HEADER;

        if ($auth !== self::AUTH_HEADER && $auth !== self::AUTH_QUERY) {
            throw new InvalidArgumentException('Опция auth принимает "header" или "query", получено: '.$auth.'.');
        }

        $this->auth = $auth;

        if (isset($options['transport'])) {
            if (! $options['transport'] instanceof TransportInterface) {
                throw new InvalidArgumentException('Опция transport ожидает объект, реализующий JsonSeo\Transport\TransportInterface.');
            }

            $this->transport = $options['transport'];
        }
    }

    /**
     * Произвольный метод API — на случай, если в сервисе появился новый,
     * а SDK ещё не обновлён.
     *
     * @param  string  $path  Путь после /api, например "yandex" или "wordstat/graph"
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ApiException
     * @throws TransportException
     */
    public function call($path, array $params = [])
    {
        return $this->request($path, $params);
    }

    /**
     * То же, но ответ возвращается строкой без разбора — для методов, чей
     * ответ не JSON.
     *
     * @param  string  $path
     * @param  array<string, mixed>  $params
     * @return string
     *
     * @throws ApiException
     * @throws TransportException
     */
    public function callRaw($path, array $params = [])
    {
        return $this->request($path, $params, false);
    }

    /**
     * Выполняет запрос и повторяет его, если отказ временный.
     *
     * Повторяются только те ответы, за которые сервис не берёт денег: 429,
     * 5xx и обрывы связи. Отказы по ключу, балансу и параметрам повторять
     * бессмысленно — они не изменятся сами.
     *
     * @param  string  $path
     * @param  array<string, mixed>  $params
     * @param  bool  $decodeJson
     * @return array<string, mixed>|string
     *
     * @throws ApiException
     * @throws TransportException
     * @throws JsonSeoException
     */
    protected function request($path, array $params, $decodeJson = true)
    {
        $query = $this->normalize($params);

        if ($this->auth === self::AUTH_QUERY) {
            $query['key'] = $this->apiKey;
        }

        $headers = [
            'Accept' => $decodeJson ? 'application/json' : 'application/xml, text/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => $this->userAgent,
        ];

        if ($this->auth === self::AUTH_HEADER) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        // Всегда POST: длинные списки фраз и адреса страниц в GET упираются
        // в предел длины URL, а сервис принимает оба способа одинаково.
        $url = $this->baseUrl.'/'.ltrim($path, '/');
        // Разделитель задаётся явно: без него берётся arg_separator.output из
        // php.ini, а на легаси-хостинге там до сих пор встречается "&amp;" —
        // и тогда все параметры, кроме первого, уезжают с мусорным префиксом.
        $body = http_build_query($query, '', '&');
        $options = ['timeout' => $this->timeout, 'connect_timeout' => $this->connectTimeout];

        $attempt = 0;

        while (true) {
            try {
                $response = $this->transport()->send('POST', $url, $headers, $body, $options);
            } catch (TransportException $exception) {
                // Таймаут и оборвавшееся на середине тело не повторяются:
                // выдача на стороне сервиса уже собрана и оплачена, и второй
                // заход стоил бы ещё раз. Повторяем только те обрывы, при
                // которых запрос до сервиса не дошёл.
                if ($attempt >= $this->retries
                    || $exception instanceof TimeoutException
                    || $exception instanceof IncompleteResponseException) {
                    throw $exception;
                }

                $this->sleep($this->backoff($attempt));
                $attempt++;

                continue;
            }

            $status = $response->status();

            if ($status >= 200 && $status < 300) {
                return $decodeJson ? $this->decode($response->body()) : $response->body();
            }

            $retryAfter = $this->retryAfter($response);
            $error = ApiException::fromStatus(
                $status,
                $response->body(),
                $this->decodeQuietly($response->body()),
                $retryAfter
            );

            // Названный сервисом срок обрезать потолком нельзя: проснувшись
            // раньше, мы гарантированно получим тот же отказ и сожжём попытку
            // впустую. Ждать дольше потолка тоже не станем — отдаём ошибку,
            // в ней есть retryAfter(), и вызывающий решит сам.
            if ($attempt >= $this->retries
                || ! $this->isRetryable($status)
                || ($retryAfter !== null && $retryAfter > $this->maxRetryDelay)) {
                throw $error;
            }

            // Не раньше, чем просит сервис, и не чаще собственного бэкоффа:
            // Retry-After в форме прошедшей даты даёт ноль, и без нижней
            // границы повторы пошли бы вплотную друг за другом.
            $this->sleep($retryAfter === null
                ? $this->backoff($attempt)
                : max((float) $retryAfter, $this->backoff($attempt)));
            $attempt++;
        }
    }

    /**
     * Приводит параметры к тому виду, в каком их ждёт форма запроса.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     *
     * @throws InvalidArgumentException
     */
    protected function normalize(array $params)
    {
        $normalized = [];

        foreach ($params as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $normalized[$name] = $value ? '1' : '0';

                continue;
            }

            if (is_array($value)) {
                // Пустой список — это «параметр не задан», а не «задан пустой
                // строкой»: сервис от region= отказал бы валидацией.
                if ($value === []) {
                    continue;
                }

                $parts = [];

                foreach ($value as $index => $item) {
                    $parts[] = $this->scalar($item, $name.'['.$index.']');
                }

                // Списки фраз склеиваются переводом строки: в самой фразе
                // запятая встречается, перевод строки — нет.
                $normalized[$name] = implode($name === 'phrases' ? "\n" : ',', $parts);

                continue;
            }

            $normalized[$name] = $this->scalar($value, $name);
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @param  string  $name  Имя параметра — попадает в текст ошибки
     * @return string
     *
     * @throws InvalidArgumentException
     */
    protected function scalar($value, $name = '')
    {
        if (is_float($value)) {
            // Через (string) дробная часть до PHP 8 зависела бы от локали:
            // в ru_RU получилась бы запятая, и сервис такого числа не поймёт.
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        if (is_int($value) || is_string($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        throw new InvalidArgumentException(
            ($name === '' ? 'Значение параметра' : 'Значение параметра '.$name)
            .' должно быть строкой, числом, флагом или массивом таких значений.'
        );
    }

    /**
     * Позволяет вызывать метод и одним запросом: yandex('купить ноутбук')
     * вместо yandex(['text' => 'купить ноутбук']).
     *
     * @param  string|array<mixed>  $params
     * @param  string  $key  Имя основного параметра метода
     * @return array<string, mixed>
     */
    protected function withPrimary($params, $key)
    {
        if (is_string($params)) {
            return [$key => $params];
        }

        if (! is_array($params)) {
            throw new InvalidArgumentException('Параметры метода передаются строкой или массивом.');
        }

        // Список без ключей — это значение основного параметра: так удобно
        // передавать фразы в direct().
        if ($params !== [] && array_keys($params) === range(0, count($params) - 1)) {
            return [$key => $params];
        }

        return $params;
    }

    /**
     * @param  string  $body
     * @return array<string, mixed>
     *
     * @throws JsonSeoException
     */
    private function decode($body)
    {
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new JsonSeoException('Ответ JSON SEO API не разобрался как JSON: '.json_last_error_msg().'.');
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Тело ошибки может оказаться и не JSON — страницей от прокси, например.
     * Тогда подробностей просто нет, и это не повод терять сам статус.
     *
     * @param  string  $body
     * @return array<string, mixed>
     */
    private function decodeQuietly($body)
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  int  $status
     * @return bool
     */
    private function isRetryable($status)
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * Сколько секунд просит подождать сервис.
     *
     * RFC 9110 разрешает две формы: число секунд и HTTP-дату. Разбираются обе —
     * иначе на дате retryAfter() оказывался бы пустым, хотя срок назван.
     *
     * @return int|null
     */
    private function retryAfter(Response $response)
    {
        $value = $response->header('retry-after');

        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    /**
     * Пауза удваивается с каждой попыткой и разбавляется случайной добавкой:
     * без неё пачка параллельных запросов вернулась бы одновременно и снова
     * упёрлась бы в тот же лимит.
     *
     * @param  int  $attempt
     * @return float
     */
    private function backoff($attempt)
    {
        $delay = min($this->retryDelay * pow(2, $attempt), $this->maxRetryDelay);

        return $delay + $delay * 0.25 * (mt_rand(0, 1000) / 1000);
    }

    /**
     * @param  float  $seconds
     * @return void
     */
    private function sleep($seconds)
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1000000));
        }
    }

    /**
     * @return TransportInterface
     */
    private function transport()
    {
        if ($this->transport === null) {
            $this->transport = function_exists('curl_init') ? new CurlTransport : new StreamTransport;
        }

        return $this->transport;
    }
}
