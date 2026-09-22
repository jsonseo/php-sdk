<?php

/**
 * HTTP-сервер для тестов транспортов: печатает в stdout занятый порт и
 * отвечает по заданному сценарию.
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

if ($mode === 'reuse-then-silent') {
    // Первый запрос обслуживаем, на втором по тому же сокету молчим:
    // так выглядит таймаут ответа на переиспользованном соединении.
    $connection = @stream_socket_accept($server, 10);

    if ($connection === false) {
        exit(1);
    }

    read_request($connection);
    $body = '{"balance":1,"currency":"RUB"}';
    fwrite($connection, "HTTP/1.1 200 OK\r\n"
        ."Content-Type: application/json\r\n"
        .'Content-Length: '.strlen($body)."\r\n"
        ."Connection: keep-alive\r\n\r\n".$body);
    fflush($connection);

    read_request($connection);
    sleep(10);
    exit(0);
}

if ($mode === 'reuse') {
    // Два запроса по одному соединению: так ходит curl с keep-alive.
    $connection = @stream_socket_accept($server, 10);

    if ($connection === false) {
        exit(1);
    }

    foreach ([0, 1] as $number) {
        $head = read_request($connection);
        // Отдаём обратно полученный Accept: по нему видно, не протекли ли
        // настройки прошлого запроса в следующий.
        $accept = preg_match('/^Accept:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : 'нет';
        $body = '{"accept":"'.$accept.'"}';
        fwrite($connection, "HTTP/1.1 200 OK\r\n"
            ."Content-Type: application/json\r\n"
            .'Content-Length: '.strlen($body)."\r\n"
            ."Connection: keep-alive\r\n\r\n".$body);
        fflush($connection);
    }

    sleep(10);
    exit(0);
}

for ($i = 0; $i < $connections; $i++) {
    $connection = @stream_socket_accept($server, 10);

    if ($connection === false) {
        exit(1);
    }

    read_request($connection);
    respond($connection, $mode);

    // Часть сценариев держит сокет открытым намеренно.
    if (! in_array($mode, ['truncated', 'silent', 'keepalive', 'reuse', 'reuse-then-silent'], true)) {
        fclose($connection);
    }
}

exit(0);

/**
 * Вычитывает запрос целиком: иначе клиент получит обрыв вместо ответа.
 *
 * @param  resource  $connection
 * @return string Заголовки запроса
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

    return $head;
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
        // В смешанном регистре: клиент ищет заголовок в нижнем.
        fwrite($connection, "HTTP/1.1 429 Too Many Requests\r\n"
            ."Content-Type: application/json\r\n"
            ."Retry-After: 17\r\n"
            .'Content-Length: '.strlen($body)."\r\n"
            ."Connection: close\r\n\r\n".$body);

        return;
    }

    if ($mode === 'truncated') {
        // Обещаем тысячу байт, отдаём горсть и замолкаем.
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
        // То же, но соединение рвётся сразу — как у упавшего бэкенда.
        fwrite($connection, "HTTP/1.1 200 OK\r\n"
            ."Content-Type: application/json\r\n"
            ."Content-Length: 1000\r\n"
            ."Connection: close\r\n\r\n"
            .'{"results":[');
        fflush($connection);

        return;
    }

    if ($mode === 'chunked') {
        // Тела без Content-Length: длину проверить нечем, и чтение обязано
        // остановиться на конце потока, а не на таймауте.
        $body = '{"balance":123.45,"currency":"RUB"}';
        fwrite($connection, "HTTP/1.1 200 OK\r\n"
            ."Content-Type: application/json\r\n"
            ."Connection: close\r\n\r\n".$body);

        return;
    }

    if ($mode === 'silent') {
        // Запрос принят, заголовков нет: сервис ещё собирает выдачу.
        sleep(10);

        return;
    }

    if ($mode === 'keepalive') {
        // Соединение после ответа не закрывается, как у боевого сервера.
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
