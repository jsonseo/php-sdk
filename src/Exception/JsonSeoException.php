<?php

namespace JsonSeo\Exception;

use RuntimeException;

/**
 * Общий предок всех исключений SDK: ловите его, если разбирать причину не
 * нужно, — ни одна ошибка библиотеки мимо него не пройдёт.
 */
class JsonSeoException extends RuntimeException {}
