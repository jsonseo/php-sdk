<?php

namespace JsonSeo\Exception;

/**
 * 422: параметры запроса не приняты. Деньги не списываются.
 */
class ValidationException extends ApiException
{
    /**
     * Ошибки по именам параметров: ['text' => ['Введите запрос']].
     * Пустой массив, если сервис прислал только общее сообщение.
     *
     * @return array<string, array<int, string>>
     */
    public function errors()
    {
        $payload = $this->payload();

        if (! isset($payload['errors']) || ! is_array($payload['errors'])) {
            return [];
        }

        $errors = [];

        foreach ($payload['errors'] as $field => $messages) {
            $errors[$field] = is_array($messages) ? array_values($messages) : [(string) $messages];
        }

        return $errors;
    }

    /**
     * Имена параметров, которые сервис забраковал.
     *
     * @return array<int, string>
     */
    public function fields()
    {
        return array_keys($this->errors());
    }
}
