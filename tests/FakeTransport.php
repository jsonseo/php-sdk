<?php

namespace JsonSeo\Tests;

use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;
use JsonSeo\Transport\Response;
use JsonSeo\Transport\TransportInterface;

/**
 * Транспорт-заглушка: отдаёт заранее сложенные ответы и запоминает, что у
 * него просили. Позволяет проверять сборку запроса и разбор ответа, не
 * обращаясь к сети.
 */
class FakeTransport implements TransportInterface
{
    /**
     * @var array<int, Response|TransportException>
     */
    private $queue = [];

    /**
     * @var array<int, array{method: string, url: string, headers: array<string, string>, body: string|null, options: array<string, mixed>}>
     */
    public $requests = [];

    /**
     * @param  array<string, mixed>|string  $body
     * @param  int  $status
     * @param  array<string, string>  $headers
     * @return $this
     */
    public function queueJson($body, $status = 200, array $headers = [])
    {
        $encoded = is_string($body) ? $body : json_encode($body);
        $this->queue[] = new Response($status, $headers, $encoded);

        return $this;
    }

    /**
     * @param  string  $body
     * @param  int  $status
     * @param  array<string, string>  $headers
     * @return $this
     */
    public function queueRaw($body, $status = 200, array $headers = [])
    {
        $this->queue[] = new Response($status, $headers, $body);

        return $this;
    }

    /**
     * @param  string  $message
     * @return $this
     */
    public function queueFailure($message = 'соединение оборвано')
    {
        $this->queue[] = new TransportException($message);

        return $this;
    }

    /**
     * @param  string  $message
     * @return $this
     */
    public function queueTimeout($message = 'ответа не дождались')
    {
        $this->queue[] = new TimeoutException($message);

        return $this;
    }

    /**
     * @param  string  $message
     * @return $this
     */
    public function queueIncomplete($message = 'ответ пришёл не целиком')
    {
        $this->queue[] = new IncompleteResponseException($message);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function send($method, $url, array $headers, $body, array $options)
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'options' => $options,
        ];

        if ($this->queue === []) {
            throw new TransportException('В очереди заглушки не осталось ответов, а запрос пришёл.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof TransportException) {
            throw $next;
        }

        return $next;
    }

    /**
     * Разобранное тело запроса под номером $index.
     *
     * @param  int  $index
     * @return array<string, string>
     */
    public function sentParams($index = 0)
    {
        $params = [];
        parse_str($this->requests[$index]['body'], $params);

        return $params;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->requests);
    }
}
