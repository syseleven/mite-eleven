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
 * @package SysEleven\MiteEleven
 */

namespace SysEleven\MiteEleven\Exceptions;

use Zend\Http\Response;
 
/**
 * CustomerNotFoundException, should be thrown if a customer object cannot be
 * found in the backend
 *
 * @author M. Seifert <m.seifert@syseleven.de
 * @version 0.9.1
 * @package SysEleven\MiteEleven
 */ 
class MiteRuntimeException extends \Exception
{

    /**
     * Last http response
     *
     * @var \Zend\Http\Response
     */
    public $response;

    /**
     * Error data, only there if response returned a json response
     *
     * @var array
     */
    public $data;

    /**
     * Initializes the exception and sets the data, if message is an array it
     * will be stored in $data and a generic message will be set.
     *
     * @param string     $message
     * @param int        $code
     * @param null       $extraData
     * @param \Exception $previous
     */
    public function __construct($message, $code, $extraData = null, \Exception $previous = null)
    {
        if (is_array($message)) {
            $this->data = $message;

            $message = 'Mite Error';
        }

        if ($extraData instanceof Response) {
            $this->response = $extraData;
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the error data
     *
     * @return array
     */
    public function getErrorData()
    {
        return $this->data;
    }

    /**
     * Return the response if any
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

}
