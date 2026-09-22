<?php

namespace JsonSeo\Transport;

use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;

/**
 * Запасной транспорт на потоках PHP — для хостинга, где ext-curl не собран.
 *
 * Возможностей меньше: отдельного таймаута на установку соединения у потоков
 * нет, действует один общий, и кода ошибки они не отдают — только текст.
 * Поэтому по умолчанию берётся curl, а этот класс подставляется, только
 * когда curl недоступен.
 */
class StreamTransport implements TransportInterface
{
    /**
     * Сколько читать за раз. Ответ выдачи — сотни килобайт, и посимвольное
     * чтение на нём заметно дороже.
     */
    const CHUNK = 16384;

    public function __construct()
    {
        if (! ini_get('allow_url_fopen')) {
            throw new TransportException('Для потокового транспорта нужен включённый allow_url_fopen. Установите ext-curl или передайте свой транспорт в опции transport.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send($method, $url, array $headers, $body, array $options)
    {
        // Соединение закрывается сразу после ответа. Пул соединений потокам
        // всё равно недоступен, а с keep-alive сервер держал бы сокет
        // открытым, и чтение до конца потока упиралось бы в таймаут на
        // каждом успешном ответе.
        $headers['Connection'] = 'close';

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }

        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : 300.0;

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $lines),
                'content' => $body === null ? '' : $body,
                'timeout' => $timeout,
                'follow_location' => 0,
                // Без этого ответы 4xx и 5xx приходят как ошибка, и сообщение
                // сервиса о причине отказа теряется вместе с телом.
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $started = microtime(true);

        // Чужое предупреждение из другого места программы иначе попало бы в
        // текст ошибки как причина отказа.
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }

        $handle = @fopen($url, 'rb', false, $context);

        if ($handle === false) {
            throw $this->connectionFailure($started, $timeout);
        }

        $meta = stream_get_meta_data($handle);
        stream_set_timeout($handle, (int) $timeout, (int) (fmod($timeout, 1.0) * 1000000));

        $expected = $this->contentLength($meta);
        $responseBody = '';
        $timedOut = false;

        // Читаем сами, а не через file_get_contents: тот на исчерпании
        // таймаута отдаёт прочитанное — без ошибки и без единого признака
        // обрыва, и обрезанная выдача уходила бы наверх как успешная, хотя
        // клиент за неё уже заплатил.
        while (! feof($handle)) {
            if ($expected !== null && strlen($responseBody) >= $expected) {
                break;
            }

            $chunk = fread($handle, self::CHUNK);

            $info = stream_get_meta_data($handle);

            if (! empty($info['timed_out'])) {
                $timedOut = true;

                break;
            }

            if ($chunk === false || $chunk === '') {
                break;
            }

            $responseBody .= $chunk;
        }

        fclose($handle);

        if ($timedOut) {
            throw new TimeoutException('Ответа от JSON SEO API не дождались: тело ответа пришло не целиком.');
        }

        if ($expected !== null && strlen($responseBody) < $expected) {
            throw new IncompleteResponseException(
                'Ответ от JSON SEO API пришёл не целиком: обещано '.$expected.' байт, получено '.strlen($responseBody).'.'
            );
        }

        return new Response($this->statusFrom($meta), $this->headersFrom($meta), $responseBody);
    }

    /**
     * Соединения нет или заголовки так и не пришли.
     *
     * Потоки не отдают кода ошибки, и по тексту эти случаи не различить:
     * на ожидании заголовков PHP пишет просто «HTTP request failed!».
     * Зато их различает время: если бюджет выбран до конца, сервис успел
     * принять запрос и уже считает выдачу — повторять такое нельзя.
     *
     * @param  float  $started
     * @param  float  $timeout
     * @return TransportException
     */
    private function connectionFailure($started, $timeout)
    {
        $error = error_get_last();
        $message = isset($error['message']) ? $error['message'] : 'соединение не установлено';
        $elapsed = microtime(true) - $started;

        if ($elapsed >= $timeout * 0.95
            || stripos($message, 'timed out') !== false
            || stripos($message, 'timeout') !== false) {
            return new TimeoutException('Ответа от JSON SEO API не дождались: '.$message.'.');
        }

        return new TransportException('Запрос к JSON SEO API не удался: '.$message.'.');
    }

    /**
     * Сколько байт тела обещал сервис. null, если заголовка нет — тогда
     * полноту проверить нечем и читается весь поток до конца.
     *
     * @param  array<string, mixed>  $meta
     * @return int|null
     */
    private function contentLength(array $meta)
    {
        $headers = $this->headersFrom($meta);

        if (! isset($headers['content-length']) || ! preg_match('/^\d+$/', $headers['content-length'])) {
            return null;
        }

        return (int) $headers['content-length'];
    }

    /**
     * Стартовых строк может быть несколько, если в цепочке стоит прокси:
     * берётся последняя — она от конечного ответа.
     *
     * @param  array<string, mixed>  $meta
     * @return int
     */
    private function statusFrom(array $meta)
    {
        $status = 0;

        foreach ($this->wrapperData($meta) as $line) {
            if (strncasecmp($line, 'HTTP/', 5) === 0) {
                $parts = explode(' ', $line, 3);
                $status = isset($parts[1]) ? (int) $parts[1] : 0;
            }
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function headersFrom(array $meta)
    {
        $headers = [];

        foreach ($this->wrapperData($meta) as $line) {
            if (strncasecmp($line, 'HTTP/', 5) === 0) {
                // Заголовки предыдущего ответа в цепочке к делу не относятся.
                $headers = [];

                continue;
            }

            $separator = strpos($line, ':');

            if ($separator !== false) {
                $name = strtolower(trim(substr($line, 0, $separator)));
                $headers[$name] = trim(substr($line, $separator + 1));
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int, string>
     */
    private function wrapperData(array $meta)
    {
        return isset($meta['wrapper_data']) && is_array($meta['wrapper_data']) ? $meta['wrapper_data'] : [];
    }
}
