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

namespace SysEleven\MiteEleven;

use SysEleven\MiteEleven\Exceptions\MiteRuntimeException;
use SysEleven\MiteEleven\Exceptions\ApiNotAvailableException;
use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Http\Response;

/**
 * Defines method for the rest client used by the api
 *
 * @author M. Seifert <m.seifert@syseleven.de
 * @version 0.9.1
 * @package SysEleven\MiteEleven
 */
interface RestClientInterface
{
    /**
     * Sets the user agent
     *
     * @param $agent
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setAgent($agent);

    /**
     * Gets the user agent
     *
     * @return string
     */
    public function getAgent();

    /**
     * Sets the api key
     *
     * @param $key
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setKey($key);

    /**
     * Gets the api key
     *
     * @return string
     */
    public function getKey();

    /**
     * Sets the mite api url
     *
     * @param $url
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setUrl($url);

    /**
     * Gets the mite api url
     *
     * @return string
     */
    public function getUrl();

    /**
     * Sets options for the connection adapter and the http client
     *
     * @param array $clientOptions
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setClientOptions($clientOptions);

    /**
     * Returns the client options
     *
     * @return array
     */
    public function getClientOptions();


    /**
     * Set expected content type
     *
     * @param string $expectedContentType
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setExpectedContentType($expectedContentType);

    /**
     * Get expected content type
     *
     * @return string
     */
    public function getExpectedContentType();

    /**
     * Set the password
     *
     * @param null $password
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setPassword($password);

    /**
     * Get the password
     *
     * @return string
     */
    public function getPassword();
    /**
     * Set username
     *
     * @param null $username
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setUsername($username);

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername();

    /**
     * Creates a new Zend Http Client instance and returns it. You can overwrite
     * this method to fit your needs.
     *
     * @param array $options
     *
     * @return \Zend\Http\Client
     */
    public function getClient(array $options = array());

    /**
     * Creates a new Request object and returns it
     *
     * @param string $method
     * @param array  $headers
     *
     * @throws \RuntimeException
     * @return \Zend\Http\Request
     */
    public function createRequest($method = 'GET', array $headers = array());


    /**
     * Make a API call
     *
     * @param \Zend\Http\Request $request
     * @param \Zend\Http\Client  $client
     * @param array              $options
     *
     * @return array|string
     * @throws ApiNotAvailableException
     * @throws \SysEleven\MiteEleven\Exceptions\MiteRuntimeException
     */
    public function call(Request $request, Client $client = null, array $options = array());

}