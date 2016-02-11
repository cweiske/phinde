#!/usr/bin/env php
<?php
namespace phinde;
// index a given URL
require_once __DIR__ . '/../src/init.php';

$supportedIndexTypes = array(
    'application/xhtml+xml',
    'text/html',
);

if ($argc < 2) {
    echo "No URL given\n";
    exit(1);
}

function removeTags($doc, $tag) {
    $elems = array();
    foreach ($doc->getElementsbyTagName($tag) as $elem) {
        $elems[] = $elem;
    }
    foreach ($elems as $elem) {
        $elem->parentNode->removeChild($elem);
    }
}

$es = new Elasticsearch($GLOBALS['phinde']['elasticsearch']);

$url = $argv[1];
$existingDoc = $es->get($url);
if ($existingDoc && $existingDoc->status == 'indexed') {
    echo "URL already indexed: $url\n";
    exit(0);
}
//FIXME: size limit
//FIXME: sourcetitle, sourcelink

$req = new \HTTP_Request2($url);
$req->setConfig('follow_redirects', true);
$req->setConfig('connect_timeout', 5);
$req->setConfig('timeout', 10);
$req->setConfig('ssl_verify_peer', false);
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
//FIXME: check if effective url needs updating
$url = $res->getEffectiveUrl();
$base = new \Net_URL2($url);

$indexDoc = new \stdClass();

//FIXME: MIME type switch
$doc = new \DOMDocument();
//@ to hide parse warning messages in invalid html
@$doc->loadHTML($res->getBody());
$dx = new \DOMXPath($doc);

$xbase = $dx->evaluate('/html/head/base[@href]')->item(0);
if ($xbase) {
    $base = $base->resolve(
        $xbase->attributes->getNamedItem('href')->textContent
    );
}


//remove script tags
removeTags($doc, 'script');
removeTags($doc, 'style');
removeTags($doc, 'nav');

//default content: <body>
$xpContext = $doc->getElementsByTagName('body')->item(0);
//FIXME: follow meta refresh, no body
// example: https://www.gnu.org/software/coreutils/

//use microformats content if it exists
$xpElems = $dx->query(
    "//*[contains(concat(' ', normalize-space(@class), ' '), ' e-content ')]"
);
if ($xpElems->length) {
    $xpContext = $xpElems->item(0);
} else if ($doc->getElementById('content')) {
    //if there is an element with ID "content", we'll use this
    $xpContext = $doc->getElementById('content');
}

$indexDoc->url = $url;
$indexDoc->schemalessUrl = Helper::noSchema($url);
$indexDoc->type = 'html';
$indexDoc->subtype = '';
$indexDoc->mimetype = $mimetype;
$indexDoc->domain   = parse_url($url, PHP_URL_HOST);

//$indexDoc->source = 'FIXME';
//$indexDoc->sourcetitle = 'FIXME';

$indexDoc->author = new \stdClass();

$arXpElems = $dx->query('/html/head/meta[@name="author" and @content]');
if ($arXpElems->length) {
    $indexDoc->author->name = trim(
        $arXpElems->item(0)->attributes->getNamedItem('content')->textContent
    );
}
$arXpElems = $dx->query('/html/head/link[@rel="author" and @href]');
if ($arXpElems->length) {
    $indexDoc->author->url = trim(
        $base->resolve(
            $arXpElems->item(0)->attributes->getNamedItem('href')->textContent
        )
    );
}


$arXpElems = $dx->query('/html/head/title');
if ($arXpElems->length) {
    $indexDoc->title = trim(
        $arXpElems->item(0)->textContent
    );
}

foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $headlinetype) {
    $indexDoc->$headlinetype = array();
    foreach ($xpContext->getElementsByTagName($headlinetype) as $xheadline) {
        array_push(
            $indexDoc->$headlinetype,
            trim($xheadline->textContent)
        );
    }
}

//FIXME: split paragraphs
//FIXME: insert space after br
$indexDoc->text = array();
$indexDoc->text[] = trim(
    str_replace(
        array("\r\n", "\n", "\r", '  '),
        ' ',
        $xpContext->textContent
    )
);

//tags
$tags = array();
foreach ($dx->query('/html/head/meta[@name="keywords" and @content]') as $xkeywords) {
    $keywords = $xkeywords->attributes->getNamedItem('content')->textContent;
    foreach (explode(',', $keywords) as $keyword) {
        $tags[trim($keyword)] = true;
    }
}
$indexDoc->tags = array_keys($tags);

//dates
$arXpdates = $dx->query('/html/head/meta[@name="DC.date.created" and @content]');
if ($arXpdates->length) {
    $indexDoc->crdate = date(
        'c',
        strtotime(
            $arXpdates->item(0)->attributes->getNamedItem('content')->textContent
        )
    );
}
//FIXME: keep creation date from database, or use modified date if we
// do not have it there

$arXpdates = $dx->query('/html/head/meta[@name="DC.date.modified" and @content]');
if ($arXpdates->length) {
    $indexDoc->modate = date(
        'c',
        strtotime(
            $arXpdates->item(0)->attributes->getNamedItem('content')->textContent
        )
    );
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
$xlang = $doc->documentElement->attributes->getNamedItem('lang');
if ($xlang) {
    $indexDoc->language = strtolower(substr($xlang->textContent, 0, 2));
}
//FIXME: fallback, autodetection
//FIXME: check noindex

//var_dump($indexDoc);die();

$indexDoc->status = 'indexed';

//FIXME: update index if it exists already
$r = new Elasticsearch_Request(
    $GLOBALS['phinde']['elasticsearch'] . 'document/' . rawurlencode($url),
    \HTTP_Request2::METHOD_PUT
);
$r->setBody(json_encode($indexDoc));
$r->send();


?>
