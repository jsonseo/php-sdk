<?php

namespace JsonSeo\Transport;

use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;

/**
 * Транспорт на ext-curl. Основной: только он умеет раздельные таймауты на
 * соединение и на ответ, а методы выдачи отвечают долго, и путать «сервер не
 * отзывается» с «запрос ещё считается» нельзя.
 *
 * Дескриптор переиспользуется между запросами: у соединения с jsonseo.ru
 * полноценный TLS-хендшейк, и на серии запросов keep-alive экономит больше,
 * чем стоит всё остальное в SDK.
 */
class CurlTransport implements TransportInterface
{
    /**
     * @var resource|\CurlHandle|null
     */
    private $handle;

    public function __construct()
    {
        if (! function_exists('curl_init')) {
            throw new TransportException('Расширение ext-curl не установлено. Передайте другой транспорт в опции transport.');
        }
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send($method, $url, array $headers, $body, array $options)
    {
        $handle = $this->handle();
        $responseHeaders = [];

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name.': '.$value;
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_CONNECTTIMEOUT_MS => $this->milliseconds($options, 'connect_timeout', 10.0),
            CURLOPT_TIMEOUT_MS => $this->milliseconds($options, 'timeout', 300.0),
            CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$responseHeaders) {
                $length = strlen($line);
                $separator = strpos($line, ':');

                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($line, $separator + 1));
                }

                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $message = curl_error($handle);
            $code = curl_errno($handle);
            $description = $message !== '' ? $message : 'ошибка curl '.$code;

            // Оборвавшееся посреди тела соединение (код 18) повторять нельзя:
            // выдача уже собрана и оплачена, обрыв случился на отдаче.
            if ($code === CURLE_PARTIAL_FILE) {
                throw new IncompleteResponseException('Ответ от JSON SEO API пришёл не целиком: '.$description.'.', $code);
            }

            // Код 28 curl ставит и на таймауте соединения, и на таймауте
            // ответа, а это разные вещи: пока соединение не установлено,
            // запрос до сервиса не дошёл и ничего не списано — такой отказ
            // надо повторять.
            //
            // Различает их время до начала передачи. Время установки
            // соединения для этого не годится: на переиспользованном сокете
            // оно тоже нулевое, а дескриптор здесь живёт между запросами
            // ради keep-alive — и настоящий таймаут ответа второго запроса
            // выглядел бы как неудачное соединение.
            $connected = (float) curl_getinfo($handle, CURLINFO_PRETRANSFER_TIME) > 0.0;

            if ($code === CURLE_OPERATION_TIMEOUTED && $connected) {
                throw new TimeoutException('Ответа от JSON SEO API не дождались: '.$description.'.', $code);
            }

            throw new TransportException('Запрос к JSON SEO API не удался: '.$description.'.', $code);
        }

        return new Response(curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $responseHeaders, $responseBody);
    }

    /**
     * @return resource|\CurlHandle
     */
    private function handle()
    {
        if ($this->handle === null) {
            $this->handle = curl_init();
        } else {
            // Сбрасываем настройки прошлого запроса, но не соединение: curl
            // держит открытый сокет в своём пуле и переиспользует его сам.
            curl_reset($this->handle);
        }

        return $this->handle;
    }

    /**
     * @param  array{timeout?: float, connect_timeout?: float}  $options
     * @param  string  $key
     * @param  float  $default
     * @return int
     */
    private function milliseconds(array $options, $key, $default)
    {
        $seconds = isset($options[$key]) ? (float) $options[$key] : $default;

        return (int) round($seconds * 1000);
    }
}
