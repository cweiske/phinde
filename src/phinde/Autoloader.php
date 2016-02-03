<?php
namespace phinde;

class Autoloader
{
    public static function autoload($class)
    {
        $file = str_replace(array('\\', '_'), '/', $class)
            . '.php';
        if (stream_resolve_include_path($file)) {
            require_once $file;
        }
    }

    public static function register()
    {
        set_include_path(__DIR__ . '/../' . PATH_SEPARATOR . get_include_path());
        spl_autoload_register('phinde\\Autoloader::autoload');
    }
}
?>
