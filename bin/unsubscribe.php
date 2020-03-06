#!/usr/bin/env php
<?php
namespace phinde;
/**
 * Unsubscribe from a WebSub subscription
 */
require_once __DIR__ . '/../src/init.php';

$cc = new \Console_CommandLine();
$cc->description = 'Unsubscribe from a WebSub subscription';
$cc->version = '0.0.1';
$cc->addArgument(
    'url',
    array(
        'description' => 'URL to process',
        'multiple'    => false
    )
);
try {
    $res = $cc->parse();
} catch (\Exception $e) {
    $cc->displayError($e->getMessage());
}

$subDb = new Subscriptions();

$url = $res->args['url'];
$url = Helper::addSchema($url);
$urlObj = new \Net_URL2($url);
$topic = $urlObj->getNormalizedURL();


$sub = $subDb->get($topic);
if ($sub === false) {
    Log::error("No existing subscription for URL");
    exit(2);
}
if ($sub->sub_status === 'unsubscribed') {
    Log::info('Already unsubscribed');
    exit(0);
}

$subDb->unsubscribing($sub->sub_id);

$callbackUrl = $GLOBALS['phinde']['baseurl'] . 'push-subscription.php'
    . '?hub.topic=' . urlencode($topic)
    . '&capkey=' . urlencode($sub->sub_capkey);
$req = new HttpRequest($sub->sub_hub, 'POST');
$req->addPostParameter('hub.callback', $callbackUrl);
$req->addPostParameter('hub.mode', 'unsubscribe');
$req->addPostParameter('hub.topic', $topic);
$req->addPostParameter('hub.lease_seconds', $sub->sub_lease_seconds);
$req->addPostParameter('hub.secret', $sub->sub_secret);
$res = $req->send();

if (intval($res->getStatus()) == 202) {
    Log::info('Unsubscription initiated');
    exit(0);
}

Log::error(
    'Error: Unsubscription response status code was not 202 but '
    . $res->getStatus()
);
Log::error($res->getBody());
?>
