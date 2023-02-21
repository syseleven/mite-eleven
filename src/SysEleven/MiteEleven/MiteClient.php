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

use BadMethodCallException;
use DateTime;
use Exception;
use RuntimeException;
use SysEleven\MiteEleven\MiteInterface;
use SysEleven\MiteEleven\Exceptions\CustomerNotFoundException;
use SysEleven\MiteEleven\Exceptions\EntryNotFoundException;
use SysEleven\MiteEleven\Exceptions\ProjectNotFoundException;
use SysEleven\MiteEleven\Exceptions\ServiceNotFoundException;
use SysEleven\MiteEleven\Exceptions\UserNotFoundException;
use SysEleven\MiteEleven\Exceptions\ApiNotAvailableException;
use SysEleven\MiteEleven\Exceptions\MiteRuntimeException;

use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Http\Response;
use SysEleven\MiteEleven\RestClientInterface;


/**
 * Implementation of a simple interface to the mite time tracking api.
 *
 * @author M. Seifert <m.seifert@syseleven.de
 * @version 0.9.1
 * @package SysEleven\MiteEleven
 */
class MiteClient implements MiteInterface
{

    /**
     * Rest client object
     *
     * @type RestClientInterface
     */
    protected $_rest;

    /**
     * Initializes the object and sets the rest handler
     *
     * @param RestClientInterface $rest
     */
    public function __construct(RestClientInterface $rest)
    {
        $this->_rest = $rest;
    }

    /**
     * Sets the rest client used to communicate with the api.
     *
     * @param RestClientInterface $rest
     *
     * @return $this
     */
    public function setClient(RestClientInterface $rest)
    {
        $this->_rest = $rest;

        return $this;
    }

    /**
     * Returns the rest client
     *
     * @return RestClientInterface
     */
    public function getClient()
    {
        return $this->_rest;
    }

    /**
     * Returns a list of time entries optionally filtered by $filter and|or
     * grouped by $group, please refer to the online manual for a detailed
     * description of the options for every parameter. $limit and $page are
     * optional and used for pagination, if omitted all results are returned.
     *
     * @param array $filter
     * @param array $group
     * @param int   $limit
     * @param int   $page
     * @param bool  $strict if true, every unknown or empty filter|group element will result in a BadMethodCallException
     *
     * @throws BadMethodCallException
     * @return array
     * @link https://mite.de/api/gruppierte-zeiten.html
     * @link https://mite.de/api/zeiten.html
     */
    public function listEntries(
        array $filter = array(),
        array $group = array(),
        $limit = null,
        $page = null,
        $strict = false
    ) {

        $params = $this->prepareTimeEntryFilters($filter, $strict);

        $group = $this->prepareTimeEntryGrouping($group, $strict);

        if (0 != count($group)) {
            $params['group_by'] = implode(',',$group);
        }

        $limit = $this->prepareLimit($limit, $page);

        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/time_entries.json',$params);
    }

