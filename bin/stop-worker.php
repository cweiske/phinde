#!/usr/bin/env php
<?php
//stop a single running worker
namespace phinde;
require_once __DIR__ . '/../src/init.php';

$gmclient = new \GearmanClient();
$gmclient->addServer('127.0.0.1');
$gmclient->doHigh(
    $GLOBALS['phinde']['queuePrefix'] . 'phinde_quit', 'none'
);
?>
