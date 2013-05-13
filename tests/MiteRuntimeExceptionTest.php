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
use SysEleven\MiteEleven\Exceptions\MiteRuntimeException;
use Zend\Http\Response;


/**
 * MiteRuntimeExceptionTest
 * @author M. Seifert <m.seifert@syseleven.de
 * @package 
 * @subpackage
 */ 
class MiteRuntimeExceptionTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers \SysEleven\MiteEleven\Exceptions\MiteRuntimeException::__construct
     * @covers \SysEleven\MiteEleven\Exceptions\MiteRuntimeException::getErrorData
     * @covers \SysEleven\MiteEleven\Exceptions\MiteRuntimeException::getResponse
     *
     */
    public function testConstruct()
    {
        $me = new MiteRuntimeException('test', 123);

        $this->assertEquals('test', $me->getMessage());
        $this->assertEquals(123, $me->getCode());

        $me = new MiteRuntimeException(array(), 123, new Response());

        $this->assertEquals('Mite Error', $me->getMessage());
        $this->assertEquals(123, $me->getCode());
        $this->assertEquals(array(), $me->getErrorData());
        $this->assertInstanceOf('\Zend\Http\Response', $me->getResponse());
    }

}
