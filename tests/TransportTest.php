<?php

namespace JsonSeo\Tests;

use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Exception\TransportException;
use JsonSeo\Transport\CurlTransport;
use JsonSeo\Transport\StreamTransport;
use JsonSeo\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

/**
 * Транспорты против настоящего сокета: заглушка в остальных тестах
 * проверяет клиент, но не их самих.
 */
class TransportTest extends TestCase
{
    /**
     * @var resource|null
     */
    private $process;

    /**
     * @var array<int, resource>
     */
    private $pipes = [];

    protected function tearDown(): void
    {
        $this->stopServer();
    }

    public function test_curl_reads_status_headers_and_body(): void
    {
        $this->assertReadsWholeResponse(new CurlTransport);
    }

    public function test_stream_reads_status_headers_and_body(): void
    {
        $this->assertReadsWholeResponse(new StreamTransport);
    }

    public function test_curl_keeps_error_status_and_retry_after(): void
    {
        $this->assertKeepsErrorStatus(new CurlTransport);
    }

    public function test_stream_keeps_error_status_and_retry_after(): void
    {
        $this->assertKeepsErrorStatus(new StreamTransport);
    }

    public function test_curl_truncated_body_raises_timeout(): void
    {
        $this->assertTruncatedBodyRaisesTimeout(new CurlTransport);
    }

    public function test_stream_truncated_body_raises_timeout(): void
    {
        $this->assertTruncatedBodyRaisesTimeout(new StreamTransport);
    }

    public function test_curl_reads_a_keep_alive_response(): void
    {
        $this->assertReadsKeepAliveResponse(new CurlTransport);
    }

    public function test_stream_reads_a_keep_alive_response(): void
    {
        $this->assertReadsKeepAliveResponse(new StreamTransport);
    }

    public function test_curl_reports_a_cut_connection(): void
    {
        $this->assertCutConnectionRaisesIncompleteResponse(new CurlTransport);
    }

    public function test_stream_reports_a_cut_connection(): void
    {
        $this->assertCutConnectionRaisesIncompleteResponse(new StreamTransport);
    }

    public function test_curl_silence_before_headers_is_a_timeout(): void
    {
        $this->assertSilenceIsATimeout(new CurlTransport);
    }

    public function test_stream_silence_before_headers_is_a_timeout(): void
    {
        $this->assertSilenceIsATimeout(new StreamTransport);
    }

    /** Сервер HTTP/1.1 соединение не закрывает — чтение до EOF висло бы. */
    private function assertReadsKeepAliveResponse(TransportInterface $transport): void
    {
        $url = $this->startServer('keepalive');

        $started = microtime(true);
        $response = $transport->send('POST', $url, [], 'a=1', $this->options());
        $elapsed = microtime(true) - $started;

        self::assertSame(200, $response->status());
        self::assertSame('{"balance":123.45,"currency":"RUB"}', $response->body());
        self::assertLessThan(3.0, $elapsed, 'ответ не должен ждать закрытия соединения');
    }

    /** Огрызок нельзя выдавать за успешный ответ: за выдачу уже заплачено. */
    private function assertCutConnectionRaisesIncompleteResponse(TransportInterface $transport): void
    {
        $url = $this->startServer('cut');

        $this->expectException(IncompleteResponseException::class);

        $transport->send('POST', $url, [], 'a=1', $this->options());
    }

    /** Сервис принял запрос и молчит: деньги считаются, повторять нельзя. */
    private function assertSilenceIsATimeout(TransportInterface $transport): void
    {
        $url = $this->startServer('silent');

        $this->expectException(TimeoutException::class);

        $transport->send('POST', $url, [], 'a=1', ['timeout' => 1.0, 'connect_timeout' => 2.0]);
    }

    private function assertReadsWholeResponse(TransportInterface $transport): void
    {
        $url = $this->startServer('ok');

        $response = $transport->send('POST', $url, ['Accept' => 'application/json'], 'a=1', $this->options());

        self::assertSame(200, $response->status());
        self::assertSame('{"balance":123.45,"currency":"RUB"}', $response->body());
        self::assertSame('application/json', $response->header('Content-Type'));
        self::assertSame('Значение', $response->header('x-sample-header'));
    }

    private function assertKeepsErrorStatus(TransportInterface $transport): void
    {
        $url = $this->startServer('limited');

        $response = $transport->send('POST', $url, [], 'a=1', $this->options());

        self::assertSame(429, $response->status());
        self::assertSame('17', $response->header('retry-after'));
        self::assertSame('{"message":"Too Many Attempts."}', $response->body());
    }

