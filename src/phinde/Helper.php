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

    public static function removeAnchor($url)
    {
        $parts = explode('#', $url, 2);
        return $parts[0];
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

    /**
     * Create a full URL with protocol and host name
     *
     * @param string $path Path to the file, with leading /
     *
     * @return string Full URL
     */
    public static function fullUrl($path = '/')
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
            $prot = 'https';
        } else {
            $prot = 'http';
        }
        return $prot . '://' . $_SERVER['HTTP_HOST'] . $path;
    }

}
?>