    /**
     * Returns the entry specified by $id
     *
     * @param int $id
     *
     * @return array
     * @throws EntryNotFoundException
     * @throws \SysEleven\MiteEleven\Exceptions\ApiNotAvailableException
     * @throws BadMethodCallException
     * @throws Exception
     * @throws MiteRuntimeException
     * @link https://mite.de/api/zeiten.html
     */
    public function getEntry($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/time_entries/'.$id.'.json';
            $method = 'GET';
            $params = array();

            $res = $this->callApi($method, $url, $params);

            if (!array_key_exists('time_entry', $res)) {
                throw new EntryNotFoundException('Cannot find entry: '.$id, 404);
            }

            return $res['time_entry'];

        } catch (Exception $re) {
            if (404 === (int) $re->getCode()) {
                throw new EntryNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Creates a new entry, all parameters are optional.
     *
     * @param string[] $data {
     *      @type DateTime $date_at    Optional defaults to now
     *      @type int       $minutes    Optional defaults to 0
     *      @type string    $note       Optional defaults to ''
     *      @type int       $user_id    Can only be set vy the owner or an Administrator
     *      @type int       $project_id Optional defaults to nil
     *      @type int       $service_id Optional defaults to nil
     *      @type bool      $locked     Optional defaults to false
     * }
     *
     * @return array
     *
     * @throws MiteRuntimeException
     * @link https://mite.de/api/zeiten.html
     */
    public function createEntry(array $data = array())
    {
        $params = $this->prepareEntryData($data);

        return $this->callApi('POST', '/time_entries.json', $params);
    }

    /**
     * Updates the entry specified by id.
     *
     * @param int       $id
     * @param string[] $data {
     *      @type DateTime $date_at    Optional defaults to now
     *      @type int       $minutes    Optional defaults to 0
     *      @type string    $note       Optional defaults to ''
     *      @type int       $user_id    Can only be set vy the owner or an Administrator
     *      @type int       $project_id Optional defaults to nil
     *      @type int       $service_id Optional defaults to nil
     *      @type bool      $locked     Optional defaults to false
     * }
     * @param bool $force Forces edit of a locked entry
     *
     * @return bool
     *
     * @throws BadMethodCallException
     * @throws MiteRuntimeException
     * @throws EntryNotFoundException
     * @throws Exception
     * @link https://mite.de/api/zeiten.html
     */
    public function updateEntry($id, array $data = array(), $force = false)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {
            $params = $this->prepareEntryData($data);
            if ($force === true) {
                $params['time_entry']['force'] = 'true';
            }

            return $this->callApi('PUT', '/time_entries/'.$id.'.json', $params);

        } catch (Exception $e) {
            if (404 === (int) $e->getCode()) {
                throw new EntryNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $e;
        }
    }

    /**
     * Prepares the time_entry data for sending
     *
     * @param array $data
     *
     * @return array
     */
    public function prepareEntryData(array $data = array())
    {
        $supported = array('date_at',
                           'minutes',
                           'note',
                           'user_id',
                           'project_id',
                           'service_id',
                           'locked');
        $params = array();
        foreach ($data AS $k => $v) {
            if (!in_array($k, $supported)) {
                continue;
            }

            if (is_null($v)) {
                continue;
            }

            if ($k === 'locked') {
                if (null === ($locked = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                    continue;
                }
                $v = (false === $locked)? 'false':'true';
            }

            if ($v instanceof DateTime ) {
                /**
                 * @type DateTime $v
                 */
                $v = (string) $v->format('Y-m-d');
            }

            $params[$k] = $v;
        }

        return array('time_entry' => $params);
    }

    /**
     * Deletes the time entry.
     *
     * @param int  $id
     *
     * @return boolean
     * @throws BadMethodCallException
     * @throws MiteRuntimeException
     * @throws EntryNotFoundException
     * @throws Exception
     * @link https://mite.de/api/zeiten.html
     */
    public function deleteEntry($id)
    {
        if(false === filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {
            $params = array();
            return $this->callApi(
                'DELETE',
                '/time_entries/'.$id.'.json',
                $params);

        } catch (Exception $e) {
            if (404 === (int) $e->getCode()) {
                throw new EntryNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $e;
        }
    }

    /**
     * Returns a list of customers filtered by $name, $limit and $page are
     * optional and used for pagination, if omitted all results are returned.
     *
     * @param string $name  Name Filter
     * @param int    $limit limit the result to $limit entries
     * @param int    $page  page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @link https://mite.de/api/kunden.html
     */
    public function listCustomers($name = null, $limit = null, $page = null)
    {
        $params = array();
        if (!is_null($name)) {
            $params['name'] = $name;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/customers.json', $params);
    }

    /**
     * Returns a list of archived customers filtered by $name.
     *
     * @param string $name Name Filter
     *
     * @param null   $limit
     * @param null   $page
     *
     * @return array
     * @link https://mite.de/api/kunden.html
     */
    public function listArchivedCustomers($name = null, $limit = null, $page = null)
    {
        $params = array();
        if (!is_null($name)) {
            $params['name'] = $name;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/customers/archived.json', $params);
    }

    /**
     * Searches for active and archived customers. there is no pagination
     * possible, and a search string is mandatory.
     *
     * @param $name
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function searchCustomers($name)
    {
        if (2 > strlen($name)) {
            throw new BadMethodCallException('The search term ust be at least 2 characters long');
        }

        $active = $this->listCustomers($name);
        $archived = $this->listArchivedCustomers($name);

        return array_merge($active, $archived);
    }

    /**
     * Retrieves a single customer $record. When no record is found a
     * CustomerNotFoundException is thrown
     *
     * @param $id
     *
     * @throws BadMethodCallException
     * @throws Exceptions\CustomerNotFoundException
     * @throws Exception
     * @return array
     * @link https://mite.de/api/kunden.html
     */
    public function getCustomer($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/customers/'.$id.'.json';
            $method = 'GET';
            $params = array();

            $res = $this->callApi($method, $url, $params);

            if (!array_key_exists('customer', $res)) {
                throw new CustomerNotFoundException('Cannot find customer: '.$id, 404);
            }

            return $res['customer'];

        } catch (Exception $re) {
            if (404 === (int) $re->getCode()) {
                throw new CustomerNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Creates a new customer entry in the backend, all parameters except
     * $name are optional
     *
     * @param string $name
     * @param string[] $options {
     *      @type string $note
     *      @type bool   $archived
     *      @type int    $hourly_rate              in Cent
     *      @type array  $hourly_rates_per_service array of service => rates
     *      @type string $active_hourly_rate       one of nil|hourly_rate|hourly_rate_per_service
     * }
     * @return array
     * @throws BadMethodCallException
     * @throws MiteRuntimeException
     * @link https://mite.de/api/kunden.html
     */
    public function createCustomer($name, array $options = array())
    {
        if (!is_string($name) || $name === '') {
            throw new BadMethodCallException('Name: you must provide a valid name for the customer got: '.$name);
        }

        $options['name'] = $name;
        $params = $this->prepareCustomerData($options);

        return $this->callApi('POST', '/customers.json', $params);
    }

    /**
     * Updates the customer record specified by $id with the given data,
     * all null data is not sent to the backend. If you want to set a value to
     * null provide the string nil.
     *
     * @param int    $id
     * @param string[] $options {
     *      @type string $name
     *      @type string $note
     *      @type bool   $archived
     *      @type int    $hourly_rate              in Cent
     *      @type array  $hourly_rates_per_service array of service => rates
     *      @type string $active_hourly_rate       one of nil|hourly_rate|hourly_rate_per_service
     * }
     *
     * @return bool
     * @throws MiteRuntimeException
     * @throws BadMethodCallException
     * @throws CustomerNotFoundException
     * @throws Exception
     * @link https://mite.de/api/kunden.html
     */
    public function updateCustomer($id, array $options = array())
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {
            $params = $this->prepareCustomerData($options);
            return $this->callApi('PUT', '/customers/'.$id.'.json', $params);

        } catch (Exception $re) {
            if (404 === (int) $re->getCode()) {
                throw new CustomerNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Prepares and checks the provided data for further processing
     *
     * @param array $data
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function prepareCustomerData(array $data = array())
    {
        $params = array();
        $supported = array('note', 'archived', 'hourly_rate',
                           'hourly_rates_per_service', 'active_hourly_rate', 'name');

        foreach ($data AS $k => $v) {
            if(!in_array($k, $supported, true)) {
                continue;
            }

            if (is_null($v)) {
                continue;
            }

            if ($k === 'name') {
                if ($v == '') {
                    throw new BadMethodCallException('Name: expected string with len >= 0');
                }
                $params['name'] = $v;
                continue;
            }

            if ($k === 'hourly_rates_per_service') {
                if (!is_array($v)) {
                    throw new BadMethodCallException('Hourly Rates Per Service: expected array with count >= 0 got: '.$v);
                }

                $params['hourly_rates_per_service'] = $v;
                continue;
            }

            if ($k === 'hourly_rate') {
                if (false === filter_var($v, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
                    throw new BadMethodCallException('Hourly Rate: expected int >= 0 got: '.$v);
                }
                $params['hourly_rate'] = $v;
                continue;
            }

            if ($k === 'active_hourly_rate') {
                if (!in_array($v, array('hourly_rate', 'hourly_rates_per_service'))) {
                    throw new BadMethodCallException('Active hourly rate: expected one of "hourly_rate", "hourly_rates_per_service" got: '.$v);
                }

                $params['active_hourly_rate'] = $v;
                continue;
            }

            if ($k === 'archived') {
                if (null !== ($archived = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                    $params['archived'] = (false === $archived)? 'false':'true';
                }
                continue;
            }

            $params[$k] = $v;
        }

        return array('customer' => $params);
    }

    /**
     * Deletes the given customer. If you try to delete a project which has
     * projects left you will receive an error.
     *
     * @param int $id
     *
     * @throws BadMethodCallException
     * @throws Exceptions\CustomerNotFoundException
     * @throws Exception
     * @return bool
     * @link https://mite.de/api/kunden.html
     */
    public function deleteCustomer($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/customers/'.$id.'.json';
            $method = 'DELETE';
            $params = array();

            return $this->callApi($method, $url, $params);

        } catch (Exception $re) {
            if (404 === (int) $re->getCode()) {
                throw new CustomerNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Returns a list of projects optionally filtered by $name. $limit and
     * $page are optional and used for pagination, if omitted all
     * results are returned.
     *
     * @param string $name
     * @param int    $limit
     * @param int    $page page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     * @param int    $customerId
     *
     * @return array
     * @throws BadMethodCallException
     * @link https://mite.de/api/projekte.html
     */
    public function listProjects($name = null, $limit = null, $page = null, $customerId = null)
    {
        $params = array();
        if (!is_null($name)) {
            $params['name'] = $name;
        }
        if (!is_null($customerId)) {
            $params['customer_id'] = $customerId;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/projects.json', $params);
    }

    /**
     * Returns a list of archived projects optionally filtered by $name.
     *
     * @param string $name
     * @param int    $limit
     * @param int    $page
     * @param int    $customerId
     *
     * @return array
     * @link https://mite.de/api/projekte.html
     */
    public function listArchivedProjects($name = null, $limit = null, $page = null, $customerId = null)
    {
        $params = array();
        if (!is_null($name)) {
            $params['name'] = $name;
        }
        if (!is_null($customerId)) {
            $params['customer_id'] = $customerId;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/projects/archived.json', $params);
    }

    /**
     * Searches for active and archived projects. there is no pagination
     * possible, and a search string is mandatory.
     *
     * @param $name
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function searchProjects($name)
    {
        if (2 > strlen($name)) {
            throw new BadMethodCallException('The search term ust be at least 2 characters long');
        }

        $active = $this->listProjects($name);
        $archived = $this->listArchivedProjects($name);

        return array_merge($active, $archived);
    }

    /**
     * Returns the project specified by $id.
     *
     * @param int $id
     *
     * @throws Exceptions\ProjectNotFoundException
     * @throws BadMethodCallException
     * @throws Exception
     * @return array
     * @link https://mite.de/api/projekte.html
     */
    public function getProject($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/projects/'.$id.'.json';
            $method = 'GET';
            $params = array();

            $res = $this->callApi($method, $url, $params);

            if (!array_key_exists('project', $res)) {
                throw new ProjectNotFoundException('Cannot find project: '.$id, 404);
            }

            return $res['project'];

        } catch (Exception $re) {
            if (404 === (int) $re->getCode()) {
                throw new ProjectNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Creates a new project, all parameter except $name are optional.
     *
     * @param string $name
     * @param string[] $options {
     *      @type string $note
     *      @type int    $budget
     *      @type string $budget_type (minutes)|cents
     *      @type bool   $archived
     *      @type int    $customer_id
     *      @type int    $hourly_rate
     *      @type array  $hourly_rates_per_service
     *      @type string $active_hourly_rate
     * }
     * @return string[]
     * @throws BadMethodCallException
     * @link https://mite.de/api/projekte.html
     */
    public function createProject($name, array $options = array())
    {
        if ($name === '') {
            throw new BadMethodCallException('You must provide a name for the project');
        }

        $options['name'] = $name;
        $params = $this->prepareProjectData($options);


        return $this->callApi('POST', '/projects.json', $params);
    }

    /**
     * Updates the project specified by $id, all null data is not sent to the
     * api. If you want to set a value to null provide the string nil.
     *
     * @param int    $id
     * @param string[] $options {
     *      @type string $name
     *      @type string $note
     *      @type int    $budget
     *      @type string $budget_type (minutes)|cents
     *      @type bool   $archived
     *      @type int    $customer_id
     *      @type int    $hourly_rate
     *      @type array  $hourly_rates_per_services
     *      @type string $active_hourly_rate
     *      @type boolean $update_hourly_rate_on_time_entries
     * }
     *
     * @return bool
     * @throws BadMethodCallException
     * @throws ProjectNotFoundException
     * @throws RuntimeException
     * @throws Exception
     * @link https://mite.de/api/projekte.html
     */
    public function updateProject($id, array $options = array())
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $params = $this->prepareProjectData($options);

            return $this->callApi('PUT', '/projects/'.$id.'.json', $params);

        } catch (Exception $re) {
            if (404 === (int) $re->getCode()) {
                throw new ProjectNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Prepares the given data for further processing.
     *
     * @param string[] $data {
     *      @type string $name
     *      @type string $note
     *      @type int    $budget
     *      @type string $budget_type (minutes)|cents
     *      @type bool   $archived
     *      @type int    $customer_id
     *      @type int    $hourly_rate
     *      @type array  $hourly_rates_per_service
     *      @type string $active_hourly_rate
     *      @type boolean $update_hourly_rate_on_time_entries
     * }
     *
     * @throws BadMethodCallException
     * @return array
     */
    public function prepareProjectData(array $data = array())
    {

        $params = array();
        $supported = array('note', 'budget','budget_type','archived',
                           'customer_id','hourly_rate',
                           'hourly_rates_per_service','active_hourly_rate',
                           'name', 'update_hourly_rate_on_time_entries');

        foreach ($data AS $k => $v) {
            if(!in_array($k, $supported, true)) {
                continue;
            }

            if (is_null($v)) {
                continue;
            }

            if ($k === 'name') {
                if ($v === '') {
                    throw new BadMethodCallException('Name: You must provide a name for the project');
                }
                $params['name'] = $v;
                continue;
            }

            if ($k === 'budget') {
                if (null === ($budget = filter_var($v, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))))) {
                    throw new BadMethodCallException('Budget: expected positive integer >= 0 got: '.$v);
                }
                $params['budget'] = $budget;
                continue;
            }

            if ($k === 'budget_type') {
                if (!in_array($v, array('minutes', 'cent'))) {
                    throw new BadMethodCallException('Budget type: expected one of (minutes|cent) got: '.$v);
                }
                $params['budget_type'] = $v;
                continue;
            }

            if ($k === 'customer_id') {
                if (null === ($customer = filter_var($v, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))))) {
                    throw new BadMethodCallException('Customer: expected positive integer >= 0 got: '.$v);
                }
                $params['customer_id'] = $v;
                continue;
            }

            if ($k === 'hourly_rates_per_service') {
                if (!is_array($v)) {
                    throw new BadMethodCallException('Hourly Rates Per Service: expected array with count >= 0 got: '.$v);
                }

                $params['hourly_rates_per_service'] = $v;
                continue;
            }
            if ($k === 'hourly_rate') {
                if (false === filter_var($v, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
                    throw new BadMethodCallException('Hourly Rate: expected int >= 0 got: '.$v);
                }
                $params['hourly_rate'] = $v;
                continue;
            }

            if ($k === 'active_hourly_rate') {
                if (!in_array($v, array('hourly_rate', 'hourly_rates_per_service'))) {
                    throw new BadMethodCallException('Active hourly rate: expected one of "hourly_rate", "hourly_rates_per_service" got: '.$v);
                }

                $params['active_hourly_rate'] = $v;
                continue;
            }

            if (in_array($k, array('archived', 'update_hourly_rate_on_time_entries'))) {
                if (null === ($vv = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                    throw new BadMethodCallException($k.': expected value to be one of true or false got: '.$v);
                }
                $params[$k] = (false === $vv)? 'false':'true';
                continue;
            }

            $params[$k] = $v;
        }

        return array('project' => $params);
    }

    /**
     * Deletes the given project.
     *
     * @param $id
     *
     * @throws Exceptions\ProjectNotFoundException
     * @throws BadMethodCallException
     * @throws Exception
     * @return bool
     * @link https://mite.de/api/projekte.html
     */
    public function deleteProject($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/projects/'.$id.'.json';
            $method = 'DELETE';
            $params = array();

            return $this->callApi($method, $url, $params);

        } catch (Exception $re) {
            if (404 == $re->getCode()) {
                throw new ProjectNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Returns a list of services optionally filtered by $name. $limit
     * and $page are optional and used for pagination, if omitted all
     * results are returned.
     *
     * @param string $name
     * @param int    $limit
     * @param int    $page page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @throws BadMethodCallException
     * @link https://mite.de/api/leistungen.html
     */
    public function listServices($name = null, $limit = null, $page = null)
    {
        $params = array();
        if (!is_null($name)) {
            $params['name'] = $name;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/services.json', $params);
    }

    /**
     * Returns a list of archived services optionally filtered by $name. $limit
     * and $page are optional and used for pagination, if omitted all
     * results are returned.
     *
     * @param string $name
     * @param int    $limit
     * @param int    $page page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @link https://mite.de/api/leistungen.html
     */
    public function listArchivedServices($name = null, $limit = null, $page = null)
    {
        $params = array();
        if (!is_null($name)) {
            $params['name'] = $name;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/services/archived.json', $params);
    }

    /**
     * Searches for active and archived services. there is no pagination
     * possible, and a search string is mandatory.
     *
     * @param $name
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function searchServices($name)
    {
        if (2 > strlen($name)) {
            throw new BadMethodCallException('The search term ust be at least 2 characters long');
        }

        $active = $this->listServices($name);
        $archived = $this->listArchivedServices($name);

        return array_merge($active, $archived);
    }


    /**
     * Returns the detail of the given service.
     *
     * @param int $id
     *
     * @throws Exceptions\ServiceNotFoundException
     * @throws BadMethodCallException
     * @throws Exception
     * @return array
     * @link https://mite.de/api/leistungen.html
     */
    public function getService($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/services/'.$id.'.json';
            $method = 'GET';
            $params = array();

            $res = $this->callApi($method, $url, $params);

            if (!array_key_exists('service', $res)) {
                throw new ServiceNotFoundException('Cannot find Service: '.$id, 404);
            }

            return $res['service'];

        } catch (Exception $re) {
            if (404 == $re->getCode()) {
                throw new ServiceNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Creates a new service in the backend, all parameter except $name are
     * optional.
     *
     * @param string   $name    name of the service
     * @param string[] $options {
     *      @type string $note  defaults
     *      @type int    $hourlyRate hourly rate in cent
     *      @type bool   $billable true or false
     *      @type bool   $archived true or false
     * }
     *
     * @return array
     * @link https://mite.de/api/leistungen.html
     */
    public function createService($name, array $options = array())
    {
        $options['name'] = strval($name);
        $params = $this->prepareServiceData($options);

        return $this->callApi('POST', '/services.json', $params);
    }

    /**
     * Updates the given service. all null data is not sent to the
     * api. If you want to set a value to null provide the string nil.
     *
     * @param int    $id
     * @param string[] $options {
     *      @type string $name
     *      @type string $note  defaults
     *      @type int    $hourly_rate hourly rate in cent
     *      @type bool   $billable true or false
     *      @type bool   $archived true or false
     *      @type bool   $update_hourly_rate_on_time_entries true or false
     * }
     *
     * @return bool
     * @throws BadMethodCallException
     * @throws ServiceNotFoundException
     * @throws RuntimeException
     * @throws Exception
     * @link https://mite.de/api/leistungen.html
     */
    public function updateService($id, array $options = array())
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/services/'.$id.'.json';
            $method = 'PUT';
            $params = $this->prepareServiceData($options);

            return $res = $this->callApi($method, $url, $params);

        } catch (Exception $re) {
            if (404 == $re->getCode()) {
                throw new ServiceNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Prepares the given data for further processing
     *
     * @param string[] $data {
     *      @type string $name
     *      @type string $note  defaults
     *      @type int    $hourlyRate hourly rate in cent
     *      @type bool   $billable true or false
     *      @type bool   $archived true or false
     *      @type bool   $update_hourly_rate_on_time_entries true or false
     * }
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function prepareServiceData(array $data = array())
    {
        $supported = array('name', 'note', 'hourly_rate', 'billable',
                           'archived','update_hourly_rate_on_time_entries');

        $params = array();

        foreach($data AS $k => $v) {
            if (!in_array($k, $supported) || is_null($v)) {
                continue;
            }

            if (in_array($k, array('archived', 'billable','update_hourly_rate_on_time_entries'))) {
                if (null === ($vv = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                    throw new BadMethodCallException(ucfirst($k).': expected true or false got: '.$v);
                }
                $params[$k] = (false === $vv)? 'false':'true';
                continue;
            }

            if ($k == 'hourly_rate') {
                if (false === filter_var($v, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
                    throw new BadMethodCallException('Hourly Rate: expected int >= 0 got: '.$v);
                }
                $params[$k] = $v;
                continue;
            }

            if ($k == 'name') {
                if (0 == strlen($v)) {
                    throw new BadMethodCallException('Name: You must provide a name for the service');
                }
                $params[$k] = $v;
                continue;
            }

            $params[$k] = $v;
        }

        return array('service' => $params);
    }

    /**
     * Deletes the given service.
     *
     * @param int $id
     *
     * @throws Exceptions\ServiceNotFoundException
     * @throws BadMethodCallException
     * @throws Exception
     * @return bool
     * @link https://mite.de/api/leistungen.html
     */
    public function deleteService($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/services/'.$id.'.json';
            $method = 'DELETE';
            $params = array();

            return $this->callApi($method, $url, $params);

        } catch (Exception $re) {
            if (404 == $re->getCode()) {
                throw new ServiceNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Returns a list of users optionally filtered by $name and or email.
     * $limit and $page are optional and used for pagination, if omitted all
     * results are returned.
     *
     * @param string $name
     * @param string $email
     * @param int    $limit
     * @param int    $page page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @throws BadMethodCallException
     * @link https://mite.de/api/benutzer.html
     */
    public function listUsers(
        $name = null, $email = null, $limit = null, $page = null
    ) {
        $params = array();
        if (!is_null($name) && 0 != strlen($name)) {
            $params['name'] = $name;
        }

        if (!is_null($email) && 0 != strlen($email)) {
            $params['email'] = $email;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/users.json', $params);
    }

    /**
     * Returns a list of archived users optionally filtered by $name or $email.
     * $limit and $page are optional and used for pagination, if omitted all
     * results are returned.
     *
     * @param string $name
     * @param string $email
     * @param int    $limit
     * @param int    $page page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @link https://mite.de/api/benutzer.html
     */
    public function listArchivedUsers($name = null, $email = null, $limit = null, $page = null)
    {
        $params = array();
        if (!is_null($name) && 0 != strlen($name)) {
            $params['name'] = $name;
        }

        if (!is_null($email) && 0 != strlen($email)) {
            $params['email'] = $email;
        }

        $limit = $this->prepareLimit($limit, $page);
        $params = array_merge_recursive($params, $limit);

        return $this->callApi('GET', '/users/archived.json', $params);
    }

    /**
     * Searches for active and archived users. there is no pagination
     * possible, and a search string is mandatory.
     *
     * @param string $name
     * @param string $email
     *
     * @throws BadMethodCallException
     * @return array
     */
    public function searchUsers($name = null, $email = null)
    {

        if (2 > strlen($name) && 2 > strlen($email)) {
            throw new BadMethodCallException('The search term ust be at least 2 characters long');
        }

        $active = $this->listCustomers($name);
        $archived = $this->listArchivedCustomers($name);

        return array_merge($active, $archived);
    }

    /**
     * Returns the user specified by $id
     *
     * @param int $id
     *
     * @throws Exceptions\UserNotFoundException
     * @throws BadMethodCallException
     * @throws Exception
     * @return array
     * @link https://mite.de/api/benutzer.html
     */
    public function getUser($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('ID must be a positive integer got: '.$id);
        }

        try {

            $url    = '/users/'.$id.'.json';
            $method = 'GET';
            $params = array();

            $res = $this->callApi($method, $url, $params);

            if (!array_key_exists('user', $res)) {
                throw new UserNotFoundException('Cannot find User: '.$id, 404);
            }

            return $res['user'];

        } catch (Exception $re) {
            if (404 == $re->getCode()) {
                throw new UserNotFoundException('Cannot find entry: '.$id, 404);
            }

            throw $re;
        }
    }

    /**
     * Gets the account information of the currently authenticated user.
     *
     * @return array
     */
    public function getAccount()
    {
        $url = '/account.json';
        $method = 'GET';

        return $this->callApi($method, $url);
    }

    /**
     * Gets the user record of the currently authenticated user.
     *
     * @return array
     */
    public function getMyself()
    {
        $url = '/myself.json';
        $method = 'GET';

        return $this->callApi($method, $url);
    }


    /**
     * Prepares or checks the given data for further processing, unknown values
     * are skipped if $strict is false.
     *
     * @param array $groupBy
     * @param bool  $strict if true an exception is thrown on every unknown value
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function prepareTimeEntryGrouping(array $groupBy = array(), $strict = false)
    {
        $use = array();
        $supported = array('customer', 'project', 'service', 'user', 'day',
                           'week', 'month', 'year');

        foreach ($groupBy AS $v) {
            if (!in_array($v, $supported)) {
                if ($strict) {
                    throw new BadMethodCallException('GroupBy: '.$v.': is not supported');
                }
                continue;
            }
            $use[] = $v;
        }

        return $use;
    }

    /**
     * Prepares and optionally checks the given data for further processing,
     * unknown keys, values or invalid values are skipped if $strict is false.
     *
     * @param array $filter
     * @param bool  $strict if true an exception is thrown on every error or empty value
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function prepareTimeEntryFilters(array $filter = array(), $strict = false)
    {
        $use = array();
        $supported = array('customer_id', 'project_id',
                           'service_id', 'user_id', 'billable', 'note', 'at', 'from', 'to');

        foreach ($filter AS $k => $v) {
            if(!in_array($k, $supported)) {
                if ($strict) {
                    throw new BadMethodCallException('Filter: '.$k.': is not supported');
                }
                continue;
            }

            if ($k == 'billable') {
                $vv = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if (is_null($vv)) {
                    if ($strict) {
                        throw new BadMethodCallException('Filter: billable: '.$v.' is '
                            . ' not one of true or false or their abbreviations');
                    }
                    continue;
                }
                $v = ($vv)? 'true':'false';
                $use[$k] = $v;
                continue;
            }

            if (is_array($v) && 0 == count($v)) {
                if ($strict) {
                    throw new BadMethodCallException('Filter: '.$k.': no values provided');
                }
                continue;
            }

            if (!is_array($v) && 0 == strlen($v)) {
                if ($strict) {
                    throw new BadMethodCallException('Filter: '.$k.': no values provided');
                }
                continue;
            }

            if (in_array($k,array('customer_id', 'project_id', 'service_id', 'user_id'))) {
                if (is_array($v)) {
                    $vv = array();
                    foreach ($v AS $_v) {
                        if (!filter_var($_v, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))) {
                            continue;
                        }
                        $vv[] = $_v;
                    }

                    if(count($v) != count($vv)) {
                         if ($strict) {
                            throw new BadMethodCallException('Filter: '.$k.': no '
                                . 'valid values provided or some of the value are invalid');
                        }
                    }

                    $v = str_replace(' ','',implode(',',$vv));

                }
                $use[$k] = $v;
                continue;
            }

            if (in_array($k, array('from', 'to'))) {
                $vv = strtotime($v);
                if (false === $vv) {
                    if ($strict) {
                        throw new BadMethodCallException('Filter: '.$k.': '.$v.' is not a valid date');
                    }
                    continue;
                }

                $use[$k] = date('Y-m-d',$vv);
                continue;
            }

            if ($k == 'at') {
                if (in_array($v, array('yesterday', 'today', 'last_week', 'this_month', 'last_month'))) {
                    $use[$k] = $v;
                    continue;
                }

                $vv = strtotime($v);
                if (false === $vv) {
                    if ($strict) {
                        throw new BadMethodCallException('Filter: at: '.$v.' is not a valid date or keyword');
                    }
                    continue;
                }

                $use[$k] = date('Y-m-d',$vv);
                continue;
            }

            if ($k == 'note') {
                $use[$k] = $v;
            }
        }

        return $use;
    }

    /**
     * Prepares the limit parameters for list queries
     *
     * @param int $limit
     * @param int $page
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function prepareLimit($limit = null, $page = null)
    {
        $param = array();
        if (!is_null($page) && is_null($limit)) {
            throw new BadMethodCallException('Page is only working with limit');
        }

        if (!is_null($limit) && !filter_var($limit, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('limit must be greater than 0');
        }

        if (!is_null($limit)) {
            $param['limit'] = $limit;
        }

        if (!is_null($page) && !filter_var($page, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)))) {
            throw new BadMethodCallException('page must be greater than 0');
        }

        if (!is_null($page)) {
            $param['page'] = $page;
        }

        return $param;
    }

    /**
     * Prepares the $request object and sends it to call()
     *
     * @param string $method
     * @param string $url
     * @param array  $parameters
     * @param array  $options
     *
     * @return array|string
     * @throws BadMethodCallException
     */
    public function callApi($method, $url, array $parameters = array(), array $options = array())
    {
        if (0 == strlen($url)) {
            throw new BadMethodCallException('No Url provided');
        }

        $headers = (array_key_exists('headers', $options) && is_array($options['headers']))? $options['headers']:array();

        $request = $this->getClient()->createRequest($method, $headers);
        $request->setUri(sprintf('%s%s',$this->getClient()->getUrl(), $url));

        if (!in_array($method, array('GET','DELETE'))) {
            $request->setContent(json_encode($parameters));
        } else {
            $request->getQuery()->fromArray($parameters);
        }

        return $this->getClient()->call($request, $this->getClient()->getClient(), $options);
    }



}
