<?php

namespace JsonSeo\Transport;

use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;

/**
 * Основной транспорт: только curl умеет раздельные таймауты на соединение и
 * на ответ. Дескриптор переиспользуется — keep-alive экономит TLS-хендшейк.
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
        $code = curl_errno($handle);

        // Судим по коду ошибки, а не по возвращённому значению: на PHP 7.1
        // curl_exec при обрыве отдаёт прочитанный огрызок вместо false, и
        // обрезанное тело ушло бы наверх как успешный ответ.
        if ($code !== CURLE_OK || $responseBody === false) {
            $message = curl_error($handle);
            $description = $message !== '' ? $message : 'ошибка curl '.$code;

            // Код 18 — обрыв на середине тела: выдача уже оплачена.
            if ($code === CURLE_PARTIAL_FILE) {
                throw new IncompleteResponseException('Ответ от JSON SEO API пришёл не целиком: '.$description.'.', $code);
            }

            // Код 28 curl ставит и на таймауте соединения, и на таймауте
            // ответа. Первый ничего не стоил и повторяется. Различает их
            // время до начала передачи: CONNECT_TIME не годится, на
            // переиспользованном сокете он тоже нулевой.
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
            // Сбрасывает настройки, но не соединение: сокет остаётся в пуле.
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
