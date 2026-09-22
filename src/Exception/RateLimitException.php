<?php

namespace JsonSeo\Exception;

/** 429: превышен лимит частоты. Срок повтора — в retryAfter(). */
class RateLimitException extends ApiException {}
