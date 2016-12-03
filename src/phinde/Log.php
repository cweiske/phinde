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
        if (isset($GLOBALS['phinde']['logfile'])
            && $GLOBALS['phinde']['logfile'] != ''
        ) {
            file_put_contents(
                $GLOBALS['phinde']['logfile'],
                $msg . "\n", FILE_APPEND
            );
        } else {
            echo $msg . "\n";
        }
    }
}
?>
