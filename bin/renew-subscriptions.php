#!/usr/bin/env php
<?php
namespace phinde;
/**
 * Renew subscriptions
 * Call this once a day with cron.
 */
require_once __DIR__ . '/../src/init.php';

chdir(__DIR__);
$subDb = new Subscriptions();
foreach ($subDb->getExpiring() as $sub) {
    Log::info('Expires soon: ' . $sub['sub_topic']);
    passthru('./subscribe.php ' . escapeshellarg($sub['sub_topic']));
}
?>
