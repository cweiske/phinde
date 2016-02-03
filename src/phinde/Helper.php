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
}
?>
