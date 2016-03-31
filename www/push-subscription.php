<?php
namespace phinde;
/**
 * Handles PuSH subscription responses
 */
header('HTTP/1.0 500 Internal Server Error');
require 'www-header.php';

//PHP converts dots to underscore, so hub.mode becomes hub_mode
if (!isset($_GET['hub_mode'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: hub.mode\n";
    exit(1);
}
$hubMode = $_GET['hub_mode'];

if (!isset($_GET['hub_topic'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: hub.topic\n";
    exit(1);
}
if (!isValidUrl($_GET['hub_topic'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.topic: Invalid URL\n";
    exit(1);
}
$hubTopic = $_GET['hub_topic'];

$subDb = new Subscriptions();

if ($hubMode == 'denied') {
    //TODO: Inspect Location header to retry subscription
    //TODO: remove subscription
    return;
} else if ($hubMode == 'subscribe') {
    //FIXME
    $pos = array_search($hubTopic, $GLOBALS['phinde']['subscriptions']);
    if ($pos === false) {
        //we do not want to subscribe
        header('HTTP/1.0 404 Not Found');
        echo "We are not interested in this hub.topic\n";
        exit(1);
    }
    if (!isset($_GET['hub_challenge'])) {
        header('HTTP/1.0 400 Bad Request');
        echo "Parameter missing: hub.challenge\n";
        exit(1);
    }
    $hubChallenge = $_GET['hub_challenge'];

    if (!isset($_GET['hub_lease_seconds'])) {
        header('HTTP/1.0 400 Bad Request');
        echo "Parameter missing: hub.lease_seconds\n";
        exit(1);
    }
    $hubLeaseSeconds = $_GET['hub_lease_seconds'];

    //FIXME: store in database

    header('HTTP/1.0 200 OK');
    header('Content-type: text/plain');
    echo $hubChallenge;
    exit(0);

} else if ($hubMode == 'unsubscribe') {
    $sub = $subDb->get($hubTopic);
    if ($sub === false) {
        //we do not know this subscription
        header('HTTP/1.0 404 Not Found');
        echo "We are not subscribed to this hub.topic\n";
        exit(1);
    }
    $pos = array_search($hubTopic, $GLOBALS['phinde']['subscriptions']);
    if ($pos !== false) {
        //we do not want to unsubscribe
        header('HTTP/1.0 404 Not Found');
        echo "We do not want to unsubscribe from this hub.topic\n";
        exit(1);
    }
    $sub->remove($hubTopic);
    header('HTTP/1.0 200 OK');
    header('Content-type: text/plain');
    echo "Unsubscribed.\n";
    exit(0);
} else {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.mode\n";
    exit(1);
}


function isValidUrl($url)
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    if (substr($url, 0, 7) == 'http://'
        || substr($url, 0, 8) == 'https://'
    ) {
        return true;
    }
    return false;
}
?>