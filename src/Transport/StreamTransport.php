<?php

namespace JsonSeo\Transport;

use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;

/**
 * Запасной транспорт для хостинга без ext-curl. Возможностей меньше: общий
 * таймаут вместо раздельных и текст ошибки вместо кода.
 */
class StreamTransport implements TransportInterface
{
    /** Сколько читать за раз: ответ выдачи — сотни килобайт. */
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
        // Без этого сервер держал бы сокет открытым, и чтение до конца
        // потока упиралось бы в таймаут на каждом успешном ответе.
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
                // Иначе 4xx и 5xx приходят как ошибка, без тела и причины.
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $started = microtime(true);

        // Иначе в текст ошибки попадёт чужое предупреждение.
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

        // Читаем сами: file_get_contents на таймауте отдаёт прочитанное
        // без признака обрыва, и огрызок выдачи выглядел бы успешным.
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
     * Соединения нет или заголовки не пришли. Различает их только время:
     * выбранный до конца бюджет значит, что сервис принял запрос и уже
     * считает выдачу. По тексту судить нельзя — «Connection timed out» от
     * ядра приходит и на неустановленном соединении, за которое не платят.
     *
     * @param  float  $started
     * @param  float  $timeout
     * @return TransportException
     */
    private function connectionFailure($started, $timeout)
    {
        $error = error_get_last();
        $message = isset($error['message']) ? $error['message'] : 'соединение не установлено';

        if (microtime(true) - $started >= $timeout * 0.95) {
            return new TimeoutException('Ответа от JSON SEO API не дождались: '.$message.'.');
        }

        return new TransportException('Запрос к JSON SEO API не удался: '.$message.'.');
    }

    /**
     * Сколько байт тела обещал сервис. null — проверить полноту нечем.
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
     * Стартовых строк бывает несколько из-за прокси: берём последнюю.
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
                // Заголовки предыдущего ответа в цепочке не нужны.
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
