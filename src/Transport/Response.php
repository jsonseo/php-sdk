<?php

namespace JsonSeo\Transport;

/**
 * Сырой ответ транспорта: статус, заголовки и тело как есть.
 *
 * Разбор тела живёт в клиенте, а не здесь: не всякий ответ — JSON,
 * и транспорт не должен знать, чего от него ждут.
 */
class Response
{
    /**
     * @var int
     */
    private $status;

    /**
     * Имена заголовков хранятся в нижнем регистре: HTTP их регистр не
     * различает, а искать по точному совпадению надёжнее, чем перебирать.
     *
     * @var array<string, string>
     */
    private $headers;

    /**
     * @var string
     */
    private $body;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct($status, array $headers, $body)
    {
        $this->status = (int) $status;
        // Регистр приводится здесь, а не в транспортах: свой транспорт пишут
        // под интерфейс, и Retry-After с заглавных букв не должен теряться.
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = (string) $body;
    }

    public function status()
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * @param  string  $name
     * @return string|null
     */
    public function header($name)
    {
        $name = strtolower($name);

        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    public function body()
    {
        return $this->body;
    }
}
