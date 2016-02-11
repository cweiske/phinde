#!/usr/bin/env php
<?php
namespace phinde;
require_once __DIR__ . '/../src/init.php';

$cc = new \Console_CommandLine();
$cc->description = 'phinde URL crawler';
$cc->version = '0.0.1';
$cc->addOption(
    'showLinksOnly',
    array(
        'short_name'  => '-s',
        'long_name'   => '--show-links',
        'description' => 'Only show which URLs were found',
        'action'      => 'StoreTrue',
        'default'     => false
    )
);
$cc->addArgument(
    'url',
    array(
        'description' => 'URL to crawl',
        'multiple'    => false
    )
);
try {
    $res = $cc->parse();
} catch (\Exception $e) {
    $cc->displayError($e->getMessage());
}

$url = $res->args['url'];
$url = Helper::addSchema($url);
if (!Helper::isUrlAllowed($url)) {
    echo "Domain is not allowed; not crawling\n";
    exit(2);
}

try {
    $crawler = new Crawler();
    $crawler->setShowLinksOnly($res->options['showLinksOnly']);
    $crawler->crawl($url);
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
    exit(10);
}
?>