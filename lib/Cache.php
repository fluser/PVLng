<?php
/**
 * Abstract class Cache
 *
 * @author     Knut Kohl <github@knutkohl.de>
 * @copyright  2010-2013 Knut Kohl
 * @license    GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version    1.2.0
 *
 * @changelog
 * - v1.2.0
 *     - Move validation check against timestamps into here
 * - v1.1.0
 *     - Add test to find supported caches
 *
 */
abstract class Cache {

    // -------------------------------------------------------------------------
    // ABSTRACT
    // -------------------------------------------------------------------------

    /**
     * Cache availability
     *
     * Returns TRUE by default, reimplement if required
     *
     * @return bool
     */
    abstract public function isAvailable();

    /**
     * Write raw data in cache
     *
     * @param string $key Unique cache Id
     * @param string $data
     * @return bool
     */
    abstract public function write( $key, $data );

    /**
     * Retrieve raw data from cache
     *
     * @param string $key Unique cache Id
     * @return string
     */
    abstract public function fetch( $key );

    /**
     * Delete data from cache
     *
     * @param string $key Unique cache Id
     * @return bool
     */
    abstract public function delete( $key );

    /**
     * Clear cache
     *
     * @return bool
     */
    abstract public function flush();

    // -------------------------------------------------------------------------
    // PUBLIC
    // -------------------------------------------------------------------------

    /**
     * Create/find a cache instance
     *
     * @param string $class Force cache class to create
     * @param array $settings
     * @return Cache
     */
    public static final function factory( $settings=array(), $classes=NULL ) {
        $caches = ($classes == '') ? self::$Caches : explode(',', $classes);
        foreach ($caches as $class) {
            $class = 'Cache\\'.$class;

            if (!class_exists($class))
                throw new CacheException('Missing class: '.$class);

            $cache = new $class($settings);
            if ($cache->isAvailable()) {
                return $cache;
            }
        }

        return new Cache\Mock;
    } // function factory()

    /**
     * Class constructor
     *
     * @param array $settings
     * @return void
     */
    public function __construct( $settings=array() ) {
        $this->ts = time();
        $this->stack = array();
        $this->settings = array_merge($this->settings, $settings);

        if ($this->settings['Token'] == '') {
            $this->settings['Token'] = substr(md5(__FILE__), -7);
        }

    } // function __construct()

    /**
     * Some infos about the cache
     *
     * @return array
     */
    public function info() {
        return array('class' => get_class($this));
    }

    /**
     * Get data from cache, if not yet exists, save to cache
     *
     * Nested calls of save() will be handled correctly.
     *
     * @par Scenarios:
     * - Data not cached yet @b or not more valid
     *     - On 1st call: Return TRUE and go 1 times through the loop to build
     *         the data
     *     - On 2nd call: Store the data to the cache and return FALSE
     * - Data cached @b and valid
     *     - On 1st call: Retrieve the data from cache and return FALSE
     *
     * @usage
     * @code
     * $cache = Cache::create('...');
     * while ($cache->save($key, $data[, $ttl])) {
     *     ...
     *     $data = ...;
     * }
     * echo $data;
     * @endcode
     *
     * @throws CacheException
     * @param string $key Unique cache Id
     * @param mixed &$data Data to store / retrieve
     * @param int $ttl Time to live, if set to 0, expire never
     * @return bool
     */
    public final function save( $key, &$data, $ttl=0 ) {
        if ($key == end($this->stack)) {
            $this->set($key, $data, $ttl);
            // done, remove id from stack
            array_pop($this->stack);
            return FALSE;
        } elseif (in_array($key, $this->stack)) {
            // $key is in stack, but NOT on top
            throw new CacheException(__CLASS__.': Stack problem - '.end($this->stack).' not properly finished!', 99);
        } else {
            $data = $this->get($key, $ttl);
            if ($data !== NULL) {
                // Content found in cache, done
                return FALSE;
            } else {
                // not found yet, let's go
                $this->stack[] = $key;
                return TRUE;
            }
        }
    } // function save()

    /**
     * Increments value of an item by the specified value.
     *
     * If item specified by key was not numeric and cannot be converted to a
     * number, it will change its value to value.
     *
     * inc() does not create an item if it doesn't already exist.
     *
     * @param string $key Unique cache Id
     * @param numeric $step
     * @return numeric|bool New items value on success or FALSE on failure.
     */
    public function inc( $key, $step=1 ) {
        return $this->modify($key, $step);
    } // function inc()

    /**
     * Decrements value of the item by value.
     *
     * If item specified by key was not numeric and cannot be converted to a
     * number, it will change its value to value.
     *
     * dec() does not create an item if it doesn't already exist.
     *
     * Similarly to inc(), current value of the item is being converted to
     * numerical and after that value is substracted.
     *
     * @param string $key Unique cache Id
     * @param numeric $step
     * @return numeric|bool New items value on success or FALSE on failure.
     */
    public function dec( $key, $step=1 ) {
        return $this->modify($key, -$step);
    } // function dec()

