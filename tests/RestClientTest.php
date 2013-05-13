<?php
/**
 * This file is part of the syseleven/mite-eleven package
 * (c) SysEleven GmbH <info@syseleven.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author M. Seifert <m.seifert@syseleven.de
 * @version 0.9.1
 * @package SysEleven\MiteEleven\Tests
 */
namespace SysEleven\MiteEleven\Tests;

use SysEleven\MiteEleven\Exceptions\ApiNotAvailableException;
use SysEleven\MiteEleven\Exceptions\MiteRuntimeException;
use SysEleven\MiteEleven\RestClient;
use SysEleven\MiteEleven\RestClientInterface;
use Zend\Http\Client;
use Zend\Http\Headers;
use Zend\Http\Request;
use \Mockery as m;

/**
 * Tests for MiteEleven rest client library
 *
 * @package SysEleven\MiteEleven\Tests
 */
class RestClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RestClient $object
     */
    public $object;

    public function setUp()
    {
        $this->object = new RestClient(null, null, array());
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @covers \SysEleven\MiteEleven\RestClient::__construct
     * @covers \SysEleven\MiteEleven\RestClient::setOptions
     *
     * @covers \SysEleven\MiteEleven\RestClient::setAgent
     * @covers \SysEleven\MiteEleven\RestClient::getAgent
     * @covers \SysEleven\MiteEleven\RestClient::setKey
     * @covers \SysEleven\MiteEleven\RestClient::getKey
     * @covers \SysEleven\MiteEleven\RestClient::setUrl
     * @covers \SysEleven\MiteEleven\RestClient::getUrl
     * @covers \SysEleven\MiteEleven\RestClient::setClientOptions
     * @covers \SysEleven\MiteEleven\RestClient::getClientOptions
     * @covers \SysEleven\MiteEleven\RestClient::setExpectedContentType
     * @covers \SysEleven\MiteEleven\RestClient::getExpectedContentType
     * @covers \SysEleven\MiteEleven\RestClient::setUsername
     * @covers \SysEleven\MiteEleven\RestClient::setPassword
     * @covers \SysEleven\MiteEleven\RestClient::getUsername
     * @covers \SysEleven\MiteEleven\RestClient::getPassword
     * @covers \SysEleven\MiteEleven\RestClient::getAdapter
     * @covers \SysEleven\MiteEleven\RestClient::setAdapter
     */
    public function testSetterGetter()
    {
        $options = array('clientOptions' => array('option' => 'value'),
                         'notextistentproperty' => 'notthere');
        $object = new RestClient('uri', 'key', $options);
        $this->assertEquals('uri',$object->getUrl());
        $this->assertEquals('key',$object->getKey());
        $this->assertEquals($options['clientOptions'],$object->getClientOptions());

        $this->object->setAgent('dummy');
        $this->object->setKey('dummy');
        $this->object->setUrl('dummy');
        $this->object->setClientOptions(array('dummy' => 'dummy'));
        $this->object->setExpectedContentType('application/dummy');
        $this->object->setUsername('dummy');
        $this->object->setPassword('dummy');
        $this->object->setAdapter(new \Zend\Http\Client\Adapter\Socket());

        $this->assertEquals('dummy',$this->object->getAgent());
        $this->assertEquals('dummy',$this->object->getKey());
        $this->assertEquals('dummy',$this->object->getUrl());
        $this->assertEquals(array('dummy' => 'dummy'),$this->object->getClientOptions());
        $this->assertEquals('application/dummy',$this->object->getExpectedContentType());
        $this->assertEquals('dummy',$this->object->getUsername());
        $this->assertEquals('dummy',$this->object->getPassword());
        $this->assertInstanceOf('\Zend\Http\Client\Adapter\Socket', $this->object->getAdapter());

        $this->object->setOptions(array('url' => 'test','client_options' => 'test','bogus'), array('seturl','setclientoptions'));
        $this->assertEquals('test',$this->object->getUrl());
        $this->assertEquals('test',$this->object->getClientOptions());
    }


    /**
     * @covers \SysEleven\MiteEleven\RestClient::getClient
     */
    public function testGetClient()
    {
        $this->object->setUsername('dummy');
        $this->object->setPassword('dummy');
        $this->object->setKey('myKey');
        $this->object->setClientOptions(null);
        $this->object->setAdapter(new \Zend\Http\Client\Adapter\Socket());

        $client = $this->object->getClient();
        $obj = new \ReflectionClass($client);
        $property = $obj->getProperty('auth');
        $property->setAccessible(true);;
        $auth = $property->getValue($client);

        $this->assertInstanceOf('\Zend\Http\Client',$client);
        $this->assertEquals(array(),$auth);

        $this->object->setKey(null);
        $client = $this->object->getClient();
        $obj = new \ReflectionClass($client);
        $property = $obj->getProperty('auth');
        $property->setAccessible(true);;
        $auth = $property->getValue($client);

        $this->assertInstanceOf('\Zend\Http\Client',$client);
        $this->assertNotEquals(array(),$auth);

        $this->assertInstanceOf('\Zend\Http\Client\Adapter\Socket', $client->getAdapter());

    }

    /**
     * @covers \SysEleven\MiteEleven\RestClient::createRequest
     * @expectedException \RuntimeException
     */
    public function testCreateRequest()
    {
        $this->object->setKey('123456');

        $request = $this->object->createRequest('GET', array('Test' => 'MyTest'));
        $this->assertInstanceOf('\Zend\Http\Request',$request);
        $this->assertEquals('MyTest',$request->getHeader('Test')->getFieldValue());
        $this->assertEquals('123456',$request->getHeader('X-MiteApiKey')->getFieldValue());
        $this->assertFalse($request->getHeader('Content-Type',false));
        $request = $this->object->createRequest('POST', array());
        $this->assertInstanceOf('\Zend\Http\Request',$request);
        $this->assertEquals('application/json',$request->getHeader('Content-Type')->getFieldValue());
        $request = $this->object->createRequest('BOGUS', array('Test' => 'MyTest'));

    }

    /**
     * @covers \SysEleven\MiteEleven\RestClient::call
     */
    public function testCall()
    {
        try {
            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andThrow('\Zend\Http\Client\Adapter\Exception\TimeoutException','Timeout',123);

            $class = m::mock('\SysEleven\MiteEleven\RestClient[getClient]',array('test', 'test'));
            $class->shouldReceive('getClient')->andReturn($client);

            $request = $this->object->createRequest('GET');
            $class->call($request, null, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (ApiNotAvailableException $na) {
            $this->assertEquals('Timeout', $na->getMessage());
            $this->assertEquals(123, $na->getCode());
        }

        try {
            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andThrow('\Zend\Http\Client\Adapter\Exception\TimeoutException','Timeout',123);

            $request = $this->object->createRequest('GET');
            $this->object->call($request, $client, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (ApiNotAvailableException $na) {
            $this->assertEquals('Timeout', $na->getMessage());
            $this->assertEquals(123, $na->getCode());
        }

        try {
            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andThrow('\Exception','SomeMessage',123);

            $request = $this->object->createRequest('GET');
            $this->object->call($request, $client, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (MiteRuntimeException $re) {
            $this->assertEquals('SomeMessage', $re->getMessage());
            $this->assertEquals(123, $re->getCode());
        }

        try {
            $response = m::mock('\Zend\Http\Response');

            $headers = new Headers();
            $headers->addHeaders(array('Content-Type' => 'application/bogus'));

            $response->shouldReceive('getHeaders')->andReturn($headers);
            $response->shouldReceive('isSuccess')->andReturn(true);
            $response->shouldReceive('getStatusCode')->andReturn(200);
            $response->shouldReceive('getBody')->andReturn(json_encode(array('error' => 1234)));

            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andReturn($response);

            $request = $this->object->createRequest('GET');
            $this->object->call($request, $client, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (MiteRuntimeException $re) {
            $this->assertEquals(2001, $re->getCode());
            $this->assertEquals('Wrong type of response, expected: application/json got: application/bogus', $re->getMessage());
        }

        try {
            $response = m::mock('\Zend\Http\Response');

            $headers = new Headers();
            $headers->addHeaders(array('Content-Type' => 'application/json'));

            $response->shouldReceive('getHeaders')->andReturn($headers);
            $response->shouldReceive('isSuccess')->andReturn(true);
            $response->shouldReceive('getStatusCode')->andReturn(200);
            $response->shouldReceive('getBody')->andReturn("no valid json data");

            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andReturn($response);

            $request = $this->object->createRequest('GET');
            $this->object->call($request, $client, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (MiteRuntimeException $re) {
            $this->assertEquals(2001, $re->getCode());
            $this->assertEquals('Cannot decode data', $re->getMessage());
        }

        // Testing empty json response
        $response = m::mock('\Zend\Http\Response');

        $headers = new Headers();
        $headers->addHeaders(array('Content-Type' => 'application/json'));

        $response->shouldReceive('getHeaders')->andReturn($headers);
        $response->shouldReceive('isSuccess')->andReturn(true);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn("");

        $client = m::mock('\Zend\Http\Client');
        $client->shouldReceive('dispatch')->andReturn($response);

        $request = $this->object->createRequest('GET');
        $res = $this->object->call($request, $client);

        $this->assertTrue($res);


        $response = m::mock('\Zend\Http\Response');

        $headers = new Headers();
        $headers->addHeaders(array('Content-Type' => 'application/json'));

        $response->shouldReceive('getHeaders')->andReturn($headers);
        $response->shouldReceive('isSuccess')->andReturn(true);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn(json_encode(array()));

        $client = m::mock('\Zend\Http\Client');
        $client->shouldReceive('dispatch')->andReturn($response);

        $request = $this->object->createRequest('GET');
        $res = $this->object->call($request, $client, array());

        $this->assertEquals(array(), $res);

        $response = m::mock('\Zend\Http\Response');

        $headers = new Headers();
        $headers->addHeaders(array('Content-Type' => 'text/html'));

        $response->shouldReceive('getHeaders')->andReturn($headers);
        $response->shouldReceive('isSuccess')->andReturn(true);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn('Simple Html');

        $client = m::mock('\Zend\Http\Client');
        $client->shouldReceive('dispatch')->andReturn($response);

        $request = $this->object->createRequest('GET');
        $res = $this->object->call($request, $client, array('expected' => 'text/html'));

        $this->assertEquals('Simple Html', $res);


        try {
            $response = m::mock('\Zend\Http\Response');
            $response->shouldReceive('isSuccess')->andReturn(false);
            $response->shouldReceive('getStatusCode')->andReturn(403);
            $response->shouldReceive('getBody')->andReturn('AuthFailed');

            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andReturn($response);

            $request = $this->object->createRequest('GET');
            $this->object->call($request, $client, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (MiteRuntimeException $re) {
            $this->assertEquals('AuthFailed', $re->getMessage());
            $this->assertEquals(403, $re->getCode());
        }

        try {
            $response = m::mock('\Zend\Http\Response');

            $headers = new Headers();
            $headers->addHeaders(array('Content-Type' => 'application/json'));

            $response->shouldReceive('getHeaders')->andReturn($headers);
            $response->shouldReceive('isSuccess')->andReturn(false);
            $response->shouldReceive('getStatusCode')->andReturn(401);
            $response->shouldReceive('getBody')->andReturn(json_encode(array('error' => 1234)));

            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andReturn($response);

            $request = $this->object->createRequest('GET');
            $this->object->call($request, $client, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (MiteRuntimeException $re) {
            $this->assertEquals(array('error' => 1234), $re->getErrorData());
            $this->assertEquals(401, $re->getCode());
            $this->assertEquals('Mite Error', $re->getMessage());
        }

        try {
            $response = m::mock('\Zend\Http\Response');

            $headers = new Headers();
            $headers->addHeaders(array('Content-Type' => 'application/bogus'));

            $response->shouldReceive('getHeaders')->andReturn($headers);
            $response->shouldReceive('isSuccess')->andReturn(false);
            $response->shouldReceive('getStatusCode')->andReturn(401);
            $response->shouldReceive('getBody')->andReturn('errormessage');

            $client = m::mock('\Zend\Http\Client');
            $client->shouldReceive('dispatch')->andReturn($response);

            $request = $this->object->createRequest('GET');
            $this->object->call($request, $client, array());
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (MiteRuntimeException $re) {
            $this->assertEquals(401, $re->getCode());
            $this->assertEquals('errormessage', $re->getMessage());
        }
    }
}
