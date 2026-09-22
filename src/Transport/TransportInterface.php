<?php

namespace JsonSeo\Transport;

use JsonSeo\Exception\TransportException;

/**
 * Как SDK ходит в сеть. Подменяется в тестах и в проектах со своим
 * HTTP-клиентом — достаточно объекта с этим методом.
 */
interface TransportInterface
{
    /**
     * @param  string  $method  HTTP-метод в верхнем регистре
     * @param  string  $url  Полный адрес запроса
     * @param  array<string, string>  $headers  Заголовки вида ['Content-Type' => '...']
     * @param  string|null  $body  Тело запроса, уже закодированное
     * @param  array{timeout?: float, connect_timeout?: float}  $options
     * @return Response
     *
     * @throws TransportException Сеть недоступна, таймаут, оборванное соединение
     */
    public function send($method, $url, array $headers, $body, array $options);
}
