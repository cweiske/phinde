#!/usr/bin/env php
<?php
namespace phinde;

chdir(dirname($argv[0]));

require_once __DIR__ . '/../src/init.php';

$gmworker = new \GearmanWorker();
$gmworker->addServer('127.0.0.1');

$gmworker->addFunction(
    $GLOBALS['phinde']['queuePrefix'] . 'phinde_process',
    function(\GearmanJob $job) {
        $data = unserialize($job->workload());
        echo "-- Processing " . $data['url']
            . ' (' . implode(',', $data['actions']) . ')'
            . "\n";
        passthru(
            './process.php ' . escapeshellarg($data['url'])
            . ' ' . implode(' ', $data['actions'])
        );
    }
);

while ($gmworker->work()) {
    if ($gmworker->returnCode() != GEARMAN_SUCCESS) {
        echo 'Error running job: ' . $gmworker->returnCode() . "\n";
        break;
    }
}
?>
