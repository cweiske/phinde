#!/usr/bin/env php
<?php
namespace phinde;
require_once __DIR__ . '/../src/init.php';

if ($argc < 2) {
    echo "No URL given\n";
    exit(1);
}

$url = $argv[1];
$url = Helper::addSchema($url);
if (!Helper::isUrlAllowed($url)) {
    echo "Domain is not allowed; not crawling\n";
    exit(2);
}

try {
    $crawler = new Crawler();
    $crawler->crawl($url);
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
    exit(10);
}
?>