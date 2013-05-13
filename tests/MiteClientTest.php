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

use \PHPUnit_Framework_TestCase;
use SysEleven\MiteEleven\MiteClient;
use SysEleven\MiteEleven\MiteInterface;
use \Mockery as m;
use SysEleven\MiteEleven\RestClient;

/**
 * Test for MiteEleven client library
 *
 * @package SysEleven\MiteEleven\Tests
 */
class MiteClientTest extends \PHPUnit_Framework_TestCase {

    /**
     * Initializes the object
     */
    public function setUp()
    {

    }

    public function tearDown()
    {
        m::close();
    }

    public function testFilterBoolean()
    {
        $var = filter_var(false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->assertFalse($var);
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::__construct
     * @covers \SysEleven\MiteEleven\MiteClient::listEntries()
     */
    public function testListEntries()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $filter = array(
                      'customer_id' => 1,
                      'project_id' => 2,
	                  'service_id' => array(1,2,3),
                      'user_id' => array(1,0),
                      'billable' => true,
                      'note' => 'test',
                      'at' => 'today',
                      'from' => '2012-12-01',
                      'to' => '2013-01-01');

        $group = array('customer', 'project', 'service', 'user', 'day',
	                           'week', 'month', 'year');

        $expected = $filter;
        $expected['service_id'] = '1,2,3';
        $expected['user_id'] = '1';
        $expected['billable'] = 'true';
        $expected['limit'] = 10;
        $expected['page'] = 1;
        $expected['group_by'] = implode(',',$group);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listEntries($filter, $group, 10, 1);

        $this->assertTrue($request->isGet());

        foreach ($expected AS $k => $v) {
            $this->assertEquals($v, $request->getQuery($k));
        }

        $this->assertEquals('https://www.example.com/time_entries.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getEntry
     */
    public function testGetEntry()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return array('time_entry' => $request); } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->getEntry(123);

        $this->assertTrue($request->isGet());

        $this->assertEquals('https://www.example.com/time_entries/123.json', $request->getUri());

        try {
            $mite->getEntry(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->getEntry('abc');
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andReturn(array());

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getEntry(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\EntryNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getEntry(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\EntryNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getEntry(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::createEntry
     * @covers \SysEleven\MiteEleven\MiteClient::prepareEntryData
     */
    public function testCreateEntry()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $data = array('date_at' => new \DateTime(),
                      'minutes' => 10,
                      'note' => '',
                      'user_id' => 1,
                      'service_id' => 1,
                      'project_id' => 1,
                      'locked' => true);

        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->createEntry($data);
        $this->assertTrue($res->isPost());
        $this->assertEquals('application/json', $res->getHeader('Content-Type')->getFieldValue());

        $cnt = $res->getContent();
        $cnt = json_decode($cnt, true);

        $this->assertArrayHasKey('time_entry', $cnt);

        $expected = $data;
        $expected['date_at'] = $data['date_at']->format('Y-m-d');
        $expected['locked']  = 'true';

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $cnt['time_entry']);
            $this->assertEquals($v, $cnt['time_entry'][$k]);
        }

        $mite = new MiteClient($rest);

        $data = array('bogus' => 1,'note' => null, 'locked' => 117);

        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->createEntry($data);
        $this->assertTrue($res->isPost());
        $this->assertEquals('application/json', $res->getHeader('Content-Type')->getFieldValue());

        $cnt = $res->getContent();
        $cnt = json_decode($cnt, true);

        $this->assertArrayHasKey('time_entry', $cnt);
        $this->assertArrayNotHasKey('bogus', $cnt['time_entry']);
        $this->assertCount(0, $cnt['time_entry']);
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::updateEntry
     * @covers \SysEleven\MiteEleven\MiteClient::prepareEntryData
     */
    public function testUpdateEntry()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $data = array('date_at' => new \DateTime(),
                      'minutes' => 10,
                      'note' => '',
                      'user_id' => 1,
                      'service_id' => 1,
                      'project_id' => 1,
                      'locked' => true);

        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->updateEntry(123, $data, true);
        $this->assertTrue($res->isPut());
        $this->assertEquals('https://www.example.com/time_entries/123.json', $res->getUriString());
        $this->assertEquals('application/json', $res->getHeader('Content-Type')->getFieldValue());

        $cnt = $res->getContent();
        $cnt = json_decode($cnt, true);

        $this->assertArrayHasKey('time_entry', $cnt);
        $this->assertArrayHasKey('force',$cnt['time_entry']);

        $expected = $data;
        $expected['date_at'] = $data['date_at']->format('Y-m-d');
        $expected['locked']  = 'true';

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $cnt['time_entry']);
            $this->assertEquals($v, $cnt['time_entry'][$k]);
        }

        $mite = new MiteClient($rest);

        $data = array('bogus' => 1,'note' => null, 'locked' => 117);

        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->updateEntry(123, $data, false);
        $this->assertTrue($res->isPut());
        $this->assertEquals('https://www.example.com/time_entries/123.json', $res->getUriString());
        $this->assertEquals('application/json', $res->getHeader('Content-Type')->getFieldValue());


        $cnt = $res->getContent();
        $cnt = json_decode($cnt, true);

        $this->assertArrayHasKey('time_entry', $cnt);
        $this->assertArrayNotHasKey('force', $cnt);
        $this->assertArrayNotHasKey('bogus', $cnt['time_entry']);
        $this->assertCount(0, $cnt['time_entry']);

        try {
            $mite->updateEntry(null);

        } catch(\BadMethodCallException $bme) {
            $this->assertEquals('ID must be a positive integer got: ', $bme->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);
            $mite->updateEntry(1);

            $this->assertTrue(false, 'Expected Exception');

        } catch (\Exception $e) {

            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\EntryNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 403);

            $mite = new MiteClient($rest);
            $mite->updateEntry(1);

            $this->assertTrue(false, 'Expected Exception');

        } catch (\Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }


    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::deleteEntry
     */
    public function testDeleteEntry()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->deleteEntry(123);

        $this->assertTrue($request->isDelete());

        $this->assertEquals('https://www.example.com/time_entries/123.json', $request->getUri());

        try {
            $mite->deleteEntry(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->deleteEntry('abc');
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteEntry(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\EntryNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteEntry(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::listCustomers
     * @covers \SysEleven\MiteEleven\MiteClient::prepareLimit
     */
    public function testListCustomers()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listCustomers('test', 10, 1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/customers.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::listArchivedCustomers
     */
    public function testListArchivedCustomers()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request;} );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listArchivedCustomers('test', 10, 1);


        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/customers/archived.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::searchCustomers
     * @expectedException \BadMethodCallException
     */
    public function testSearchCustomers()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturn(array(1));

        $mite = new MiteClient($rest);

        $res = $mite->searchCustomers('test');

        $this->assertEquals(array(1,1),$res);

        $mite->searchCustomers('t');
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getCustomer
     * @expectedException \BadMethodCallException
     */
    public function testGetCustomer()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return array('customer' => $request); } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->getCustomer(1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('https://www.example.com/customers/1.json', $request->getUri());

        $mite->getCustomer(null);
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getCustomer
     * @expectedException \SysEleven\MiteEleven\Exceptions\CustomerNotFoundException
     */
    public function testGetCustomerNotFound()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturn(array());

        $mite = new MiteClient($rest);

        $mite->getCustomer(1);
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getCustomer
     * @expectedException \SysEleven\MiteEleven\Exceptions\CustomerNotFoundException
     */
    public function testGetCustomerNotFoundTheSecond()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andThrow('\Exception','Not Found', 404);

        $mite = new MiteClient($rest);

        $mite->getCustomer(1);
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getCustomer
     * @expectedException \Exception
     */
    public function testGetCustomerOtherException()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andThrow('\Exception','Not Found', 500);

        $mite = new MiteClient($rest);

        $mite->getCustomer(1);
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::createCustomer
     * @covers \SysEleven\MiteEleven\MiteClient::prepareCustomerData
     */
    public function testCreateCustomer()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $name = 'somename';
        $options = array('note' => '',
                         'archived' => false,
                         'hourly_rate' => 1,
                         'hourly_rates_per_service' => array(
                             array('id' => 2, 'hourly_rate' => 2)),
                         'active_hourly_rate' => 'hourly_rate');
        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->createCustomer($name, $options);

        $expected = $options;
        $expected['name'] = $name;
        $expected['archived'] = 'false';

        $this->assertTrue($res->isPost());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());
        $this->assertEquals('https://www.example.com/customers.json',$res->getUriString());

        $ret = json_decode($res->getContent(), true);

        $this->assertArrayHasKey('customer', $ret);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $ret['customer']);
            $this->assertEquals($v, $ret['customer'][$k]);
        }

        // checking for empty values
        $res = $mite->createCustomer($name, array('note' => null));
        $this->assertTrue($res->isPost());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());

        $ret = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('customer', $ret);
        $this->assertArrayNotHasKey('note', $ret['customer']);

        // Checking for unwanted data
        $res = $mite->createCustomer($name, array('bogus' => ''));

        $this->assertTrue($res->isPost());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());

