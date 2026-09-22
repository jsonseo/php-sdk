<?php

namespace JsonSeo\Exception;

/**
 * До сервиса не достучались: сеть, DNS, TLS, таймаут. Ответа нет, поэтому
 * и статуса нет — отличается этим от ApiException.
 */
class TransportException extends JsonSeoException {}
