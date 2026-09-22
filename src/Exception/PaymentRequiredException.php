<?php

namespace JsonSeo\Exception;

/**
 * 402: на счёте не хватает средств. Повторять запрос бессмысленно, пока
 * баланс не пополнен, — проверить его можно методом balance().
 */
class PaymentRequiredException extends ApiException {}