        $ret = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('customer', $ret);
        $this->assertArrayNotHasKey('bogus', $ret['customer']);

        try {
            $mite->createCustomer('',array());
            $this->assertTrue(false, 'Empty customer name should throw exception');

        } catch (\BadMethodCallException $bme) {
            $this->assertTrue(true, 'Empty name should throw exception');
        }

        try {
            $mite->createCustomer('abc',array('hourly_rate' => 'abx'));
            $this->assertTrue(false, 'Faulty value of hourly_rate should throw an exception');

        } catch (\BadMethodCallException $bme) {
            $this->assertTrue(true);
        }

        try {
            $mite->createCustomer('abc',array('hourly_rates_per_service' => 'abx'));
            $this->assertTrue(false, 'Faulty value of hourly_rates_per_service should throw an exception');

        } catch (\BadMethodCallException $bme) {
            $this->assertTrue(true);
        }

        try {
            $mite->createCustomer('abc',array('active_hourly_rate' => 'abx'));
            $this->assertTrue(false, 'Faulty value of active_hourly_rate should throw an exception');

        } catch (\BadMethodCallException $bme) {
            $this->assertTrue(true);
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::updateCustomer
     * @covers \SysEleven\MiteEleven\MiteClient::prepareCustomerData
     */
    public function testUpdateCustomer()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $options = array('name' => 'somename',
                         'note' => '',
                         'archived' => false,
                         'hourly_rate' => 1,
                         'hourly_rates_per_service' => array(
                             array('id' => 2, 'hourly_rate' => 2)),
                         'active_hourly_rate' => 'hourly_rate');
        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->updateCustomer(123, $options);

        $expected = $options;
        $expected['archived'] = 'false';

        $this->assertTrue($res->isPut());
        $this->assertEquals('https://www.example.com/customers/123.json', $res->getUriString());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());

