<?php

namespace JsonSeo\Tests;

use JsonSeo\Client;
use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\PaymentRequiredException;
use JsonSeo\Exception\RateLimitException;
use JsonSeo\Exception\ServiceUnavailableException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;
use JsonSeo\Exception\UnauthorizedException;
use JsonSeo\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class RetryTest extends TestCase
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
     * Паузы обнулены: проверяется решение о повторе, а не длительность сна.
     *
     * @param  array<string, mixed>  $options
     * @return Client
     */
    private function client(array $options = [])
    {
        return new Client('KEY', array_merge([
            'transport' => $this->transport,
            'retry_delay' => 0.0,
            'max_retry_delay' => 0.0,
        ], $options));
    }

    public function test_temporary_failure_is_retried(): void
    {
        $this->transport
            ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
            ->queueJson(['results' => ['ok']]);

        $serp = $this->client()->yandex('тест');

        self::assertSame(['ok'], $serp['results']);
        self::assertSame(2, $this->transport->count());
    }

    /** Без Retry-After пауза берётся из бэкоффа. */
    public function test_rate_limit_is_retried(): void
    {
        $this->transport
            ->queueJson(['message' => 'Too Many Attempts.'], 429)
            ->queueJson(['results' => []]);

        $this->client()->yandex('тест');

        self::assertSame(2, $this->transport->count());
    }

    public function test_broken_connection_is_retried(): void
    {
        $this->transport
            ->queueFailure('соединение оборвано')
            ->queueJson(['results' => []]);

        $this->client()->yandex('тест');

        self::assertSame(2, $this->transport->count());
    }

    /** По умолчанию у запроса три попытки: одна основная и две повторных. */
    public function test_three_attempts_by_default(): void
    {
        $this->transport
            ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
            ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
            ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
            ->queueJson(['results' => ['лишний']]);

        $this->expectException(ServiceUnavailableException::class);

        try {
            // Настройку не трогаем: проверяется именно значение по умолчанию.
            $client = new Client('KEY', [
                'transport' => $this->transport,
                'retry_delay' => 0.0,
                'max_retry_delay' => 0.0,
            ]);

            $client->yandex('тест');
        } finally {
            self::assertSame(3, $this->transport->count());
        }
    }

    /** Затупивший сервис успевает ответить с третьей попытки. */
    public function test_slow_service_succeeds_on_a_later_attempt(): void
    {
        $this->transport
            ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
            ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
            ->queueJson(['results' => ['ok']]);

        $serp = $this->client()->yandex('тест');

        self::assertSame(['ok'], $serp['results']);
        self::assertSame(3, $this->transport->count());
    }

    /** Ноль попыток означал бы «не отправлять запрос» — подрезаем до одной. */
    public function test_attempts_are_never_below_one(): void
    {
        foreach ([0, -5] as $configured) {
            $transport = new FakeTransport;
            $transport
                ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
                ->queueJson(['results' => []]);

            $client = new Client('KEY', [
                'transport' => $transport,
                'attempts' => $configured,
                'retry_delay' => 0.0,
                'max_retry_delay' => 0.0,
            ]);

            try {
                $client->yandex('тест');
                self::fail('Ожидался отказ сервиса');
            } catch (ServiceUnavailableException $exception) {
                self::assertSame(1, $transport->count(), 'attempts = '.$configured.' должно давать один запрос');
            }
        }
    }

    public function test_attempts_stop_at_the_configured_limit(): void
    {
        $this->transport
            ->queueJson(['message' => 'Too Many Attempts.'], 429)
            ->queueJson(['message' => 'Too Many Attempts.'], 429)
            ->queueJson(['message' => 'Too Many Attempts.'], 429);

        $this->expectException(RateLimitException::class);

        try {
            $this->client(['attempts' => 3])->yandex('тест');
        } finally {
            self::assertSame(3, $this->transport->count());
        }
    }

    public function test_validation_error_is_not_retried(): void
    {
        $this->transport->queueJson(['message' => 'Введите запрос'], 422);

        try {
            $this->client()->yandex('');
            self::fail('Ожидалась ошибка валидации');
        } catch (ValidationException $exception) {
            self::assertSame(1, $this->transport->count());
        }
    }

    public function test_missing_funds_are_not_retried(): void
    {
        $this->transport->queueJson(['message' => 'Недостаточно средств.'], 402);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидалась ошибка баланса');
        } catch (PaymentRequiredException $exception) {
            self::assertSame(1, $this->transport->count());
        }
    }

    public function test_server_errors_are_retried(): void
    {
        foreach ([500, 502, 504] as $status) {
            $transport = new FakeTransport;
            $transport
                ->queueJson(['message' => 'Ошибка'], $status)
                ->queueJson(['results' => []]);

            $client = new Client('KEY', [
                'transport' => $transport,
                'retry_delay' => 0.0,
                'max_retry_delay' => 0.0,
            ]);

            $client->yandex('тест');

            self::assertSame(2, $transport->count(), 'статус '.$status.' должен повторяться');
        }
    }

    public function test_bad_key_is_not_retried(): void
    {
        $this->transport->queueJson(['message' => 'Недействительный токен авторизации.'], 403);

        try {
            $this->client()->yandex('тест');
            self::fail('Ожидалась ошибка авторизации');
        } catch (UnauthorizedException $exception) {
            self::assertSame(1, $this->transport->count());
        }
    }

    /** Проснуться раньше названного срока — снова получить тот же отказ. */
    public function test_retry_after_sets_the_pause(): void
    {
        $this->transport
            ->queueJson(['message' => 'Too Many Attempts.'], 429, ['retry-after' => '1'])
            ->queueJson(['results' => []]);

        $started = microtime(true);
        $this->client(['max_retry_delay' => 30.0])->yandex('тест');
        $elapsed = microtime(true) - $started;

        self::assertSame(2, $this->transport->count());
        self::assertGreaterThanOrEqual(1.0, $elapsed);
    }

    /** Дольше потолка SDK не ждёт: отдаёт ошибку с retryAfter(). */
    public function test_retry_after_beyond_the_cap_stops_retrying(): void
    {
        $this->transport
            ->queueJson(['message' => 'Too Many Attempts.'], 429, ['retry-after' => '600'])
            ->queueJson(['results' => []]);

        try {
            $this->client(['max_retry_delay' => 30.0])->yandex('тест');
            self::fail('Ожидался отказ по лимиту');
        } catch (RateLimitException $exception) {
            self::assertSame(1, $this->transport->count());
            self::assertSame(600, $exception->retryAfter());
        }
    }

    /** Ровно потолок — ещё в пределах: сравнение строгое. */
    public function test_retry_after_equal_to_the_cap_still_retries(): void
    {
        $this->transport
            ->queueJson(['message' => 'Too Many Attempts.'], 429, ['retry-after' => '1'])
            ->queueJson(['results' => []]);

        $this->client(['max_retry_delay' => 1.0])->yandex('тест');

        self::assertSame(2, $this->transport->count());
    }

    /** RFC 9110 разрешает и HTTP-дату. */
    public function test_retry_after_accepts_an_http_date(): void
    {
        $this->transport->queueJson(
            ['message' => 'Too Many Attempts.'],
            429,
            ['retry-after' => gmdate('D, d M Y H:i:s \G\M\T', time() + 600)]
        );

        try {
            $this->client(['max_retry_delay' => 30.0])->yandex('тест');
            self::fail('Ожидался отказ по лимиту');
        } catch (RateLimitException $exception) {
            self::assertGreaterThan(500, $exception->retryAfter());
        }
    }

    public function test_service_unavailable_also_keeps_retry_after(): void
    {
        $this->transport->queueJson(['message' => 'Сервис временно недоступен.'], 503, ['retry-after' => '120']);

        try {
            $this->client(['max_retry_delay' => 30.0])->yandex('тест');
            self::fail('Ожидался отказ сервиса');
        } catch (ServiceUnavailableException $exception) {
            self::assertSame(120, $exception->retryAfter());
        }
    }

    public function test_repeated_request_carries_the_same_body(): void
    {
        $this->transport
            ->queueJson(['message' => 'Сервис временно недоступен.'], 503)
            ->queueJson(['results' => []]);

        $this->client()->yandex(['text' => 'купить ноутбук', 'pages' => 3]);

        self::assertSame(
            $this->transport->requests[0]['body'],
            $this->transport->requests[1]['body']
        );
    }

    public function test_timeout_is_not_retried(): void
    {
        $this->transport
            ->queueTimeout('ответа не дождались')
            ->queueJson(['results' => []]);

        $this->expectException(TimeoutException::class);

        try {
            $this->client()->yandex('тест');
        } finally {
            self::assertSame(1, $this->transport->count());
        }
    }

    /** Обрыв на отдаче не повторяется: выдача уже оплачена. */
    public function test_incomplete_response_is_not_retried(): void
    {
        $this->transport
            ->queueIncomplete('пришло меньше обещанного')
            ->queueJson(['results' => []]);

        $this->expectException(IncompleteResponseException::class);

        try {
            $this->client()->yandex('тест');
        } finally {
            self::assertSame(1, $this->transport->count());
        }
    }

    public function test_a_single_attempt_means_no_retries(): void
    {
        $this->transport->queueFailure('сеть недоступна');

        $this->expectException(TransportException::class);

        try {
            $this->client(['attempts' => 1])->yandex('тест');
        } finally {
            self::assertSame(1, $this->transport->count());
        }
    }
}
