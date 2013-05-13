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

use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Http\Client\Adapter\Exception\TimeoutException;
use Zend\Http\Client\Adapter\AdapterInterface;

use SysEleven\MiteEleven\Exceptions\ApiNotAvailableException;
use SysEleven\MiteEleven\Exceptions\MiteRuntimeException;
use SysEleven\MiteEleven\RestClientInterface;
 
/**
 * Rest client for accessing the api.
 *
 * @author M. Seifert <m.seifert@syseleven.de
 * @version 0.9.1
 * @package SysEleven\MiteEleven
 */ 
class RestClient implements RestClientInterface
{
    /**
     * Mite API key
     *
     * @type string
     */
    protected $_key;

    /**
     * Mite username optional you should use the api key instead
     *
     * @type string
     */
    protected $_username = null;

    /**
     * Mite password optional you should use the api key instead
     * @type string
     */
    protected $_password = null;

    /**
     * Url of your mite account
     *
     * @type string
     */
    protected $_url = '';

    /**
     * User agent to pass to the api
     * @type string
     */
    protected $_agent = 'SysEleven Mite Client 1.0';

    /**
     * Options for constructing the http client
     *
     * @type array
     */
    protected $_clientOptions = array('sslverifypeer' => false, );

    /**
     * Expected content types for the response
     *
     * @type array
     */
    protected $_expectedContentType = 'application/json';

    /**
     * Adapter to use for connection
     *
     * @type Client\Adapter\AdapterInterface $adapter
     */
    protected $_adapter;

    /**
     * Authentication Errors
     *
     * @type int
     */
    const AUTHENTICATION_ERROR = 403;

    /**
     * Encoding Error
     * @type int
     */
    const ENCODING_ERROR      = 2001;


    /**
     * Initializes the Client and sets some options.
     *
     * @param string $url   Url of your mite account
     * @param string $key   Api key
     * @param array  $options
     */
    public function __construct($url, $key, array $options = array())
    {
        $this->_url = $url;
        $this->_key = $key;

        $this->setOptions($options);
    }

    /**
     * Sets the client adapter
     *
     * @param AdapterInterface $adapter
     *
     * @return $this
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->_adapter = $adapter;

        return $this;
    }

    /**
     * Gets the client adapter or null if none is configured
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Sets the options for the service, it first checks if a dedicated setter
     * for the key is available if not not it checks if there is a protected
     * property _key and then tries to find a property key, if key is not a
     * property of the class it is skipped (no overloading permitted here)
     *
     * @param array $options
     * @param array $ignoreSetters
     *
     * @return MiteClient
     */
    public function setOptions(array $options = array(), array $ignoreSetters = array())
    {
        if (is_array($options) && 0 != count($options)) {
            $ref = new \ReflectionClass($this);
            foreach ($options AS $k => $v) {
                if (is_numeric($k) || 0 == strlen($k)) {
                    continue;
                }

                // Look for a dedicated setter first, must be in the form
                // setCamelCasedKeyName
                $m = sprintf(
                    'set%s'
                    , str_replace(' ', '', ucwords(str_replace('_', ' ', $k)))
                );

                if ($ref->hasMethod($m) && !in_array(strtolower($m), $ignoreSetters)) {
                    $this->$m($v);
                    continue;
                }

                // Protected Variables are underscored by convention
                if ($ref->hasProperty('_' . $k)) {
                    $name = '_' . $k;
                    $this->$name = $v;
                    continue;
                }

                // camelCased
                $pro = '_'.str_replace(' ', '', ucwords(str_replace('_', ' ', $k)));
                $ft = strtolower(substr($pro,0,2));
                $pro = substr_replace($pro, $ft, 0, 2);

                if ($ref->hasProperty($pro)) {
                    $this->$pro = $v;
                    continue;
                }
            }
        }

        return $this;
    }

    /**
     * Sets the user agent
     *
     * @param $agent
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setAgent($agent)
    {
        $this->_agent = $agent;

        return $this;
    }

    /**
     * Gets the user agent
     *
     * @return string
     */
    public function getAgent()
    {
        return $this->_agent;
    }

    /**
     * Sets the api key
     *
     * @param $key
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setKey($key)
    {
        $this->_key = $key;

        return $this;
    }

    /**
     * Gets the api key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * Sets the mite api url
     *
     * @param $url
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setUrl($url)
    {
        $this->_url = $url;

        return $this;
    }

    /**
     * Gets the mite api url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * Sets options for the adapter
     *
     * @param array $clientOptions
     *
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setClientOptions($clientOptions)
    {
        $this->_clientOptions = $clientOptions;

        return $this;
    }

    /**
     * Retunrs the client options
     *
     * @return array
     */
    public function getClientOptions()
    {
        return $this->_clientOptions;
    }


