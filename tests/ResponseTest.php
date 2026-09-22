<?php

namespace JsonSeo\Tests;

use JsonSeo\Transport\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    /**
     * Регистр имён заголовков HTTP не различает, а свой транспорт пишут под
     * интерфейс и вправе отдать их как угодно: Retry-After с заглавных букв
     * не должен из-за этого потеряться.
     */
    public function test_headers_are_found_regardless_of_case(): void
    {
        $response = new Response(429, ['Retry-After' => '17', 'CONTENT-TYPE' => 'application/json'], '');

        self::assertSame('17', $response->header('retry-after'));
        self::assertSame('17', $response->header('Retry-After'));
        self::assertSame('application/json', $response->header('Content-Type'));
        self::assertSame(['retry-after' => '17', 'content-type' => 'application/json'], $response->headers());
    }

    public function test_missing_header_is_null(): void
    {
        $response = new Response(200, [], '{}');

        self::assertNull($response->header('retry-after'));
    }

    public function test_keeps_status_and_body(): void
    {
        $response = new Response('503', [], 'тело');

        self::assertSame(503, $response->status());
        self::assertSame('тело', $response->body());
    }
}