    /**
     * Magic method to set cache data
     *
     * Use implicit $ttl == NULL
     *
     * @usage
     * @code
     * $cache = Cache::create('...');
     * // Set data
     * $cache->Key = '...';
     * // Retrieve data
     * $data = $cache->Key;
     * @endcode
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public final function __set( $key, $value ) {
        $this->set($key, $value);
    }

    /**
     * Write raw data in cache
     *
     * @param string $key Unique cache Id
     * @param mixed $data
     * @param int $ttl Time to live or timestamp
     *                                 - = 0 - expire never
     *                                 - > 0 - Time to live
     *                                 - < 0 - Timestamp of expiration
     * @return bool
     */
    public function set( $key, $data, $ttl=0 ) {
        if (!is_array($key)) {
            return $this->write($key, $this->serialize(array($this->ts, $ttl, $data)));
        }

        // Multiple values
        $ok = TRUE;
        foreach ($key as $k=>$v) {
            $ok = ($ok AND $this->write($key, $this->serialize(array($this->ts, $ttl, $v))));
        }
        return $ok;
    }

    /**
     * Magic method to get cached data
     *
     * Use implicit $expire == NULL
     *
     * @usage
     * @code
     * $cache = Cache::create('...');
     * // Set data
     * $cache->Key = '...';
     * // Retrieve data
     * $data = $cache->Key;
     * @endcode
     *
     * @param string $key
     * @return mixed
     */
    public final function __get( $key ) {
        return $this->get($key);
    }

    /**
     * Retrieve raw data from cache
     *
     * @param string $key Unique cache Id
     * @param int $expire Time to live or timestamp
     *                                        - = 0 - expire never
     *                                        - > 0 - Time to live
     *                                        - < 0 - Timestamp of expiration
     * @return mixed
     */
    public function get( $key, $expire=0 ) {
        $data = $this->fetch($key);

        if ($data === NULL) return;

        // Split into store time, ttl, data
        list($ts, $ttl, $data) = $this->unserialize($data);

        // Data valid?
        if ($this->valid($ts, $ttl, $expire)) return $data;

        // else drop data for this key
        $this->delete($key);
    }

    /**
     * Magic method to check existence and validity of cached data
     *
     * @usage
     * @code
     * $cache = Cache::create('...');
     * if (!isset($cache->Key)) {
     *     $cache->Key = '...';
     * }
     * $data = $cache->Key;
     * @endcode
     *
     * @param string $key
     * @return mixed
     */
    public function __isset( $key ) {
        return ($this->get($key) !== NULL);
    }

    /**
     * Magic method to unset cached data
     *
     * @param string $key
     * @return mixed
     */
    public final function __unset( $key ) {
        return $this->delete($key);
    }

    // -------------------------------------------------------------------------
    // PROTECTED
    // -------------------------------------------------------------------------

    /**
     * Master timestamp
     *
     * @var int $ts
     */
    protected $ts;

    /**
     * Settings
     *
     * @var array $settings
     */
    protected $settings = array(
        'Token'       => '',
        'Directory'   => '',
    );

    /**
     * Available caching methods
     *
     * @todo Test 'EAccelerator', 'XCache', 'MemCache'
     * @var array $Caches
     */
    protected static $Caches = array(
        'APC', 'MemCache',
        // Only avail. with a writeable directory
        'File', 'Files',
        // Always avail.
        'Mock'
    );

    /**
     * Check data validity according to the timestamps
     *
     * @see set()
     * @see get()
     * @param int $ts Timestamp when data was last saved
     * @param int $ttl Time to live of data to check against
     * @param int $expire Time to live or timestamp
     *                                        - = 0 - expire never
     *                                        - > 0 - Time to live
     *                                        - < 0 - Timestamp of expiration
     * @return bool
     */
    protected function valid( $ts, $ttl, $expire ) {
        if (isset($expire)) {
            // expiration timestamp set
            if ($expire === 0 OR
                    $expire > 0 AND $this->ts+$expire >= $ts+$ttl OR
                    $expire < 0 AND $ts >= -$expire) return TRUE;
        } else {
            // expiration timestamp NOT set
            if ($ttl === 0 OR
                    $ttl > 0 AND $ts+$ttl >= $this->ts OR
                    $ttl < 0 AND -$ttl >= $this->ts) return TRUE;
        }
        return FALSE;
    } // function valid()

    /**
     * Build internal Id from external Id and the cache token
     *
     * @param string $key Unique cache Id
     * @return string
     */
    protected function key( $key ) {
        return $this->settings['Token'].'.'.substr(md5(strtolower($key)), -8);
    } // function key()

    /**
     * Serialize data
     *
     * @param mixed $data
     * @return string
     */
    protected function serialize( $data ) {
        return serialize($data);
    } // function serialize()

    /**
     * Unserialize data
     *
     * @param string $data
     * @return mixed
     */
    protected function unserialize( $data ) {
        return unserialize($data);
    } // function unserialize()

    // -------------------------------------------------------------------------
    // PROTECTED
    // -------------------------------------------------------------------------

    /**
     * Stack of save() calls
     *
     * @var array $stack
     */
    protected $stack;

    /**
     * Increments / decrements value of the item by value.
     *
     * @param string $key Unique cache Id
     * @param int $step
     * @return num New items value on success or FALSE on failure.
     */
    protected function modify( $key, $step ) {
        $data = $this->get($key);
        // Change only numerics
        if (is_numeric($data)) {
            $data += $step;
            if ($this->set($key, $data) === TRUE) {
                // Sucessful saved
                return $data;
            }
        }
        return FALSE;
    }

}

/**
 * Class CacheException
 *
 * @ingroup Cache
 */
class CacheException extends Exception {}