    /** Оборвавшийся на середине ответ обязан стать ошибкой. */
    private function assertTruncatedBodyRaisesTimeout(TransportInterface $transport): void
    {
        $url = $this->startServer('truncated');

        $this->expectException(TimeoutException::class);

        $transport->send('POST', $url, [], 'a=1', ['timeout' => 1.0, 'connect_timeout' => 2.0]);
    }

    /**
     * @return array<string, float>
     */
    private function options()
    {
        return ['timeout' => 5.0, 'connect_timeout' => 2.0];
    }

    /** Настройки прошлого запроса не должны протекать в следующий. */
    public function test_curl_handle_serves_two_requests(): void
    {
        $url = $this->startServer('twice');
        $transport = new CurlTransport;

        $first = $transport->send('POST', $url, ['Accept' => 'application/json'], 'a=1', $this->options());
        $second = $transport->send('POST', $url, [], 'b=2', $this->options());

        self::assertSame(200, $first->status());
        self::assertSame(200, $second->status());
        self::assertSame($first->body(), $second->body());
    }

    /**
     * Два запроса по одному сокету: сервер возвращает полученный Accept,
     * по нему видно, что curl_reset() сработал. Заодно исполняется путь с
     * переиспользованным соединением, ради которого и живёт дескриптор.
     */
    public function test_curl_reuses_the_connection_without_leaking_settings(): void
    {
        $url = $this->startServer('reuse');
        $transport = new CurlTransport;

        $first = $transport->send('POST', $url, ['Accept' => 'application/json'], 'a=1', $this->options());
        $second = $transport->send('POST', $url, ['Accept' => 'text/plain'], 'b=2', $this->options());

        self::assertSame('{"accept":"application/json"}', $first->body());
        self::assertSame('{"accept":"text/plain"}', $second->body());
    }

    public function test_curl_reads_a_body_without_content_length(): void
    {
        $this->assertReadsBodyWithoutContentLength(new CurlTransport);
    }

    public function test_stream_reads_a_body_without_content_length(): void
    {
        $this->assertReadsBodyWithoutContentLength(new StreamTransport);
    }

    public function test_curl_reports_a_refused_connection(): void
    {
        $this->assertRefusedConnectionIsRetryable(new CurlTransport);
    }

    public function test_stream_reports_a_refused_connection(): void
    {
        $this->assertRefusedConnectionIsRetryable(new StreamTransport);
    }

    /** Без Content-Length полноту проверить нечем: читаем до конца потока. */
    private function assertReadsBodyWithoutContentLength(TransportInterface $transport): void
    {
        $url = $this->startServer('chunked');

        $started = microtime(true);
        $response = $transport->send('POST', $url, [], 'a=1', $this->options());

        self::assertSame(200, $response->status());
        self::assertSame('{"balance":123.45,"currency":"RUB"}', $response->body());
        self::assertLessThan(3.0, microtime(true) - $started);
    }

    /**
     * Запрос до сервиса не дошёл и ничего не стоил — такой отказ обязан
     * быть повторяемым, то есть не таймаутом.
     */
    private function assertRefusedConnectionIsRetryable(TransportInterface $transport): void
    {
        // Порт занимаем и сразу отпускаем: на него точно никто не слушает.
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($probe, false);
        $port = substr($name, strrpos($name, ':') + 1);
        fclose($probe);

        try {
            $transport->send('POST', 'http://127.0.0.1:'.$port.'/api/balance', [], 'a=1', $this->options());
            self::fail('ожидался отказ соединения');
        } catch (TimeoutException $exception) {
            self::fail('отказ соединения не должен считаться таймаутом: '.$exception->getMessage());
        } catch (TransportException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }

    /**
     * Поднимает сервер отдельным процессом и возвращает его адрес.
     *
     * @param  string  $mode
     * @return string
     */
    private function startServer($mode)
    {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/server.php').' '.escapeshellarg($mode);

        $this->process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes);

        if (! is_resource($this->process)) {
            self::fail('не удалось запустить тестовый сервер');
        }

        $port = trim((string) fgets($this->pipes[1]));

        if ($port === '' || ! ctype_digit($port)) {
            self::fail('тестовый сервер не сообщил порт: '.stream_get_contents($this->pipes[2]));
        }

        return 'http://127.0.0.1:'.$port.'/api/balance';
    }

    /**
     * @return void
     */
    private function stopServer()
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->pipes = [];

        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }
}
