<?php
namespace phinde;
/**
 * Handles PuSH subscription responses
 */
header('HTTP/1.0 500 Internal Server Error');
require_once 'www-header.php';

//PHP converts dots to underscore, so hub.topic becomes hub_topic
if (!isset($_GET['hub_topic'])) {
    err('Parameter missing: hub.topic', '400 Bad Request');
}
if (!isValidUrl($_GET['hub_topic'])) {
    err(
        'Invalid parameter value for hub.topic: Invalid URL',
        '400 Bad Request'
    );
}
$hubTopic = $_GET['hub_topic'];

$subDb = new Subscriptions();
$sub   = $subDb->get($hubTopic);
if ($sub === false) {
    //we do not have this topic in our database
    err('We know nothing about this hub.topic', '404 Not Found');
}

//capability key verification so third parties can't forge requests
// see https://www.w3.org/TR/capability-urls/
if (!isset($_GET['capkey'])) {
    err('Parameter missing: capkey', '400 Bad Request');
}
if ($sub->sub_capkey !== $_GET['capkey']) {
    err('Invalid parameter value for capkey', '400 Bad Request');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $queue = new Queue();
    $queue->addToProcessList($hubTopic, ['index', 'crawl']);
    $subDb->pinged($sub->sub_id);
    header('HTTP/1.0 200 OK');
    echo "URL queued.\n";
    exit();
}

if (!isset($_GET['hub_mode'])) {
    err('Parameter missing: hub.mode', '400 Bad Request');
}
$hubMode = $_GET['hub_mode'];

if ($hubMode == 'subscribe') {
    if (!isset($_GET['hub_challenge'])) {
        err('Parameter missing: hub.challenge', '400 Bad Request');
    }
    $hubChallenge = $_GET['hub_challenge'];

    if (!isset($_GET['hub_lease_seconds'])) {
        err('Parameter missing: hub.lease_seconds', '400 Bad Request');
    }
    if (!is_numeric($_GET['hub_lease_seconds'])) {
        err('Invalid value for hub.lease_seconds', '400 Bad Request');
    }
    $hubLeaseSeconds = intval($_GET['hub_lease_seconds']);

    $subDb->subscribed($sub->sub_id, $hubLeaseSeconds);

    header('HTTP/1.0 200 OK');
    header('Content-type: text/plain');
    echo $hubChallenge;
    exit();

} else if ($hubMode == 'unsubscribe') {
    if ($sub->sub_status != 'unsubscribing') {
        //we do not want to unsubscribe
        err(
            'We do not want to unsubscribe from this hub.topic',
            '404 Not Found'
        );
    }
    if (!isset($_GET['hub_challenge'])) {
        err('Parameter missing: hub.challenge', '400 Bad Request');
    }
    $hubChallenge = $_GET['hub_challenge'];

    $subDb->unsubscribed($sub->sub_id);

    header('HTTP/1.0 200 OK');
    header('Content-type: text/plain');
    echo $hubChallenge;
    exit();

} else if ($hubMode == 'denied') {
    //TODO: Inspect Location header to retry subscription (still valid?)
    $reason = '';
    if (isset($_GET['hub_reason'])) {
        $reason = $_GET['hub_reason'];
    }
    $subDb->denied($sub->sub_id, $reason);
    exit();

} else {
    err('Invalid parameter value for hub.mode', '400 Bad Request');
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

function err($msg, $statusline)
{
    header('HTTP/1.0 ' . $statusline);
    echo $msg . "\n";
    exit(1);
}
?>