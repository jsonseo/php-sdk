<?php

namespace JsonSeo\Exception;

/**
 * 503: выдачу получить не вышло. Деньги за запрос не списываются, повтор
 * обычно проходит — SDK повторяет такие запросы сам.
 */
class ServiceUnavailableException extends ApiException {}
