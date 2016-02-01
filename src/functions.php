<?php
function isUrlAllowed($url)
{
    $urlDomain = parse_url($url, PHP_URL_HOST);
    if (!in_array($urlDomain, $GLOBALS['phinde']['domains'])) {
        return false;
    }
    return true;
}

?>
