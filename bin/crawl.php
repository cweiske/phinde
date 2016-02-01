#!/usr/bin/env php
<?php
namespace phinde;

set_include_path(__DIR__ . '/../src/' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../data/config.php';
require_once 'HTTP/Request2.php';
require_once 'Elasticsearch.php';
require_once 'Elasticsearch/Request.php';
require_once 'Net/URL2.php';
require_once 'functions.php';

$supportedCrawlTypes = array(
    'text/html', 'application/xhtml+xml'
);


if ($argc < 2) {
    echo "No URL given\n";
    exit(1);
}

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);

$url = $argv[1];
if (!isUrlAllowed($url)) {
    echo "Domain is not allowed; not crawling\n";
    exit(2);
}


$req = new \HTTP_Request2($url);
//FIXME: send supported mime types in header
$res = $req->send();
if ($res->getStatus() !== 200) {
    echo "Response code is not 200 but " . $res->getStatus() . ", stopping\n";
    exit(3);
}
$mimetype = explode(';', $res->getHeader('content-type'))[0];
if (!in_array($mimetype, $supportedCrawlTypes)) {
    echo "MIME type not supported for crawling: $mimetype\n";
    exit(4);
}

//FIXME: mime type switch for cdata
$doc = new \DOMDocument();
//@ to hide parse warning messages in invalid html
@$doc->loadHTMLFile($url);

//FIXME: extract base url from html
$base = new \Net_URL2($url);

$xpath = new \DOMXPath($doc);
$links = $xpath->evaluate('//a');
//FIXME: link rel, img, video

$alreadySeen = array();

foreach ($links as $link) {
    $linkTitle = $link->textContent;
    $href = '';
    foreach ($link->attributes as $attribute) {
        if ($attribute->name == 'href') {
            $href = $attribute->textContent;
        }
    }
    if ($href == '' || $href{0} == '#') {
        //link on this page
        continue;
    }

    $linkUrlObj = $base->resolve($href);
    $linkUrlObj->setFragment(false);
    $linkUrl    = (string) $linkUrlObj;
    if (isset($alreadySeen[$linkUrl])) {
        continue;
    }

    switch ($linkUrlObj->getScheme()) {
    case 'http':
    case 'https':
        break;
    default:
        continue 2;
    }

    if ($es->isKnown($linkUrl)) {
        continue;
    }

    //FIXME: check target type
    //FIXME: check nofollow
    //var_dump($linkTitle, $linkUrl);
    $es->markQueued($linkUrl);
    addToIndex($linkUrl, $linkTitle, $url);
    if (isUrlAllowed($linkUrl)) {
        addToCrawl($linkUrl);
    }
    $alreadySeen[$linkUrl] = true;
}

function addToIndex($linkUrl, $linkTitle, $sourceUrl)
{
    echo "Queuing for indexing: $linkUrl\n";
    $gmclient = new \GearmanClient();
    $gmclient->addServer('127.0.0.1');
    $gmclient->doBackground(
        'phinde_index',
        serialize(
            array(
                'url'    => $linkUrl,
                'title'  => $linkTitle,
                'source' => $sourceUrl
            )
        )
    );
    if ($gmclient->returnCode() != GEARMAN_SUCCESS) {
        echo 'Error queueing URL indexing for '
            . $linkUrl . "\n"
            . 'Error code: ' . $gmclient->returnCode() . "\n";
        exit(2);
    }
}

function addToCrawl($linkUrl)
{
    echo "Queuing for crawling: $linkUrl\n";
    $gmclient = new \GearmanClient();
    $gmclient->addServer('127.0.0.1');
    $gmclient->doBackground(
        'phinde_crawl',
        serialize(
            array(
                'url' => $linkUrl
            )
        )
    );
    if ($gmclient->returnCode() != GEARMAN_SUCCESS) {
        echo 'Error queueing URL crawling for '
            . $linkUrl . "\n"
            . 'Error code: ' . $gmclient->returnCode() . "\n";
        exit(2);
    }
}
?>