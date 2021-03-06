<?php
/**
 *
 *
 * @author      Knut Kohl <github@knutkohl.de>
 * @copyright   2012-2013 Knut Kohl
 * @license     GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version     1.0.0
 */
class Loader {

    /**
     *
     */
    public static function autoload( $className ) {
        // Handle namespaced and PEAR named classes the same way
        $className = str_replace(array('\\','_'), DIRECTORY_SEPARATOR, $className);
        $classMap  = self::getClassMap();

        if (isset($classMap[$className])) {
            require_once $classMap[$className];
            return TRUE;
        }
    }

    /**
     *
     */
    public static function register( $settings=array(), $cache=TRUE ) {
        self::$settings = array_merge(self::$settings, array_change_key_case($settings));

        if ($cache === TRUE) $cache = sys_get_temp_dir();

        self::$ClassMapFile = $cache
                            ? sprintf('%s%sclassmap.%s.php', $cache, DS,
                              substr(md5(serialize(self::$settings)),-7))
                            : FALSE;

        spl_autoload_register('Loader::autoload');
    }

    // -------------------------------------------------------------------------
    // PROTECTED
    // -------------------------------------------------------------------------

    /**
     *
     */
    protected static $ClassMap = array();

    /**
     *
     */
    protected static $ClassMapFile;

    /**
     *
     */
    protected static $settings = array(
        'path'    => array(),
        'pattern' => array(
            '%s.php',
            '%s.class.php',
            '%s.interface.php',
            'class.%s.php',
            '%s.inc'
        ),
        'exclude' => array(),
    );

    /**
     *
     */
    protected static function getClassMap() {
        if (empty(self::$ClassMap)) {
            if (self::$ClassMapFile AND file_exists(self::$ClassMapFile)) {
                self::$ClassMap = include self::$ClassMapFile;
            } else {
                // Build class map
                foreach (self::$settings['path'] as $path) {
                    // Iterator for the paths
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($path)
                    );
                    // Load the list of files in the path
                    foreach ($files as $name=>$file ) {
                        foreach (self::$settings['exclude'] as $pattern) {
                            // Skip 2 foreach!
                            if (preg_match('~'.preg_quote($pattern, '~').'~', $name)) continue 2;
                        }
                        if (!$file->isDir() AND !preg_match('~'.DS.'\.\w+~', $name)) {
                            $filename = str_replace($path.DS, '', $file->getPathname());
                            foreach (self::$settings['pattern'] as $pattern) {
                                $pattern = str_replace('%s', '([\w/]+)', $pattern);
                                $pattern = str_replace('%s', '(\w+)', $pattern);
                                if (preg_match('~'.$pattern.'~', $filename, $args)) {
                                    self::$ClassMap[$args[1]] = $name;
                                    break;
                                }
                            }
                        }
                    }
                }
                // Cache class map if allowed
                if (self::$ClassMapFile) {
                    ksort(self::$ClassMap);
                    file_put_contents(self::$ClassMapFile,
                                      '<?php return ' . var_export(self::$ClassMap, TRUE) . ';');
                }
            }
        }

        return self::$ClassMap;
    }

}
