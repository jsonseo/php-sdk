<?php

namespace JsonSeo\Exception;

/** 503: выдачу получить не вышло. Деньги не списаны, SDK повторит сам. */
class ServiceUnavailableException extends ApiException {}
