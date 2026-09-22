<?php

/**
 * Крошечный HTTP-сервер для тестов транспортов.
 *
 * Запускается отдельным процессом, печатает в stdout занятый порт и
 * отвечает по заданному сценарию. Нужен потому, что заглушка транспорта
 * проверяет клиент, но не сами транспорты, — а разбор ответа и обнаружение
 * обрыва живут именно в них.
 *
 * Использование: php server.php <сценарий>
 */
$mode = isset($argv[1]) ? $argv[1] : 'ok';

$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

if ($server === false) {
    fwrite(STDERR, 'не удалось занять порт: '.$errstr."\n");
    exit(1);
}

$name = stream_socket_get_name($server, false);
echo substr($name, strrpos($name, ':') + 1)."\n";
flush();

$connections = $mode === 'twice' ? 2 : 1;

for ($i = 0; $i < $connections; $i++) {
    $connection = @stream_socket_accept($server, 10);

    if ($connection === false) {
        exit(1);
    }

    read_request($connection);
    respond($connection, $mode);

    // Сценарии, которые изображают зависший или оборвавшийся ответ,
    // закрывают соединение сами — или намеренно не закрывают.
    if (! in_array($mode, ['truncated', 'silent', 'keepalive'], true)) {
        fclose($connection);
    }
}

exit(0);

/**
 * Вычитывает запрос целиком: не забрав тело, сервер закрыл бы соединение
 * раньше, чем клиент успел его дописать, и тот получил бы обрыв вместо ответа.
 *
 * @param  resource  $connection
 * @return void
 */
function read_request($connection)
{
    stream_set_timeout($connection, 5);

    $head = '';

    while (($line = fgets($connection)) !== false) {
        $head .= $line;

        if (rtrim($line, "\r\n") === '') {
            break;
        }
    }

    if (preg_match('/^Content-Length:\s*(\d+)/mi', $head, $matches) && (int) $matches[1] > 0) {
        $remaining = (int) $matches[1];

        while ($remaining > 0) {
            $chunk = fread($connection, $remaining);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $remaining -= strlen($chunk);
        }
    }
}

/**
 * @param  resource  $connection
 * @param  string  $mode
 * @return void
 */
function respond($connection, $mode)
{
    if ($mode === 'limited') {
        $body = '{"message":"Too Many Attempts."}';
        // Заголовок намеренно в смешанном регистре: клиент ищет его в нижнем.
        fwrite($connection, "HTTP/1.1 429 Too Many Requests\r\n"
            ."Content-Type: application/json\r\n"
            ."Retry-After: 17\r\n"
            .'Content-Length: '.strlen($body)."\r\n"
            ."Connection: close\r\n\r\n".$body);

        return;
    }

    if ($mode === 'truncated') {
        // Обещаем тысячу байт, отдаём горсть и замолкаем: так выглядит
        // оборвавшаяся на середине передача.
        fwrite($connection, "HTTP/1.1 200 OK\r\n"
            ."Content-Type: application/json\r\n"
            ."Content-Length: 1000\r\n"
            ."Connection: close\r\n\r\n"
            .'{"results":[');
        fflush($connection);
        sleep(10);

        return;
    }

    if ($mode === 'cut') {
        // То же, но соединение рвётся сразу: так ведёт себя упавший бэкенд
        // или сбросивший сессию прокси.
        fwrite($connection, "HTTP/1.1 200 OK\r\n"
            ."Content-Type: application/json\r\n"
            ."Content-Length: 1000\r\n"
            ."Connection: close\r\n\r\n"
            .'{"results":[');
        fflush($connection);

        return;
    }

    if ($mode === 'silent') {
        // Запрос принят, заголовков нет: так выглядит сервис, который ещё
        // собирает выдачу. Деньги за неё уже считаются.
        sleep(10);

        return;
    }

    if ($mode === 'keepalive') {
        // Соединение после ответа не закрывается — так отвечает любой
        // нормальный сервер HTTP/1.1, включая боевой.
        $body = '{"balance":123.45,"currency":"RUB"}';
        fwrite($connection, "HTTP/1.1 200 OK\r\n"
            ."Content-Type: application/json\r\n"
            ."X-Sample-Header: Значение\r\n"
            .'Content-Length: '.strlen($body)."\r\n"
            ."Connection: keep-alive\r\n"
            ."Keep-Alive: timeout=60\r\n\r\n".$body);
        fflush($connection);
        sleep(10);

        return;
    }

    $body = '{"balance":123.45,"currency":"RUB"}';
    fwrite($connection, "HTTP/1.1 200 OK\r\n"
        ."Content-Type: application/json\r\n"
        ."X-Sample-Header: Значение\r\n"
        .'Content-Length: '.strlen($body)."\r\n"
        ."Connection: close\r\n\r\n".$body);
}
