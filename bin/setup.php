#!/usr/bin/env php
<?php
namespace phinde;
/**
 * Configure the elasticsearch index.
 * Throws away all data.
 */
require_once __DIR__ . '/../src/init.php';

$json = file_get_contents(__DIR__ . '/../data/elasticsearch-mapping.json');
if (json_decode($json) === null) {
    Log::error("Error: Schema JSON is broken");
    chdir(__DIR__ . '/../');
    passthru('json_pp -t null < data/elasticsearch-mapping.json');
    exit(1);
}

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
$r->setBody($json);
$r->send();
?>
