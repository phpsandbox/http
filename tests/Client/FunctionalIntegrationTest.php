<?php

namespace React\Tests\Http\Client;

use Clue\React\Block;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Client\Client;
use React\Promise\Deferred;
use React\Promise\Stream;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use React\Stream\ReadableStreamInterface;
use React\Tests\Http\TestCase;

class FunctionalIntegrationTest extends TestCase
{
    /**
     * Test timeout to use for local tests.
     *
     * In practice this would be near 0.001s, but let's leave some time in case
     * the local system is currently busy.
     *
     * @var float
     */
    const TIMEOUT_LOCAL = 1.0;

    /**
     * Test timeout to use for remote (internet) tests.
     *
     * In pratice this should be below 1s, but this relies on infrastructure
     * outside our control, so consider this a maximum to avoid running for hours.
     *
     * @var float
     */
    const TIMEOUT_REMOTE = 10.0;

    public function testRequestToLocalhostEmitsSingleRemoteConnection()
    {
        $socket = new SocketServer('127.0.0.1:0');
        $socket->on('connection', $this->expectCallableOnce());
        $socket->on('connection', function (ConnectionInterface $conn) use ($socket) {
            $conn->end("HTTP/1.1 200 OK\r\n\r\nOk");
            $socket->close();
        });
        $port = parse_url($socket->getAddress(), PHP_URL_PORT);

        $client = new Client(Loop::get());
        $request = $client->request('GET', 'http://localhost:' . $port);

        $promise = Stream\first($request, 'close');
        $request->end();

        Block\await($promise, null, self::TIMEOUT_LOCAL);
    }

    public function testRequestLegacyHttpServerWithOnlyLineFeedReturnsSuccessfulResponse()
    {
        $socket = new SocketServer('127.0.0.1:0');
        $socket->on('connection', function (ConnectionInterface $conn) use ($socket) {
            $conn->end("HTTP/1.0 200 OK\n\nbody");
            $socket->close();
        });

        $client = new Client(Loop::get());
        $request = $client->request('GET', str_replace('tcp:', 'http:', $socket->getAddress()));

        $once = $this->expectCallableOnceWith('body');
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($once) {
            $body->on('data', $once);
        });

        $promise = Stream\first($request, 'close');
        $request->end();

        Block\await($promise, null, self::TIMEOUT_LOCAL);
    }

    /** @group internet */
    public function testSuccessfulResponseEmitsEnd()
    {
        // max_nesting_level was set to 100 for PHP Versions < 5.4 which resulted in failing test for legacy PHP
        ini_set('xdebug.max_nesting_level', 256);

        $client = new Client(Loop::get());

        $request = $client->request('GET', 'http://www.google.com/');

        $once = $this->expectCallableOnce();
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($once) {
            $body->on('end', $once);
        });

        $promise = Stream\first($request, 'close');
        $request->end();

        Block\await($promise, null, self::TIMEOUT_REMOTE);
    }

    /** @group internet */
    public function testPostDataReturnsData()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        // max_nesting_level was set to 100 for PHP Versions < 5.4 which resulted in failing test for legacy PHP
        ini_set('xdebug.max_nesting_level', 256);

        $client = new Client(Loop::get());

        $data = str_repeat('.', 33000);
        $request = $client->request('POST', 'https://' . (mt_rand(0, 1) === 0 ? 'eu.' : '') . 'httpbin.org/post', array('Content-Length' => strlen($data)));

        $deferred = new Deferred();
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($deferred) {
            $deferred->resolve(Stream\buffer($body));
        });

        $request->on('error', 'printf');
        $request->on('error', $this->expectCallableNever());

        $request->end($data);

        $buffer = Block\await($deferred->promise(), null, self::TIMEOUT_REMOTE);

        $this->assertNotEquals('', $buffer);

        $parsed = json_decode($buffer, true);
        $this->assertTrue(is_array($parsed) && isset($parsed['data']));
        $this->assertEquals(strlen($data), strlen($parsed['data']));
        $this->assertEquals($data, $parsed['data']);
    }

    /** @group internet */
    public function testPostJsonReturnsData()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        $client = new Client(Loop::get());

        $data = json_encode(array('numbers' => range(1, 50)));
        $request = $client->request('POST', 'https://httpbin.org/post', array('Content-Length' => strlen($data), 'Content-Type' => 'application/json'));

        $deferred = new Deferred();
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($deferred) {
            $deferred->resolve(Stream\buffer($body));
        });

        $request->on('error', 'printf');
        $request->on('error', $this->expectCallableNever());

        $request->end($data);

        $buffer = Block\await($deferred->promise(), null, self::TIMEOUT_REMOTE);

        $this->assertNotEquals('', $buffer);

        $parsed = json_decode($buffer, true);
        $this->assertTrue(is_array($parsed) && isset($parsed['json']));
        $this->assertEquals(json_decode($data, true), $parsed['json']);
    }

    /** @group internet */
    public function testCancelPendingConnectionEmitsClose()
    {
        // max_nesting_level was set to 100 for PHP Versions < 5.4 which resulted in failing test for legacy PHP
        ini_set('xdebug.max_nesting_level', 256);

        $client = new Client(Loop::get());

        $request = $client->request('GET', 'http://www.google.com/');
        $request->on('error', $this->expectCallableNever());
        $request->on('close', $this->expectCallableOnce());
        $request->end();
        $request->close();
    }
}
