<?php
namespace Phpr;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Countable;

use Phpr;
use Phpr\Flash;
use Db\Helper as DbHelper;

/**
 * PHPR Session Class
 *
 * This class incapsulates the PHP session.
 *
 * The instance of this class is available in the Phpr global object: Phpr::$session.
 */
class Session implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * Flash object
     *
     * @var Phpr\Flash
     */
    public $flash = null;
    protected $closeSessionLocks = null;

    /**
     * Begins a session.
     * You must always start the session before use any session data.
     * You may achieve the "auto start" effect by adding the following line to the application init.php script:
     * Phpr::$session->start();
     *
     * @return boolean
     */
    public function start()
    {
        $path = ini_get('session.cookie_path');
        if (!strlen($path)) {
            $path = '/';
        }

        $secure = false;

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
            $secure = true;
        } else {
            $secure = (empty($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"] === 'off')) ? false : true;
        }

        session_set_cookie_params(ini_get('session.cookie_lifetime'), $path, ini_get('session.cookie_domain'), $secure);

        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $this->closeSessionLocks = true;
        }

        if ($this->closeSessionLocks) {
            $result = $this->sessionRead();
        } else {
            $result = $this->sessionOpen();
        }
        if ($result) {
            $this->flash = new Flash();
            if ($this->flash) {
                if (array_key_exists('flash_partial', $_POST) && strlen($_POST['flash_partial'])) {
                    $this->flash['system'] = 'flash_partial:' . $_POST['flash_partial'];
                }
            }
        }

        return $result;
    }

    public function restoreDbData()
    {
        $session_id_param = Phpr::$config->get('SESSION_PARAM_NAME', 'ls_session_id');
        $session_id = Phpr::$request->getField($session_id_param);
        // if ($session_id)
        //     session_id($session_id);

        if ($session_id) {
            $this->restore($session_id);
        }
    }

    /**
     * Destroys all data registered to a session
     */
    public function destroy()
    {
        $this->sessionOpen();
        $_SESSION = array();
        session_destroy();
    }

    /**
     * Determines whether the session contains a value
     *
     * @param  string $Name Specifies a value name
     * @return boolean
     */
    public function has($Name)
    {
        return isset($_SESSION[$Name]);
    }

    /**
     * Returns a value from the session.
     *
     * @param  string $Name    Specifies a value name
     * @param  mixed  $Default Specifies a default value
     * @return mixed
     */
    public function get($Name, $Default = null)
    {
        if ($this->has($Name)) {
            return $_SESSION[$Name];
        }

        return $Default;
    }

    /**
     * Writes a value to the session.
     * If close session locks is supported, a session lock will be opened and closed to set the session variable.
     * The opening and closing of a session lock will not occur if a session has been left open outside of this class
     *
     * @param string $Name  Specifies a value name
     * @param mixed  $Value Specifies a value to write.
     */
    public function set($Name, $Value = null)
    {
        $opened_session_lock = null;
        if ($this->closeSessionLocks) {
            $opened_session_lock = $this->sessionOpen();
        }

        if ($Value === null) {
            unset($_SESSION[$Name]);
        } else {
            $_SESSION[$Name] = $Value;
        }

        if ($this->closeSessionLocks && $opened_session_lock) {
            session_write_close();
        }
    }

    /**
     * Removes a value from the session.
     *
     * @param string $Name Specifies a value name
     */
    public function remove($Name)
    {
        $this->set($Name, null);
    }

    /**
     * Destroys the session object.
     */
    public function __destruct()
    {
    }

    public function reset()
    {
        foreach ($_SESSION as $name => $value) {
            $this->set($name, null);
        }

        $this->resetDbSessions();
    }

    /*
     * Sessions in the database
     */

    public function resetDbSessions()
    {
        $ttl = (int)Phpr::$config->get('STORED_SESSION_TTL', 3);
        DbHelper::query(
            'delete from db_session_data where created_at < DATE_SUB(now(), INTERVAL :seconds SECOND)',
            array('seconds' => $ttl)
        );
    }

    public function store()
    {
        $session_id = session_id();

        DbHelper::query(
            'delete from db_session_data where session_id=:session_id',
            array('session_id' => $session_id)
        );

        $data = serialize($_SESSION);
        DbHelper::query(
            'insert into db_session_data(session_id, session_data, created_at, client_ip) values (:session_id, :session_data, NOW(), :client_ip)',
            array(
                'session_id' => $session_id,
                'session_data' => $data,
                'client_ip' => Phpr::$request->getUserIp()
            )
        );
    }

    public function restore($session_id)
    {
        $data = DbHelper::scalar(
            'select session_data from db_session_data where session_id=:session_id and client_ip=:client_ip',
            array(
                'session_id' => $session_id,
                'client_ip' => Phpr::$request->getUserIp()
            )
        );

        DbHelper::query(
            'delete from db_session_data where session_id=:session_id',
            array('session_id' => $session_id)
        );

        if (strlen($data)) {
            try {
                $data = unserialize($data);
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $this->set($key, $value);
                    }
                }
            } catch (\Exception $ex) {
            }
        }
    }

    /**
     * Iterator implementation
     */

    public function offsetExists($offset)
    {
        return isset($_SESSION[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset, null);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->set($offset, null);
    }

    public function getIterator()
    {
        return new ArrayIterator($_SESSION);
    }

    /**
     * Returns the number of session keys set
     *
     * @return integer
     */
    public function count()
    {
        return count($_SESSION);
    }

    /**
     * Opens a session if not already open
     *
     * @return boolean true if session open, false if could not be started, null if it was already started
     */
    protected function sessionOpen()
    {
        $session_opened = null;
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            if ((session_status() === PHP_SESSION_NONE) && !headers_sent()) {
                $session_opened = session_start();
            }
        } else {
            if (!session_id()) {
                $session_opened = session_start();
            }
        }
        return $session_opened;
    }

    /**
     * Opens a session to populate $_SESSION variables and closes the session lock
     *
     * @return boolean true if read successful, false if could not be read
     */
    protected function sessionRead()
    {
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $session_read = session_start(array('read_and_close' => true));
        } else {
            $session_read = $this->sessionOpen();
            session_write_close();
        }
        return $session_read;
    }

}