        /**
     * Set expected content type
     *
     * @param string $expectedContentType
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setExpectedContentType($expectedContentType)
    {
        $this->_expectedContentType = $expectedContentType;

        return $this;
    }

    /**
     * Get expected content type
     *
     * @return string
     */
    public function getExpectedContentType()
    {
        return $this->_expectedContentType;
    }

    /**
     * Set the password
     *
     * @param null $password
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setPassword($password)
    {
        $this->_password = $password;

        return $this;
    }

    /**
     * Get the password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /**
     * Set username
     *
     * @param null $username
     * @return \SysEleven\MiteEleven\MiteClient
     */
    public function setUsername($username)
    {
        $this->_username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->_username;
    }

    /**
     * Creates a new Zend Http Client instance and returns it. You can overwrite
     * this method to fit your needs.
     *
     * @param array $options
     *
     * @return \Zend\Http\Client
     */
    public function getClient(array $options = array())
    {
        $client = new Client();

        if (null != ($adapter = $this->getAdapter())) {
            $client->setAdapter($adapter);
        }

        if (!is_null($this->_username) && !is_null($this->_password)
            && (is_null($this->_key) || 0 == strlen($this->_key))) {
            $client->setAuth($this->_username, $this->_password);
        }

        if (!is_array($this->_clientOptions)) {
            $this->_clientOptions = array();
        }

        $options = array_merge($this->_clientOptions, $options);

        $client->setOptions($options);

        return $client;
    }

    /**
     * Creates a new Request object and returns it
     *
     * @param string $method
     * @param array  $headers
     *
     * @throws \RuntimeException
     * @return \Zend\Http\Request
     */
    public function createRequest($method = 'GET', array $headers = array())
    {
        if (!in_array(
            $method, array('GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD')
        )
        ) {
            throw new \RuntimeException('Method ' . $method . ' not supported');
        }

        $_headers = array();
        if (!is_null($this->_key) && 0 != strlen($this->_key)) {
            $_headers['X-MiteApiKey'] = $this->_key;
        }

        if (!is_null($this->_agent) && 0 != strlen($this->_agent)) {
            $_headers['User-Agent'] = $this->_agent;
        }

        if (!in_array($method, array('GET','DELETE'))) {
            $_headers['Content-Type'] = 'application/json';
        }

        $headers = array_merge_recursive($_headers, $headers);

        $request = new Request();
        $request->setMethod($method);
        $request->getHeaders()->addHeaders($headers);

        return $request;
    }

    /**
     * Make a API call
     *
     * @param \Zend\Http\Request $request
     * @param \Zend\Http\Client  $client
     * @param array              $options
     *
     * @return array|string
     * @throws ApiNotAvailableException
     * @throws MiteRuntimeException
     * @throws \SysEleven\MiteEleven\Exceptions\MiteRuntimeException
     */
    public function call(Request $request, Client $client = null, array $options = array())
    {
        if (is_null($client)) {
            $client = $this->getClient();
        }

        try {
            /**
             * @var \Zend\Http\Response $response
             */
            $response = $client->dispatch($request);

        }
        catch (TimeoutException $te) {
            throw new ApiNotAvailableException($te->getMessage(), $te->getCode());
        }
        catch (\Exception $e) {
            throw new MiteRuntimeException($e->getMessage(),
                $e->getCode(),
                null, $e);
        }

        if ($response->isSuccess()) {
            $type = $response->getHeaders()->get('Content-Type')
                ->getFieldValue();

            $expected = (array_key_exists('expected',$options))?
                        $options['expected']:$this->getExpectedContentType();

            if (!preg_match('/^'.preg_quote($expected,'/').'/',$type)) {
                $message = 'Wrong type of response, expected: ' .
                    $expected . ' got: '
                    . $type;

                throw new MiteRuntimeException($message,
                    self::ENCODING_ERROR,
                    $response);
            }

            if (preg_match('/^application\/json/', $type)) {
                if ('' == trim($response->getBody())) {
                    // Dunno what it is but mite doesn't seem to return
                    // valid json in case of a PUT or DELETE
                    // or the response is empty

                    return true;
                }

                $data = json_decode($response->getBody(), true);

                if (!is_array($data)) {
                    $message = 'Cannot decode data';

                    throw new MiteRuntimeException($message,
                        self::ENCODING_ERROR,
                        $response);
                }

                return $data;
            }

            return $response->getBody();
        }

        if (403 == $response->getStatusCode()) {
            throw new MiteRuntimeException($response->getBody(),
                self::AUTHENTICATION_ERROR,
                $response);
        }

        $type = $response->getHeaders()->get('Content-Type')->getFieldValue();

        if (preg_match('/^application\/json/', $type)) {
            $data = json_decode($response->getBody(), true);
            throw new MiteRuntimeException($data,
                                          $response->getStatusCode());
        }

        throw new MiteRuntimeException($response->getBody(),
            $response->getStatusCode(),
            $response);
    }
}
