<?php

namespace JsonSeo\Tests;

use JsonSeo\Client;
use JsonSeo\Exception\InvalidArgumentException;
use JsonSeo\Exception\JsonSeoException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /**
     * @var FakeTransport
     */
    private $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Client
     */
    private function client(array $options = [])
    {
        return new Client('KEY', array_merge(['transport' => $this->transport], $options));
    }

    public function test_requires_api_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Client('   ');
    }

    /**
     * Заголовок не переносит не-ASCII: без проверки сервис отвечал бы
     * «токен не предоставлен», и причина была бы неочевидна.
     */
    public function test_rejects_key_with_non_ascii(): void
    {
        // Второй набор — края строки: там родной trim каждого языка свой,
        // и без общего набора обрезки эти ключи расходились бы по SDK.
        $edges = ["\xC2\xA0KEY", "\xE2\x80\x80KEY", "\xC2\x85KEY", "KEY\x00", "\x0cKEY", "\x1cKEY", "KEY\x0b"];

        foreach (array_merge(['КЛЮЧ', "dead\tbeef", "dead\x01beef", 'ключdeadbeef'], $edges) as $key) {
            try {
                new Client($key);
                self::fail('Ожидался отказ на ключе '.json_encode($key));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('ASCII', $exception->getMessage());
            }
        }
    }

    /** Пробелы по краям обрезаются, а не считаются браком ключа. */
    public function test_accepts_a_normal_key(): void
    {
        $this->transport->queueJson([]);

        $client = new Client("  Ab3-_.~xYz09 \n", ['transport' => $this->transport]);
        $client->balance();

        self::assertSame('Bearer Ab3-_.~xYz09', $this->transport->requests[0]['headers']['Authorization']);
    }

    public function test_rejects_unknown_option(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Client('KEY', ['timeuot' => 5]);
    }

    public function test_rejects_unknown_auth_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Client('KEY', ['auth' => 'cookie']);
    }

    public function test_rejects_transport_without_the_interface(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Client('KEY', ['transport' => new \stdClass]);
    }

    public function test_sends_key_in_authorization_header_by_default(): void
    {
        $this->transport->queueJson(['balance' => 1.0, 'currency' => 'RUB']);

        $this->client()->balance();

        $headers = $this->transport->requests[0]['headers'];

        self::assertSame('Bearer KEY', $headers['Authorization']);
        self::assertArrayNotHasKey('key', $this->transport->sentParams());
    }

    public function test_sends_key_in_parameter_when_asked(): void
    {
        $this->transport->queueJson(['balance' => 1.0, 'currency' => 'RUB']);

        $this->client(['auth' => Client::AUTH_QUERY])->balance();

        self::assertArrayNotHasKey('Authorization', $this->transport->requests[0]['headers']);
        self::assertSame('KEY', $this->transport->sentParams()['key']);
    }

    public function test_posts_to_the_method_path(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->yandex('купить ноутбук');

        self::assertSame('POST', $this->transport->requests[0]['method']);
        self::assertSame('https://jsonseo.ru/api/yandex', $this->transport->requests[0]['url']);
    }

    public function test_base_url_option_is_used_without_trailing_slash(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client(['base_url' => 'http://localhost:8080/api/'])->yandex('тест');

        self::assertSame('http://localhost:8080/api/yandex', $this->transport->requests[0]['url']);
    }

    public function test_string_argument_becomes_the_primary_parameter(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->yandex('купить ноутбук');

        self::assertSame('купить ноутбук', $this->transport->sentParams()['text']);
    }

    public function test_google_takes_its_query_in_q(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->google('купить ноутбук');

        self::assertSame('купить ноутбук', $this->transport->sentParams()['q']);
    }

    public function test_phrase_list_is_joined_with_newlines(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->direct(['ремонт айфона', 'ремонт телефона']);

        self::assertSame("ремонт айфона\nремонт телефона", $this->transport->sentParams()['phrases']);
    }

    public function test_other_lists_are_joined_with_commas(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->wordstat(['text' => 'ремонт', 'region' => [213, 2], 'device' => ['desktop', 'phone']]);

        $params = $this->transport->sentParams();

        self::assertSame('213,2', $params['region']);
        self::assertSame('desktop,phone', $params['device']);
    }

    public function test_booleans_become_ones_and_zeros(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->yandex(['text' => 'тест', 'ai' => true, 'noreask' => false]);

        $params = $this->transport->sentParams();

        self::assertSame('1', $params['ai']);
        self::assertSame('0', $params['noreask']);
    }

    public function test_null_parameters_are_dropped(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->yandex(['text' => 'тест', 'break_domain' => null]);

        self::assertArrayNotHasKey('break_domain', $this->transport->sentParams());
    }

    public function test_floats_keep_a_decimal_point(): void
    {
        $this->transport->queueJson([]);

        $this->client()->call('geoip', ['threshold' => 0.5]);

        self::assertSame('0.5', $this->transport->sentParams()['threshold']);
    }

    /** В ru_RU приведение к строке дало бы запятую. */
    public function test_floats_ignore_the_numeric_locale(): void
    {
        $previous = setlocale(LC_NUMERIC, '0');
        $applied = setlocale(LC_NUMERIC, 'ru_RU.UTF-8', 'ru_RU', 'de_DE.UTF-8', 'de_DE');

        try {
            $this->transport->queueJson([]);
            $this->client()->call('geoip', ['threshold' => 0.5]);

            self::assertSame('0.5', $this->transport->sentParams()['threshold']);
        } finally {
            if ($applied !== false) {
                setlocale(LC_NUMERIC, $previous);
            }
        }
    }

    public function test_empty_list_is_not_sent(): void
    {
        $this->transport->queueJson(['results' => []]);

        $this->client()->wordstat(['text' => 'ремонт', 'region' => []]);

        self::assertArrayNotHasKey('region', $this->transport->sentParams());
    }

    public function test_bad_value_inside_a_list_names_its_position(): void
    {
        $this->transport->queueJson([]);

        try {
            $this->client()->wordstat(['text' => 'ремонт', 'region' => [213, new \stdClass]]);
            self::fail('Ожидался отказ на значении внутри списка');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('region[1]', $exception->getMessage());
        }
    }

    /** Разделитель явно, а не из php.ini, где бывает &amp;. */
    public function test_parameters_are_joined_with_an_ampersand(): void
    {
        $previous = ini_get('arg_separator.output');
        ini_set('arg_separator.output', '&amp;');

        try {
            $this->transport->queueJson(['results' => []]);
            $this->client()->yandex(['text' => 'тест', 'pages' => 2]);

            self::assertSame('text=%D1%82%D0%B5%D1%81%D1%82&pages=2', $this->transport->requests[0]['body']);
        } finally {
            ini_set('arg_separator.output', $previous);
        }
    }

    public function test_user_agent_reaches_the_request(): void
    {
        $this->transport->queueJson([])->queueJson([]);

        $this->client()->balance();
        self::assertStringContainsString('jsonseo-php/', $this->transport->requests[0]['headers']['User-Agent']);

        $this->client(['user_agent' => 'мой-проект/1.0'])->balance();
        self::assertSame('мой-проект/1.0', $this->transport->requests[1]['headers']['User-Agent']);
    }

    public function test_call_raw_asks_for_an_unparsed_body(): void
    {
        $this->transport->queueRaw('что угодно');

        $this->client()->callRaw('yandex/xml', ['query' => 'тест']);

        self::assertSame('application/xml, text/xml', $this->transport->requests[0]['headers']['Accept']);
    }

    public function test_rejects_parameters_that_are_neither_string_nor_array(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client()->yandex(42);
    }

    public function test_rejects_unsupported_parameter_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client()->call('geoip', ['ip' => new \stdClass]);
    }

    public function test_decodes_json_responses(): void
    {
        $this->transport->queueJson(['balance' => 123.45, 'currency' => 'RUB']);

        $balance = $this->client()->balance();

        self::assertSame(123.45, $balance['balance']);
        self::assertSame('RUB', $balance['currency']);
    }

    public function test_call_raw_returns_the_body_as_is(): void
    {
        $body = '<?xml version="1.0"?><yandexsearch></yandexsearch>';
        $this->transport->queueRaw($body);

        self::assertSame($body, $this->client()->callRaw('yandex/xml', ['query' => 'купить ноутбук']));
        self::assertSame('купить ноутбук', $this->transport->sentParams()['query']);
    }

    public function test_balance_sends_no_parameters_of_its_own(): void
    {
        $this->transport->queueJson(['balance' => 0.0, 'currency' => 'RUB']);

        $this->client()->balance();

        self::assertSame('', $this->transport->requests[0]['body']);
    }

    public function test_call_reaches_an_arbitrary_path(): void
    {
        $this->transport->queueJson(['ok' => true]);

        $this->client()->call('some/new/method', ['a' => 1]);

        self::assertSame('https://jsonseo.ru/api/some/new/method', $this->transport->requests[0]['url']);
    }

    public function test_unparsable_body_raises_an_exception(): void
    {
        $this->transport->queueRaw('<html>прокси съел ответ</html>');

        $this->expectException(JsonSeoException::class);

        $this->client()->balance();
    }

    public function test_timeouts_reach_the_transport(): void
    {
        $this->transport->queueJson([]);

        $this->client(['timeout' => 12.5, 'connect_timeout' => 3.0])->balance();

        $options = $this->transport->requests[0]['options'];

        self::assertSame(12.5, $options['timeout']);
        self::assertSame(3.0, $options['connect_timeout']);
    }

    /** Опечатка в пути иначе всплыла бы только на боевом ключе. */
    public function test_every_method_calls_its_own_path(): void
    {
        $methods = [
            'yandex' => 'yandex',
            'yandexSuggest' => 'yandex/suggest',
            'yandexRegions' => 'yandex/regions',
            'yandexImages' => 'yandex/images',
            'yandexVideo' => 'yandex/video',
            'google' => 'google',
            'googleSuggest' => 'google/suggest',
            'googleRegions' => 'google/regions',
            'googleImages' => 'google/images',
            'googleVideo' => 'google/video',
            'bing' => 'bing',
            'bingSuggest' => 'bing/suggest',
            'bingImages' => 'bing/images',
            'bingVideo' => 'bing/video',
            'wordstat' => 'wordstat',
            'wordstatFrequency' => 'wordstat/frequency',
            'wordstatGraph' => 'wordstat/graph',
            'wordstatMap' => 'wordstat/map',
            'direct' => 'direct',
            'geoip' => 'geoip',
        ];

        $client = $this->client();
        $index = 0;

        foreach ($methods as $method => $path) {
            $this->transport->queueRaw('{}');
            $client->{$method}('тест');

            self::assertSame(
                'https://jsonseo.ru/api/'.$path,
                $this->transport->requests[$index]['url'],
                'Метод '.$method.' ушёл не по своему адресу'
            );

            $index++;
        }

        $this->transport->queueJson([]);
        $client->balance();

        self::assertSame('https://jsonseo.ru/api/balance', $this->transport->requests[$index]['url']);
        self::assertSame(count($methods) + 1, $this->transport->count());
    }
}
