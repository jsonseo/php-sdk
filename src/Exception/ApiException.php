<?php

namespace JsonSeo\Exception;

/**
 * Сервис ответил, но отказом. Сообщение берётся из поля message ответа,
 * тело сохраняется целиком — в нём бывают подробности, которых нет в тексте.
 */
class ApiException extends JsonSeoException
{
    /**
     * @var int
     */
    private $status;

    /**
     * @var string
     */
    private $body;

    /**
     * @var array<string, mixed>
     */
    private $payload;

    /**
     * @var int|null
     */
    private $retryAfter;

    /**
     * @param  string  $message
     * @param  int  $status
     * @param  string  $body
     * @param  array<string, mixed>  $payload  Разобранный JSON ответа, если он разобрался
     * @param  int|null  $retryAfter  Значение заголовка Retry-After в секундах
     */
    public function __construct($message, $status, $body = '', array $payload = [], $retryAfter = null)
    {
        parent::__construct($message, (int) $status);

        $this->status = (int) $status;
        $this->body = (string) $body;
        $this->payload = $payload;
        $this->retryAfter = $retryAfter === null ? null : (int) $retryAfter;
    }

    /**
     * Через сколько секунд сервис разрешает вернуться. null, если срок не
     * назван. Заголовок приходит не только с 429: им сопровождается и 503.
     *
     * @return int|null
     */
    public function retryAfter()
    {
        return $this->retryAfter;
    }

    /**
     * Собирает исключение того класса, который отвечает за этот HTTP-статус.
     *
     * @param  int  $status
     * @param  string  $body
     * @param  array<string, mixed>  $payload
     * @param  int|null  $retryAfter  Значение заголовка Retry-After в секундах
     * @return self
     */
    public static function fromStatus($status, $body, array $payload, $retryAfter = null)
    {
        $status = (int) $status;
        $message = self::messageFrom($payload, $status);

        switch ($status) {
            case 402:
                return new PaymentRequiredException($message, $status, $body, $payload, $retryAfter);
            case 403:
            case 401:
                return new UnauthorizedException($message, $status, $body, $payload, $retryAfter);
            case 422:
                return new ValidationException($message, $status, $body, $payload, $retryAfter);
            case 429:
                return new RateLimitException($message, $status, $body, $payload, $retryAfter);
            case 503:
                return new ServiceUnavailableException($message, $status, $body, $payload, $retryAfter);
            default:
                return new self($message, $status, $body, $payload, $retryAfter);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  int  $status
     * @return string
     */
    private static function messageFrom(array $payload, $status)
    {
        if (isset($payload['message']) && is_string($payload['message']) && $payload['message'] !== '') {
            return $payload['message'];
        }

        return 'JSON SEO API вернул ошибку '.$status.'.';
    }

    /**
     * @return int
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function body()
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload()
    {
        return $this->payload;
    }
}
