#!/usr/bin/env php
<?php
namespace phinde;
// index a given URL
set_include_path(__DIR__ . '/../src/' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../data/config.php';
require_once 'HTTP/Request2.php';
require_once 'Elasticsearch.php';
require_once 'Elasticsearch/Request.php';
require_once 'functions.php';

$supportedIndexTypes = array(
    'application/xhtml+xml',
    'text/html',
);

if ($argc < 2) {
    echo "No URL given\n";
    exit(1);
}

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);

$url = $argv[1];
$existingDoc = $es->get($url);
if ($existingDoc && $existingDoc->status == 'indexed') {
    echo "URL already indexed: $url\n";
    exit(0);
}
//FIXME: sourcetitle, sourcelink

//FIXME: enable redirects
//FIXME: enable ssl 
$req = new \HTTP_Request2($url);
$req->setConfig('connect_timeout', 5);
$req->setConfig('timeout', 10);
$res = $req->send();
//FIXME: try-catch

//FIXME: delete if 401 gone or 404 when updating
if ($res->getStatus() !== 200) {
    echo "Response code is not 200 but " . $res->getStatus() . ", stopping\n";
    //FIXME: update status
    exit(3);
}

$mimetype = explode(';', $res->getHeader('content-type'))[0];
if (!in_array($mimetype, $supportedIndexTypes)) {
    echo "MIME type not supported for indexing: $mimetype\n";
    //FIXME: update status
    exit(4);
}


//FIXME: update index only if changed since last index time
//FIXME: extract base url from html
//FIXME: use final URL after redirects
$base = new \Net_URL2($url);

$indexDoc = new \stdClass();

//FIXME: MIME type switch
$doc = new \DOMDocument();
//@ to hide parse warning messages in invalid html
@$doc->loadHTML($res->getBody());
$sx = simplexml_import_dom($doc);

$indexDoc->url = $url;
$indexDoc->type = 'html';
$indexDoc->subtype = '';
$indexDoc->mimetype = $mimetype;
$indexDoc->domain   = parse_url($url, PHP_URL_HOST);

//$indexDoc->source = 'FIXME';
//$indexDoc->sourcetitle = 'FIXME';

$indexDoc->author = new \stdClass();

$arSxElems = $sx->xpath('/html/head/meta[@name="author"]');
if (count($arSxElems)) {
    $indexDoc->author->name = trim($arSxElems[0]['content']);
}
$arSxElems = $sx->xpath('/html/head/link[@rel="author"]');
if (count($arSxElems)) {
    $indexDoc->author->url = (string) $base->resolve($arSxElems[0]['href']);
}

$indexDoc->title = (string) $sx->head->title;
foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $headlinetype) {
    $indexDoc->$headlinetype = array();
    //FIXME: limit to h-entry children
    foreach ($sx->xpath('//' . $headlinetype) as $xheadline) {
        array_push(
            $indexDoc->$headlinetype,
            trim(dom_import_simplexml($xheadline)->textContent)
        );
    }
}

//FIXME: limit to h-entry e-content
//FIXME: insert space after br
//FIXME: remove javascript
$indexDoc->text = array();
foreach ($doc->getElementsByTagName('body') as $body) {
    $indexDoc->text[] = trim(
        str_replace(
            array("\r\n", "\n", "\r", '  '),
            ' ',
            $body->textContent
        )
    );
}

//tags
$tags = array();
foreach ($sx->xpath('/html/head/meta[@name="keywords"]') as $xkeywords) {
    foreach (explode(',', $xkeywords['content']) as $keyword) {
        $tags[trim($keyword)] = true;
    }
}
$indexDoc->tags = array_keys($tags);

//dates
$arSxdates = $sx->xpath('/html/head/meta[@name="DC.date.created"]');
if (count($arSxdates)) {
    $indexDoc->crdate = date('c', strtotime((string) $arSxdates[0]['content']));
}
//FIXME: keep creation date from database, or use modified date if we
// do not have it there

$arSxdates = $sx->xpath('/html/head/meta[@name="DC.date.modified"]');
if (count($arSxdates)) {
    $indexDoc->modate = date('c', strtotime((string) $arSxdates[0]['content']));
} else {
    $lm = $res->getHeader('last-modified');
    if ($lm !== null) {
        $indexDoc->modate = date('c', strtotime($lm));
    } else {
        //use current time since we don't have any other data
        $indexDoc->modate = date('c');
    }
}

//language
//there may be "en-US" and "de-DE"
$indexDoc->language = substr((string) $sx['lang'], 0, 2);
//FIXME: fallback, autodetection
//FIXME: check noindex


//var_dump($indexDoc);

$indexDoc->status = 'indexed';

//FIXME: update index if it exists already
$r = new Elasticsearch_Request(
    $GLOBALS['phinde']['elasticsearch'] . 'document/' . rawurlencode($url),
    \HTTP_Request2::METHOD_PUT
);
$r->setBody(json_encode($indexDoc));
$r->send();


?>
