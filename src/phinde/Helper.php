<?php
namespace phinde;

class Helper
{
    public static function isUrlAllowed($url)
    {
        $urlDomain = parse_url($url, PHP_URL_HOST);
        if (!in_array($urlDomain, $GLOBALS['phinde']['domains'])) {
            return false;
        }
        return true;
    }

    public static function noSchema($url)
    {
        return str_replace(
            array('http://', 'https://'),
            '',
            $url
        );
    }

    public static function addSchema($url)
    {
        if (substr($url, 0, 7) == 'http://'
            || substr($url, 0, 8) == 'https://'
        ) {
            return $url;
        }
        return 'http://' . $url;
    }

    public static function sanitizeTitle($str)
    {
        return trim(
            str_replace(
                array("\r", "\n", '  ', '  '),
                array('', ' ', ' ', ' '),
                $str
            )
        );
    }
}
?>
