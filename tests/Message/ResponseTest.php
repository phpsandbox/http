<?php

namespace React\Tests\Http\Message;

use React\Http\Io\HttpBodyStream;
use React\Http\Message\Response;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class ResponseTest extends TestCase
{
    public function testConstructWithStringBodyWillReturnStreamInstance()
    {
        $response = new Response(200, array(), 'hello');
        $body = $response->getBody();

        /** @var \Psr\Http\Message\StreamInterface $body */
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertEquals('hello', (string) $body);
    }

    public function testConstructWithStreamingBodyWillReturnReadableBodyStream()
    {
        $response = new Response(200, array(), new ThroughStream());
        $body = $response->getBody();

        /** @var \Psr\Http\Message\StreamInterface $body */
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceof('React\Stream\ReadableStreamInterface', $body);
        $this->assertInstanceOf('React\Http\Io\HttpBodyStream', $body);
        $this->assertNull($body->getSize());
    }

    public function testConstructWithHttpBodyStreamReturnsBodyAsIs()
    {
        $response = new Response(
            200,
            array(),
            $body = new HttpBodyStream(new ThroughStream(), 100)
        );

        $this->assertSame($body, $response->getBody());
    }

    public function testFloatBodyWillThrow()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Response(200, array(), 1.0);
    }

    public function testResourceBodyWillThrow()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Response(200, array(), tmpfile());
    }


    public function testHtmlMethodReturnsHtmlResponse()
    {
        $response = Response::html('<!doctype html><body>Hello wörld!</body>');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('<!doctype html><body>Hello wörld!</body>', (string) $response->getBody());
    }

    /**
     * @requires PHP 5.4
     */
    public function testJsonMethodReturnsPrettyPrintedJsonResponse()
    {
        $response = Response::json(array('text' => 'Hello wörld!'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("{\n    \"text\": \"Hello wörld!\"\n}\n", (string) $response->getBody());
    }

    /**
     * @requires PHP 5.6.6
     */
    public function testJsonMethodReturnsZeroFractionsInJsonResponse()
    {
        $response = Response::json(1.0);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("1.0\n", (string) $response->getBody());
    }

    public function testJsonMethodReturnsJsonTextForSimpleString()
    {
        $response = Response::json('Hello world!');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("\"Hello world!\"\n", (string) $response->getBody());
    }

    public function testJsonMethodThrowsForInvalidString()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->setExpectedException('InvalidArgumentException', 'Unable to encode given data as JSON');
        } else {
            $this->setExpectedException('InvalidArgumentException', 'Unable to encode given data as JSON: Malformed UTF-8 characters, possibly incorrectly encoded');
        }
        Response::json("Hello w\xF6rld!");
    }

    public function testPlaintextMethodReturnsPlaintextResponse()
    {
        $response = Response::plaintext("Hello wörld!\n");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("Hello wörld!\n", (string) $response->getBody());
    }

    public function testXmlMethodReturnsXmlResponse()
    {
        $response = Response::xml('<?xml version="1.0" encoding="utf-8"?><body>Hello wörld!</body>');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/xml', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('<?xml version="1.0" encoding="utf-8"?><body>Hello wörld!</body>', (string) $response->getBody());
    }
}
