<?php

namespace JsonSeo\Tests;

use JsonSeo\Client;
use JsonSeo\Exception\ApiException;
use JsonSeo\Exception\PaymentRequiredException;
use JsonSeo\Exception\RateLimitException;
use JsonSeo\Exception\ServiceUnavailableException;
use JsonSeo\Exception\UnauthorizedException;
use JsonSeo\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class ErrorsTest extends TestCase
{
    /**
     * @var FakeTransport
     */
    private $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport;
    }

    /**
     * Повторы выключены: здесь проверяется разбор отказа, а не поведение
     * при нём.
     *
     * @return Client
     */
    private function client()
    {
        return new Client('KEY', ['transport' => $this->transport, 'attempts' => 1]);
    }

    public function test_missing_funds_raise_payment_required(): void
    {
        $this->transport->queueJson(['message' => 'На аккаунте недостаточно средств для завершения запроса.'], 402);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидалось исключение о нехватке средств');
        } catch (PaymentRequiredException $exception) {
            self::assertSame(402, $exception->status());
            self::assertSame('На аккаунте недостаточно средств для завершения запроса.', $exception->getMessage());
        }
    }

    public function test_bad_key_raises_unauthorized(): void
    {
        $this->transport->queueJson(['message' => 'Недействительный токен авторизации.'], 403);

        $this->expectException(UnauthorizedException::class);

        $this->client()->yandex('тест');
    }

    public function test_validation_error_carries_field_messages(): void
    {
        $this->transport->queueJson([
            'message' => 'Введите запрос',
            'errors' => ['text' => ['Введите запрос']],
        ], 422);

        try {
            $this->client()->yandex('');
            self::fail('Ожидалась ошибка валидации');
        } catch (ValidationException $exception) {
            self::assertSame(['text' => ['Введите запрос']], $exception->errors());
            self::assertSame(['text'], $exception->fields());
        }
    }

    public function test_validation_error_without_fields_returns_empty_list(): void
    {
        $this->transport->queueJson(['message' => 'Что-то не так'], 422);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидалась ошибка валидации');
        } catch (ValidationException $exception) {
            self::assertSame([], $exception->errors());
        }
    }

    public function test_rate_limit_keeps_retry_after(): void
    {
        $this->transport->queueJson(['message' => 'Too Many Attempts.'], 429, ['retry-after' => '17']);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидался отказ по лимиту');
        } catch (RateLimitException $exception) {
            self::assertSame(17, $exception->retryAfter());
        }
    }

    public function test_rate_limit_without_header_has_no_retry_after(): void
    {
        $this->transport->queueJson(['message' => 'Too Many Attempts.'], 429);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидался отказ по лимиту');
        } catch (RateLimitException $exception) {
            self::assertNull($exception->retryAfter());
        }
    }

    public function test_failed_search_raises_service_unavailable(): void
    {
        $this->transport->queueJson(['message' => 'Сервис временно недоступен. Пожалуйста, повторите попытку позже.'], 503);

        $this->expectException(ServiceUnavailableException::class);

        $this->client()->yandex('тест');
    }

    public function test_unknown_status_still_raises_api_exception(): void
    {
        $this->transport->queueJson(['message' => 'Чайник'], 418);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидалась ошибка API');
        } catch (ApiException $exception) {
            self::assertSame(418, $exception->status());
            self::assertSame('Чайник', $exception->getMessage());
        }
    }

    public function test_non_json_error_body_keeps_status_and_text(): void
    {
        $this->transport->queueRaw('<html>502 Bad Gateway</html>', 502);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидалась ошибка API');
        } catch (ApiException $exception) {
            self::assertSame(502, $exception->status());
            self::assertSame('<html>502 Bad Gateway</html>', $exception->body());
            self::assertSame([], $exception->payload());
            self::assertSame('JSON SEO API вернул ошибку 502.', $exception->getMessage());
        }
    }
}
