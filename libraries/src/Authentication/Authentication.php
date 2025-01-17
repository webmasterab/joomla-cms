<?php

/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2005 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Authentication;

use Joomla\CMS\Event\User\AuthenticationEvent;
use Joomla\CMS\Event\User\AuthorisationEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Dispatcher;
use Joomla\Event\DispatcherAwareTrait;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Authentication class, provides an interface for the Joomla authentication system
 *
 * @since  1.7.0
 */
class Authentication
{
    use DispatcherAwareTrait;

    /**
     * This is the status code returned when the authentication is success (permit login)
     *
     * @var    integer
     * @since  1.7.0
     */
    public const STATUS_SUCCESS = 1;

    /**
     * Status to indicate cancellation of authentication (unused)
     *
     * @var    integer
     * @since  1.7.0
     */
    public const STATUS_CANCEL = 2;

    /**
     * This is the status code returned when the authentication failed (prevent login if no success)
     *
     * @var    integer
     * @since  1.7.0
     */
    public const STATUS_FAILURE = 4;

    /**
     * This is the status code returned when the account has expired (prevent login)
     *
     * @var    integer
     * @since  1.7.0
     */
    public const STATUS_EXPIRED = 8;

    /**
     * This is the status code returned when the account has been denied (prevent login)
     *
     * @var    integer
     * @since  1.7.0
     */
    public const STATUS_DENIED = 16;

    /**
     * This is the status code returned when the account doesn't exist (not an error)
     *
     * @var    integer
     * @since  1.7.0
     */
    public const STATUS_UNKNOWN = 32;

    /**
     * @var    Authentication[]  JAuthentication instances container.
     * @since  1.7.3
     */
    protected static $instance = [];

    /**
     * Plugin Type to run
     *
     * @var   string
     * @since  4.0.0
     */
    protected $pluginType;

    /**
     * Constructor
     *
     * @param   string                $pluginType  The plugin type to run authorisation and authentication on
     * @param   ?DispatcherInterface  $dispatcher  The event dispatcher we're going to use
     *
     * @since   1.7.0
     */
    public function __construct(string $pluginType = 'authentication', ?DispatcherInterface $dispatcher = null)
    {
        // Set the dispatcher
        if (!\is_object($dispatcher)) {
            $dispatcher = Factory::getContainer()->get('dispatcher');
        }

        $this->setDispatcher($dispatcher);
        $this->pluginType = $pluginType;

        $isLoaded = PluginHelper::importPlugin($this->pluginType);

        if (!$isLoaded) {
            Log::add(Text::_('JLIB_USER_ERROR_AUTHENTICATION_LIBRARIES'), Log::WARNING, 'jerror');
        }
    }

    /**
     * Returns the global authentication object, only creating it
     * if it doesn't already exist.
     *
     * @param   string  $pluginType  The plugin type to run authorisation and authentication on
     *
     * @return  Authentication  The global Authentication object
     *
     * @since   1.7.0
     */
    public static function getInstance(string $pluginType = 'authentication')
    {
        if (empty(self::$instance[$pluginType])) {
            self::$instance[$pluginType] = new static($pluginType);
        }

        return self::$instance[$pluginType];
    }

    /**
     * Finds out if a set of login credentials are valid by asking all observing
     * objects to run their respective authentication routines.
     *
     * @param   array  $credentials  Array holding the user credentials.
     * @param   array  $options      Array holding user options.
     *
     * @return  AuthenticationResponse  Response object with status variable filled in for last plugin or first successful plugin.
     *
     * @see     AuthenticationResponse
     * @since   1.7.0
     */
    public function authenticate($credentials, $options = [])
    {
        // Create authentication response
        $response = new AuthenticationResponse();

        // Dispatch onUserAuthenticate event in the isolated dispatcher
        $dispatcher = new Dispatcher();
        PluginHelper::importPlugin($this->pluginType, null, true, $dispatcher);

        $dispatcher->dispatch('onUserAuthenticate', new AuthenticationEvent('onUserAuthenticate', [
            'credentials' => $credentials,
            'options'     => $options,
            'subject'     => $response,
        ]));

        if (empty($response->username)) {
            $response->username = $credentials['username'];
        }

        if (empty($response->fullname)) {
            $response->fullname = $credentials['username'];
        }

        if (empty($response->password) && isset($credentials['password'])) {
            $response->password = $credentials['password'];
        }

        return $response;
    }

    /**
     * Authorises that a particular user should be able to login
     *
     * @param   AuthenticationResponse  $response  response including username of the user to authorise
     * @param   array                   $options   list of options
     *
     * @return  AuthenticationResponse[]  Array of authentication response objects
     *
     * @since  1.7.0
     * @throws \Exception
     */
    public function authorise($response, $options = [])
    {
        $dispatcher = $this->getDispatcher();

        // Get plugins in case they haven't been imported already
        PluginHelper::importPlugin('user', null, true, $dispatcher);

        $event = new AuthorisationEvent('onUserAuthorisation', ['subject' => $response, 'options' => $options]);
        $dispatcher->dispatch('onUserAuthorisation', $event);

        return $event['result'] ?? [];
    }
}
