<?php

namespace JsonSeo\Tests;

use JsonSeo\Exception\IncompleteResponseException;
use JsonSeo\Exception\TimeoutException;
use JsonSeo\Transport\CurlTransport;
use JsonSeo\Transport\StreamTransport;
use JsonSeo\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

/**
 * Транспорты против настоящего сокета.
 *
 * Заглушка транспорта в остальных тестах проверяет клиент, но не их самих,
 * а обнаружение обрыва и разбор ответа живут именно здесь — и молча
 * обрезанное тело дороже любой другой ошибки в библиотеке: клиент за эту
 * выдачу уже заплатил.
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

    /**
     * Сервер HTTP/1.1 соединение после ответа не закрывает — так отвечает и
     * боевой. Чтение «до конца потока» на таком ответе упирается в таймаут,
     * хотя тело пришло целиком, и каждый успешный запрос превращался бы в
     * пятиминутное ожидание с исключением в конце.
     */
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

    /**
     * Оборвавшееся на середине тело нельзя выдавать за успешный ответ: за эту
     * выдачу уже заплачено, а вернулся бы огрызок.
     */
    private function assertCutConnectionRaisesIncompleteResponse(TransportInterface $transport): void
    {
        $url = $this->startServer('cut');

        $this->expectException(IncompleteResponseException::class);

        $transport->send('POST', $url, [], 'a=1', $this->options());
    }

    /**
     * Сервис принял запрос и молчит, пока собирает выдачу, — деньги за неё
     * уже считаются. Повторять такое нельзя, поэтому это именно таймаут,
     * а не обрыв связи.
     */
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

    /**
     * Ответ, оборвавшийся на середине тела, обязан стать ошибкой.
     *
     * Потоковый транспорт раньше отдавал такой огрызок как успешный ответ:
     * file_get_contents на исчерпании таймаута возвращает прочитанное, не
     * поднимая ошибки, — и обрезанная выдача уходила наверх как настоящая.
     */
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

    /**
     * Дескриптор curl переиспользуется между запросами — настройки прошлого
     * запроса не должны протекать в следующий.
     */
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
     * Поднимает сервер отдельным процессом и возвращает адрес, по которому
     * он отвечает.
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
