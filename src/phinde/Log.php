<?php
namespace phinde;

class Log
{
    public static function error($msg)
    {
        static::log($msg);
    }

    public static function info($msg)
    {
        if ($GLOBALS['phinde']['debug']) {
            static::log($msg);
        }
    }

    public static function log($msg)
    {
        echo $msg . "\n";
    }
}
?>
