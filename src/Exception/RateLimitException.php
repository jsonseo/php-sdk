<?php

namespace JsonSeo\Exception;

/**
 * 429: превышен один из лимитов частоты. Деньги не списываются, запрос
 * можно повторить — через сколько, сказано в заголовке Retry-After,
 * его отдаёт retryAfter().
 */
class RateLimitException extends ApiException {}
