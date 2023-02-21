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

use SysEleven\MiteEleven\Exceptions\CustomerNotFoundException;
use SysEleven\MiteEleven\Exceptions\EntryNotFoundException;
use SysEleven\MiteEleven\Exceptions\ProjectNotFoundException;
use SysEleven\MiteEleven\Exceptions\ServiceNotFoundException;
use SysEleven\MiteEleven\Exceptions\UserNotFoundException;

/**
 * Defines a core set of methods for accessing the mite services
 *
 * @author M. Seifert <m.seifert@syseleven.de
 * @version 0.9.1
 * @package SysEleven\MiteEleven
 */
interface MiteInterface
{

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
     *
     * @return array
     * @link https://mite.de/api/gruppierte-zeiten.html
     * @link https://mite.de/api/zeiten.html
     */
    public function listEntries(
        array $filter = array(),
        array $group = array(),
        $limit = null,
        $page = null);


    /**
     * Returns the entry specified by $id
     *
     * @param int $id
     *
     * @return array
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\EntryNotFoundException
     * @link https://mite.de/api/zeiten.html
     */
    public function getEntry($id);

    /**
     * Creates a new entry, all parameters are optional.
     *
     * @param string[] $data {
     *      @type \DateTime $dateAt    Optional defaults to now
     *      @type int       $minutes   Optional defaults to 0
     *      @type string    $note      Optional defaults to ''
     *      @type int       $userID    Can only be set vy the owner or an Administrator
     *      @type int       $projectID Optional defaults to nil
     *      @type int       $serviceID Optional defaults to nil
     *      @type bool      $locked    Optional defaults to false
     * }
     *
     * @return array
     * @throws \SysEleven\MiteEleven\Exceptions\MiteRuntimeException
     * @link https://mite.de/api/zeiten.html
     */
    public function createEntry(array $data = array());

    /**
     * Updates the entry specified by id.
     *
     * @param int  $id
     * @param string[] $data {
     *      @type \DateTime $date_at    Optional defaults to now
     *      @type int       $minutes    Optional defaults to 0
     *      @type string    $note       Optional defaults to ''
     *      @type int       $user_id    Can only be set vy the owner or an Administrator
     *      @type int       $project_id Optional defaults to nil
     *      @type int       $service_id Optional defaults to nil
     *      @type bool      $locked     Optional defaults to false
     * }
     * @param bool $force Forces edit of a locked entry
     *
     * @return array
     * @throws \BadMethodCallException
     * @throws \RuntimeException
     * @throws \SysEleven\MiteEleven\Exceptions\EntryNotFoundException
     * @link https://mite.de/api/zeiten.html
     */
    public function updateEntry($id, array $data = array(), $force = false);

    /**
     * Deletes the time entry.
     *
     * @param int  $id
     *
     * @return boolean
     * @throws \BadMethodCallException
     * @throws \RuntimeException
     * @throws \SysEleven\MiteEleven\Exceptions\EntryNotFoundException
     * @link https://mite.de/api/zeiten.html
     */
    public function deleteEntry($id);

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
    public function listCustomers($name = null, $limit = null, $page = null);

    /**
     * Returns a list of archived customers filtered by $name, $limit and $page are
     * optional and used for pagination, if omitted all results are returned.
     *
     * @param string $name  Name Filter
     * @param int    $limit limit the result to $limit entries
     * @param int    $page  page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @link https://mite.de/api/kunden.html
     */
    public function listArchivedCustomers($name = null, $limit = 0, $page = null);

    /**
     * Searches for active and archived customers. there is no pagination
     * possible, and a search string is mandatory.
     *
     * @param $name
     *
     * @return array
     * @throws \BadMethodCallException
     */
    public function searchCustomers($name);

    /**
     * Retrieves a single customer $record. When no record is found a
     * CustomerNotFoundException is thrown
     *
     * @param $id
     *
     * @return array
     * @throws \SysEleven\MiteEleven\Exceptions\CustomerNotFoundException
     * @link https://mite.de/api/kunden.html
     */
    public function getCustomer($id);

    /**
     * Creates a new customer entry in the backend, all parameters except
     * $name are optional
     *
     * @param        $name
     * @param string[] $options {
     *      @type string $note
     *      @type bool   $archived
     *      @type int    $hourly_rate              in Cent
     *      @type array  $hourly_rates_per_service array of service => rates
     *      @type string $active_hourly_tate       one of nil|hourly_rate|hourly_rate_per_service
     * }
     *
     * @return array
     * @throws \BadMethodCallException
     * @throws \RuntimeException
     * @link https://mite.de/api/kunden.html
     */
    public function createCustomer($name, array $options = array());

    /**
     * Updates the customer record specified by $id with the given data,
     * all null data is not sent to the backend. If you want to set a value to
     * null provide the string 'null'.
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
     * @return array
     * @throws \RuntimeException
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\CustomerNotFoundException
     * @link https://mite.de/api/kunden.html
     */
    public function updateCustomer($id, array $options = array());

    /**
     * Deletes the given customer. If you try to delete a project which has
     * projects left you will receive an error.
     *
     * @param int $id
     *
     * @return bool
     * @throws \RuntimeException
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\CustomerNotFoundException
     * @link https://mite.de/api/kunden.html
     */
    public function deleteCustomer($id);

