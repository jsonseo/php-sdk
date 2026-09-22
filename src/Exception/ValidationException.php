<?php

namespace JsonSeo\Exception;

/** 422: параметры не приняты. Деньги не списываются. */
class ValidationException extends ApiException
{
    /**
     * Ошибки по именам параметров: ['text' => ['Введите запрос']].
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
     * Забракованные параметры.
     *
     * @return array<int, string>
     */
    public function fields()
    {
        return array_keys($this->errors());
    }
}