        $ret = json_decode($res->getContent(), true);

        $this->assertArrayHasKey('customer', $ret);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $ret['customer']);
            $this->assertEquals($v, $ret['customer'][$k]);
        }

        try {
            $mite->updateCustomer(123, array('name' => ''));
            $this->fail('Expected test to fail because customer name is empty');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Name: expected string with len >= 0', $bme->getMessage());
        }

        try {
            $mite->updateCustomer('abx', array('name' => ''));
            $this->fail('Expected test to fail because customer id is not an integer');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('ID must be a positive integer got: abx', $bme->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound',404);

            $mite = new MiteClient($rest);

            $mite->updateCustomer(123, array());
            $this->fail('Expected test to throw CustomerNotFoundException');

        } catch (\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\CustomerNotFoundException',$e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','Error',403);

            $mite = new MiteClient($rest);

            $mite->updateCustomer(123, array());
            $this->fail('Expected test to throw Exception');

        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception',$e);
            $this->assertEquals(403, $e->getCode());
        }






    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::deleteCustomer
     */
    public function testDeleteCustomer()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->deleteCustomer(123);

        $this->assertTrue($request->isDelete());
        $this->assertEquals('https://www.example.com/customers/123.json', $request->getUri());

        try {
            $mite->deleteCustomer(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->deleteCustomer('abc');
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteCustomer(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\CustomerNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteCustomer(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::listProjects
     */
    public function testListProjects()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listProjects('test', 10, 1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/projects.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::listArchivedProjects
     */
    public function testListArchivedProjects()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listArchivedProjects('test', 10, 1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/projects/archived.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::searchProjects
     * @expectedException \BadMethodCallException
     */
    public function testSearchProjects()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturn(array(1));

        $mite = new MiteClient($rest);

        $res = $mite->searchProjects('test');

        $this->assertEquals(array(1,1),$res);

        $mite->searchProjects('t');
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getProject
     */
    public function testGetProject()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return array('project' => $request); } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->getProject(123);

        $this->assertTrue($request->isGet());

        $this->assertEquals('https://www.example.com/projects/123.json', $request->getUri());

        try {
            $mite->getProject(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->getProject('abc');
            $this->fail('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andReturn(array());

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getProject(123);
            $this->fail('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ProjectNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getProject(123);
            $this->fail('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ProjectNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getProject(123);
            $this->fail('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

    public function testPrepareProjectData()
    {
        $mite = new MiteClient(new RestClient('123',123));

        $data = array('name' => '123',
                      'note' => '123',
                      'budget' => 1,
                      'budget_type' => 'minutes',
                      'archived' => false,
                      'customer_id' => 1,
                      'hourly_rate' => 1,
                      'hourly_rates_per_service' => array(),
                      'active_hourly_rate' => 'hourly_rate');

        $expected = $data;
        $expected['archived'] = 'false';

        $res = $mite->prepareProjectData($data);

        $this->assertArrayHasKey('project', $res);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $res['project']);
            $this->assertEquals($v, $res['project'][$k]);
        }

        $res = $mite->prepareProjectData(array('name' => null, 'bogus' => '123'));
        $this->assertArrayHasKey('project', $res);
        $this->assertCount(0, $res['project']);


        try {

            $mite->prepareProjectData(array('name' => ''));
            $this->fail('Expected \BadMethodCallException because of empty name');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Name: You must provide a name for the project', $bme->getMessage());
        }

        try {

            $mite->prepareProjectData(array('budget' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Budget: expected positive integer >= 0 got: abx', $bme->getMessage());
        }

        try {

            $mite->prepareProjectData(array('budget_type' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong value');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Budget type: expected one of (minutes|cent) got: abx', $bme->getMessage());
        }

        try {

            $mite->prepareProjectData(array('customer_id' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Customer: expected positive integer >= 0 got: abx', $bme->getMessage());
        }

        try {

            $mite->prepareProjectData(array('hourly_rates_per_service' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Hourly Rates Per Service: expected array with count >= 0 got: abx', $bme->getMessage());
        }

        try {

            $mite->prepareProjectData(array('hourly_rate' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Hourly Rate: expected int >= 0 got: abx', $bme->getMessage());
        }

        try {

            $mite->prepareProjectData(array('active_hourly_rate' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Active hourly rate: expected one of "hourly_rate", "hourly_rates_per_service" got: abx', $bme->getMessage());
        }

        try {

            $mite->prepareProjectData(array('archived' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('archived: expected value to be one of true or false got: abx', $bme->getMessage());
        }

    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::createProject
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must provide a name for the project
     */
    public function testCreateProject()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $name = 'somename';
        $options = array('note' => '123',
                         'budget' => 1,
                         'budget_type' => 'minutes',
                         'archived' => false,
                         'customer_id' => 1,
                         'hourly_rate' => 1,
                         'hourly_rates_per_service' => array(),
                         'active_hourly_rate' => 'hourly_rate');
        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->createProject($name, $options);

        $expected = $options;
        $expected['name'] = $name;
        $expected['archived'] = 'false';

        $this->assertTrue($res->isPost());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());
        $this->assertEquals('https://www.example.com/projects.json',$res->getUriString());

        $ret = json_decode($res->getContent(), true);

        $this->assertArrayHasKey('project', $ret);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $ret['project']);
            $this->assertEquals($v, $ret['project'][$k]);
        }

        $mite->createProject(null);
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::updateProject
     */
    public function testUpdateProject()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $options = array('name' => 'somename',
                         'note' => '123',
                         'budget' => 1,
                         'budget_type' => 'minutes',
                         'archived' => false,
                         'customer_id' => 1,
                         'hourly_rate' => 1,
                         'hourly_rates_per_service' => array(),
                         'active_hourly_rate' => 'hourly_rate');
        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->updateProject(123, $options);

        $expected = $options;
        $expected['archived'] = 'false';

        $this->assertTrue($res->isPut());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());
        $this->assertEquals('https://www.example.com/projects/123.json',$res->getUriString());

        $ret = json_decode($res->getContent(), true);

        $this->assertArrayHasKey('project', $ret);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $ret['project']);
            $this->assertEquals($v, $ret['project'][$k]);
        }

        try {
            $mite->updateProject(null);
            $this->fail('Expected \BadMethodCallException because project id was not given');

        } catch (\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: ', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound',404);

            $mite = new MiteClient($rest);

            $mite->updateProject(123);
            $this->fail('Expected ProjectNotFoundException');

        } catch (\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ProjectNotFoundException', $e);
            $this->assertEquals('Cannot find entry: 123', $e->getMessage());
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound',403);

            $mite = new MiteClient($rest);

            $mite->updateProject(123);
            $this->fail('Expected \Exception');

        } catch (\Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }


    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::deleteProject
     */
    public function testDeleteProject()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->deleteProject(123);

        $this->assertTrue($request->isDelete());

        $this->assertEquals('https://www.example.com/projects/123.json', $request->getUri());

        try {
            $mite->deleteProject(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->deleteProject('abc');
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteProject(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ProjectNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteProject(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::listServices
     */
    public function testListServices()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listServices('test', 10, 1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/services.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::listArchivedServices
     */
    public function testListArchivedServices()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listArchivedServices('test', 10, 1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/services/archived.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::searchServices
     * @expectedException \BadMethodCallException
     */
    public function testSearchServices()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturn(array(1));

        $mite = new MiteClient($rest);

        $res = $mite->searchServices('test');

        $this->assertEquals(array(1,1),$res);

        $mite->searchServices('t');
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getService
     */
    public function testGetService()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return array('service' => $request); } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->getService(123);

        $this->assertTrue($request->isGet());

        $this->assertEquals('https://www.example.com/services/123.json', $request->getUri());

        try {
            $mite->getService(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->getService('abc');
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andReturn(array());

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getService(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ServiceNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getService(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ServiceNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getService(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Name: You must provide a name for the service
     */
    public function testCreateService()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        $name = 'somename';
        $options = array(
                      'note' => '123',
                      'hourly_rate' => 1,
                      'billable' => true,
                      'archived' => true);
        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->createService($name, $options);

        $expected = $options;
        $expected['name'] = $name;
        $expected['archived'] = 'true';
        $expected['billable'] = 'true';

        $this->assertTrue($res->isPost());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());
        $this->assertEquals('https://www.example.com/services.json',$res->getUriString());

        $ret = json_decode($res->getContent(), true);

        $this->assertArrayHasKey('service', $ret);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $ret['service']);
            $this->assertEquals($v, $ret['service'][$k]);
        }

        $mite->createService(null);
    }

    /**
     *
     */
    public function testUpdateService()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

       $data = array('name' => '123',
                      'note' => '123',
                      'hourly_rate' => 1,
                      'billable' => true,
                      'archived' => true,
                      'update_hourly_rate_on_time_entries' => true);

        $expected = array('name' => '123',
                      'note' => '123',
                      'hourly_rate' => 1,
                      'billable' => 'true',
                      'archived' => 'true',
                      'update_hourly_rate_on_time_entries' => 'true');
        /**
         * @type \Zend\Http\Request $res
         */
        $res = $mite->updateService(123, $data);

        $this->assertTrue($res->isPut());
        $this->assertEquals('application/json',$res->getHeader('Content-Type')->getFieldValue());
        $this->assertEquals('https://www.example.com/services/123.json',$res->getUriString());

        $ret = json_decode($res->getContent(), true);

        $this->assertArrayHasKey('service', $ret);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $ret['service']);
            $this->assertEquals($v, $ret['service'][$k]);
        }

        try {
            $mite->updateService(null);
            $this->fail('Expected \BadMethodCallException because project id was not given');

        } catch (\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: ', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound',404);

            $mite = new MiteClient($rest);

            $mite->updateService(123);
            $this->fail('Expected ServiceNotFoundException');

        } catch (\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ServiceNotFoundException', $e);
            $this->assertEquals('Cannot find entry: 123', $e->getMessage());
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound',403);

            $mite = new MiteClient($rest);

            $mite->updateService(123);
            $this->fail('Expected \Exception');

        } catch (\Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testPrepareServiceData()
    {
        $mite = new MiteClient(new RestClient('123',123));

        $data = array('name' => '123',
                      'note' => '123',
                      'hourly_rate' => 1,
                      'billable' => true,
                      'archived' => true,
                      'update_hourly_rate_on_time_entries' => true);

        $expected = array('name' => '123',
                      'note' => '123',
                      'hourly_rate' => 1,
                      'billable' => 'true',
                      'archived' => 'true',
                      'update_hourly_rate_on_time_entries' => 'true');

        $res = $mite->prepareServiceData($data);

        $this->assertArrayHasKey('service', $res);

        foreach ($expected AS $k => $v) {
            $this->assertArrayHasKey($k, $res['service']);
            $this->assertEquals($v, $res['service'][$k]);
        }

        $res = $mite->prepareServiceData(array('name' => null, 'bogus' => '123'));
        $this->assertArrayHasKey('service', $res);
        $this->assertCount(0, $res['service']);


        try {

            $mite->prepareServiceData(array('name' => ''));
            $this->fail('Expected \BadMethodCallException because of empty name');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Name: You must provide a name for the service', $bme->getMessage());
        }

        try {

            $mite->prepareServiceData(array('hourly_rate' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Hourly Rate: expected int >= 0 got: abx', $bme->getMessage());
        }

        try {
            // Should work for other boolean keys as well
            $mite->prepareServiceData(array('archived' => 'abx'));
            $this->fail('Expected \BadMethodCallException because of wrong type');

        } catch (\Exception $bme) {
            $this->assertInstanceOf('\BadMethodCallException', $bme);
            $this->assertEquals('Archived: expected true or false got: abx', $bme->getMessage());
        }

    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::deleteService
     */
    public function testDeleteService()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->deleteService(123);

        $this->assertTrue($request->isDelete());

        $this->assertEquals('https://www.example.com/services/123.json', $request->getUri());

        try {
            $mite->deleteService(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->deleteService('abc');
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteService(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\ServiceNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->deleteService(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

        /**
     * @covers \SysEleven\MiteEleven\MiteClient::listUsers
     */
    public function testListUsers()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listUsers('test','@mail.com', 10, 1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals('@mail.com', $request->getQuery('email'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/users.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::listArchivedUsers
     */
    public function testListArchivedUsers()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->listArchivedUsers('test','@mail.com', 10, 1);

        $this->assertTrue($request->isGet());
        $this->assertEquals('test', $request->getQuery('name'));
        $this->assertEquals('@mail.com', $request->getQuery('email'));
        $this->assertEquals(10, $request->getQuery('limit'));
        $this->assertEquals(1, $request->getQuery('page'));
        $this->assertEquals('https://www.example.com/users/archived.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::searchUsers
     * @expectedException \BadMethodCallException
     */
    public function testSearchUsers()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturn(array(1));

        $mite = new MiteClient($rest);

        $res = $mite->searchUsers('test','@mail.com');

        $this->assertEquals(array(1,1),$res);

        $mite->searchUsers('t','t');
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getUser
     */
    public function testGetUser()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return array('user' => $request); } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->getUser(123);

        $this->assertTrue($request->isGet());

        $this->assertEquals('https://www.example.com/users/123.json', $request->getUri());

        try {
            $mite->getUser(-1);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: -1', $e->getMessage());
        }

        try {
            $mite->getUser('abc');
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\BadMethodCallException', $e);
            $this->assertEquals('ID must be a positive integer got: abc', $e->getMessage());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andReturn(array());

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getUser(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\UserNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\Exception','NotFound', 404);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getUser(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\UserNotFoundException', $e);
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
            $rest->shouldReceive('call')->andThrow('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException','SomeOtherError', 444);

            $mite = new MiteClient($rest);

            /**
            * @var \Zend\Http\Request $request
            */
            $mite->getUser(123);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\Exception $e) {
            $this->assertInstanceOf('\SysEleven\MiteEleven\Exceptions\MiteRuntimeException', $e);
            $this->assertEquals(444, $e->getCode());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getAccount
     */
    public function testGetAccount()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->getAccount();

        $this->assertTrue($request->isGet());

        $this->assertEquals('https://www.example.com/account.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::getMyself
     */
    public function testGetMyself()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request; } );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->getMyself();

        $this->assertTrue($request->isGet());

        $this->assertEquals('https://www.example.com/myself.json', $request->getUri());
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::prepareTimeEntryFilters
     */
    public function testPrepareTimeEntryFilters()
    {
        $mite = new MiteClient(new RestClient('https:/localhost', '123456'));

        $data = array('customer_id' => 1,
                      'project_id' => 2,
	                  'service_id' => array(1,2,3),
                      'user_id' => array(1,0),
                      'billable' => true,
                      'note' => 'test',
                      'at' => 'today',
                      'from' => '2012-12-01',
                      'to' => '2013-01-01');
        $expected = $data;
        $expected['service_id'] = '1,2,3';
        $expected['user_id'] = '1';
        $expected['billable'] = 'true';

        $res = $mite->prepareTimeEntryFilters($data, false);
        $this->assertEquals($expected, $res);

        // Testing valid at filter
        $data = array('at' => '2012-01-01');
        $res = $mite->prepareTimeEntryFilters($data);
        $this->assertEquals($data, $res);

        // Testing unwanted, empty or wrong element removal
        $data = array('bogus' => 1,
                      'customer_id' => array(),
                      'note' => '',
                      'from' => '123',
                      'at' => '123');

        $res = $mite->prepareTimeEntryFilters($data, false);
        $this->assertEquals(array(), $res);


        try {
            // Testing boolean check
            $data = array('billable' => 'yes');
            $expected = array('billable' => 'true');

            $res = $mite->prepareTimeEntryFilters($data, false);
            $this->assertEquals($expected, $res);

            $res = $mite->prepareTimeEntryFilters(array('billable' => 99));
            $this->assertEquals(array(), $res);

            $mite->prepareTimeEntryFilters(array('billable' => 99), true);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch(\BadMethodCallException $bmc) {
            $this->assertEquals('Filter: billable: 99 is  not one of true or false or their abbreviations',$bmc->getMessage());
        }



        $invalidData = array();
        $invalidData['bogus'] = 1;
        $invalidData['customer_id'] = array(0);
        $invalidData['project_id'] = array();
        $invalidData['note'] = '';
        $invalidData['from'] = '123';
        $invalidData['at'] = '123';

        foreach ($invalidData AS $k => $v) {

            try {

                $mite->prepareTimeEntryFilters(array($k => $v), true);
                $this->assertFalse('Expected Test to throw Exception because condition should not be met');

            } catch (\BadMethodCallException $bmc) {

                switch ($k) {
                    case 'bogus':
                        $this->assertEquals('Filter: bogus: is not supported',$bmc->getMessage());
                    break;
                    case 'customer_id':
                        $this->assertEquals('Filter: customer_id: no valid values provided or some of the value are invalid', $bmc->getMessage());
                    break;
                    case 'project_id':
                        $this->assertEquals('Filter: project_id: no values provided', $bmc->getMessage());
                    break;
                    case 'note':
                        $this->assertEquals('Filter: note: no values provided', $bmc->getMessage());
                    break;
                    case 'from':
                        $this->assertEquals('Filter: from: 123 is not a valid date', $bmc->getMessage());
                    break;
                    case 'from':
                        $this->assertEquals('Filter: at: 123 is not a valid date or keyword', $bmc->getMessage());
                    break;
                }
            }

        }


    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::prepareLimit
     */
    public function testPrepareLimit()
    {
        try {

            $mite = new MiteClient(new RestClient('https:/localhost', '123456'));
            $res = $mite->prepareLimit(10, null);

            $this->assertArrayHasKey('limit', $res);
            $this->assertEquals(10, $res['limit']);

            $mite->prepareLimit(10, 0);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (\BadMethodCallException $bmc ) {
            $this->assertEquals('page must be greater than 0', $bmc->getMessage());
        }

        try {

            $mite = new MiteClient(new RestClient('https:/localhost', '123456'));
            $mite->prepareLimit(0, null);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (\BadMethodCallException $bmc ) {
            $this->assertEquals('limit must be greater than 0', $bmc->getMessage());
        }

        try {

            $mite = new MiteClient(new RestClient('https:/localhost', '123456'));
            $mite->prepareLimit(null, 10);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (\BadMethodCallException $bmc ) {
            $this->assertEquals('Page is only working with limit', $bmc->getMessage());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::prepareTimeEntryGrouping
     */
    public function testPrepareTimeEntryGrouping()
    {
        try {

            $mite = new MiteClient(new RestClient('https:/localhost', '123456'));

            $data = array('customer', 'project', 'service', 'user', 'day',
	                           'week', 'month', 'year');

            $faulty = array('customer', 'project', 'service', 'user', 'day',
	                           'week', 'month', 'year','dummy');

            $res = $mite->prepareTimeEntryGrouping($faulty);
            $this->assertEquals($data, $res);

            $mite->prepareTimeEntryGrouping($faulty, true);
            $this->assertFalse('Expected Test to throw Exception because condition should not be met');

        } catch (\BadMethodCallException $bmc ) {
            $this->assertEquals('GroupBy: dummy: is not supported', $bmc->getMessage());
        }
    }

    /**
     * @covers \SysEleven\MiteEleven\MiteClient::setClient
     * @covers \SysEleven\MiteEleven\MiteClient::getClient
     */
    public function testSetClient()
    {
        $mite = new MiteClient(new RestClient('https://localhost', 1234567));

        $mite->setClient(new RestClient('https://localhost', 123456));
        $client = $mite->getClient();

        $this->assertInstanceOf('\SysEleven\MiteEleven\RestClient', $client);
        $this->assertEquals(123456, $client->getKey());
    }


    /**
     * @covers \SysEleven\MiteEleven\MiteClient::callApi
     * @expectedException \BadMethodCallException
     */
    public function testCallApi()
    {
        $rest = m::mock('\SysEleven\MiteEleven\RestClient[call]', array('https://www.example.com', 'key'));
        $rest->shouldReceive('call')->andReturnUsing(function ($request, $client, $options) { return $request;} );

        $mite = new MiteClient($rest);

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->callApi('GET', '/dummy', array('test' => 1));

        $this->assertTrue($request->isGet());
        $this->assertEquals(1, $request->getQuery('test'));

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->callApi('DELETE', '/dummy', array('test' => 1));

        $this->assertTrue($request->isDelete());
        $this->assertEquals(1, $request->getQuery('test'));

        /**
         * @var \Zend\Http\Request $request
         */
        $request = $mite->callApi('POST', '/dummy', array('test' => 1));

        $this->assertTrue($request->isPost());
        $this->assertEquals('application/json',$request->getHeaders('Content-Type')->getFieldValue());

        $mite->callApi('POST','',array());
    }
}
