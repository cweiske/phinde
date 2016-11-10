#!/usr/bin/env php
<?php
namespace phinde;
require_once __DIR__ . '/../src/init.php';

$cc = new \Console_CommandLine();
$cc->description = 'phinde URL processor';
$cc->version = '0.0.1';
$cc->addOption(
    'force',
    array(
        'short_name'  => '-f',
        'long_name'   => '--force',
        'description' => 'Always process URL, even when it did not change',
        'action'      => 'StoreTrue',
        'default'     => false
    )
);
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
        'description' => 'URL to process',
        'multiple'    => false
    )
);
$cc->addArgument(
    'actions',
    array(
        'description' => 'Actions to take',
        'multiple'    => true,
        'optional'    => true,
        'choices'     => array('index', 'crawl'),
        'default'     => array('index', 'crawl'),
    )
);
try {
    $res = $cc->parse();
} catch (\Exception $e) {
    $cc->displayError($e->getMessage());
}

$url = $res->args['url'];
$url = Helper::addSchema($url);
$urlObj = new \Net_URL2($url);
$url = $urlObj->getNormalizedURL();
if (!Helper::isUrlAllowed($url)) {
    Log::error("Domain is not allowed; not crawling");
    exit(2);
}

try {
    $actions = array();
    foreach ($res->args['actions'] as $action) {
        if ($action == 'crawl') {
            $crawler = new Crawler();
            $crawler->setShowLinksOnly($res->options['showLinksOnly']);
            $actions[$action] = $crawler;
        } else if ($action == 'index') {
            $actions[$action] = new Indexer();
        }
    }

    $fetcher   = new Fetcher();
    $retrieved = $fetcher->fetch($url, $actions, $res->options['force']);
    if ($retrieved === false) {
        exit(0);
    }

    $update = false;
    foreach ($actions as $key => $action) {
        Log::info("step: $key");
        $update |= $action->run($retrieved);
    }

    if ($update) {
        //FIXME: update index if it exists already
        $fetcher->storeDoc($retrieved->url, $retrieved->esDoc);
    } else {
        Log::info("Not updating");
    }
} catch (\Exception $e) {
    Log::error($e->getMessage());
    exit(10);
}
?>