    /**
     * Returns a list of projects optionally filtered by $name. $limit and
     * $page are optional and used for pagination, if omitted all
     * results are returned.
     *
     * @param string $name
     * @param int    $limit
     * @param int    $page page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @throws \BadMethodCallException
     * @link https://mite.de/api/projekte.html
     */
    public function listProjects($name = null, $limit = null, $page = null);

    /**
     * Returns a list of archived projects optionally filtered by $name. $limit and
     * $page are optional and used for pagination, if omitted all
     * results are returned.
     *
     * @param string $name
     * @param int    $limit
     * @param int    $page page to access, if not used in conjunction with limit a \BadMethodCallException is thrown
     *
     * @return array
     * @link https://mite.de/api/projekte.html
     */
    public function listArchivedProjects($name = null, $limit = null, $page = null);

    /**
     * Searches for active and archived projects. there is no pagination
     * possible, and a search string is mandatory.
     *
     * @param $name
     *
     * @return array
     * @throws \BadMethodCallException
     */
    public function searchProjects($name);

    /**
     * Returns the project specified by $id.
     *
     * @param int $id
     *
     * @return array
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\ProjectNotFoundException
     * @link https://mite.de/api/projekte.html
     */
    public function getProject($id);

    /**
     * Creates a new project, all parameter except $name are optional.
     *
     * @param string $name
     * @param string[] $options {
     *      @type string $note
     *      @type int    $budget
     *      @type string $budgetType (minutes)|cents
     *      @type bool   $archived
     *      @type int    $customerID
     *      @type int    $hourlyRate
     *      @type array  $hourlyRatesPerServices
     *      @type string $activeHourlyRate
     * }
     * @return string[]
     *
     * @return mixed
     * @throws \BadMethodCallException
     * @link https://mite.de/api/projekte.html
     */
    public function createProject($name, array $options = array());

    /**
     * Updates the project specified by $id, all null data is not sent to the
     * api. If you want to set a value to null provide the string nil.
     *
     * @param int    $id
     * @param string[] $options {
     *      @type string $name
     *      @type string $note
     *      @type int    $budget
     *      @type string $budgetType (minutes)|cents
     *      @type bool   $archived
     *      @type int    $customerID
     *      @type int    $hourlyRate
     *      @type array  $hourlyRatesPerServices
     *      @type string $activeHourlyRate
     * }
     *
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\ProjectNotFoundException
     * @throws \RuntimeException
     * @link https://mite.de/api/projekte.html
     */
    public function updateProject($id, array $options = array());

    /**
     * Deletes the given project.
     *
     * @param $id
     *
     * @return bool
     * @throws \SysEleven\MiteEleven\Exceptions\ProjectNotFoundException
     * @throws \BadMethodCallException
     * @link https://mite.de/api/projekte.html
     */
    public function deleteProject($id);

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
     * @throws \BadMethodCallException
     * @link https://mite.de/api/leistungen.html
     */
    public function listServices($name = null, $limit = null, $page = null);

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
    public function listArchivedServices($name = null, $limit = null, $page = null);

    /**
     * Returns the detail of the given service.
     *
     * @param int $id
     *
     * @return array
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\ServiceNotFoundException
     * @link https://mite.de/api/leistungen.html
     */
    public function getService($id);

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
    public function createService($name, array $options = array());

    /**
     * Updates the given service. all null data is not sent to the
     * api. If you want to set a value to null provide the string nil.
     *
     * @param int    $id
     * @param string[] $options {
     *      @type string $name
     *      @type string $note  defaults
     *      @type int    $hourlyRate hourly rate in cent
     *      @type bool   $billable true or false
     *      @type bool   $archived true or false
     * }
     *
     * @return array
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\ServiceNotFoundException
     * @throws \RuntimeException
     * @link https://mite.de/api/leistungen.html
     */
    public function updateService($id, array $options = array());

    /**
     * Deletes the given service.
     *
     * @param int $id
     *
     * @return bool
     * @throws \BadMethodCallException
     * @throws \SysEleven\MiteEleven\Exceptions\ServiceNotFoundException
     * @throws \RuntimeException
     * @link https://mite.de/api/leistungen.html
     */
    public function deleteService($id);

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
     * @throws \BadMethodCallException
     * @link https://mite.de/api/benutzer.html
     */
    public function listUsers($name = null, $email = null, $limit = null, $page = null);

    /**
     * Returns a list of archived users optionally filtered by $name or $email.
     *
     * @param string $name
     * @param string $email
     *
     * @return array
     * @link https://mite.de/api/benutzer.html
     */
    public function listArchivedUsers($name = '', $email = '');

    /**
     * Returns the user specified by $id
     *
     * @param int $id
     *
     * @return array
     * @throws \SysEleven\MiteEleven\Exceptions\UserNotFoundException
     * @link https://mite.de/api/benutzer.html
     */
    public function getUser($id);

    /**
     * Gets the account information of the currently authenticated user.
     *
     * @return array
     */
    public function getAccount();

    /**
     * Gets the user record of the currently authenticated user.
     *
     * @return array
     */
    public function getMyself();

}
