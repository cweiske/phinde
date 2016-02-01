#!/usr/bin/env php
<?php
namespace phinde;
//configure the elasticsearch index
set_include_path(__DIR__ . '/../src/' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../data/config.php';
require_once 'HTTP/Request2.php';
require_once 'Elasticsearch/Request.php';

//delete old index
$r = new Elasticsearch_Request(
    $GLOBALS['phinde']['elasticsearch'],
    \HTTP_Request2::METHOD_DELETE
);
$r->allow404 = true;
$r->send();

//recreate it
$r = new Elasticsearch_Request(
    $GLOBALS['phinde']['elasticsearch'],
    \HTTP_Request2::METHOD_PUT
);
$r->setBody(
    file_get_contents(__DIR__ . '/../data/elasticsearch-mapping.json')
);
$r->send();
?>
