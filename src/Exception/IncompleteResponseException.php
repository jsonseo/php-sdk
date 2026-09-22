<?php

namespace JsonSeo\Exception;

/**
 * Пришло меньше, чем обещал сервис. Автоматически не повторяется: выдача
 * уже собрана и оплачена, обрыв случился на отдаче.
 */
class IncompleteResponseException extends TransportException {}
