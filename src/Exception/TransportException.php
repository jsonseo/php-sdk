<?php

namespace JsonSeo\Exception;

/**
 * Обмен не состоялся, статуса нет: сеть, DNS, TLS. Наследники уточняют
 * случаи, когда сервис запрос всё же принял.
 */
class TransportException extends JsonSeoException {}
