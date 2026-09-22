<?php

namespace JsonSeo\Exception;

/**
 * Ответа не дождались. Автоматически не повторяется: выдача всё равно
 * будет собрана и оплачена. Нужен ответ — поднимайте timeout, не retries.
 */
class TimeoutException extends TransportException {}
