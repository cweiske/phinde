#!/usr/bin/env php
<?php
namespace phinde;

chdir(dirname($argv[0]));

$gmworker = new \GearmanWorker();
$gmworker->addServer('127.0.0.1');

$gmworker->addFunction(
    'phinde_crawl',
    function(\GearmanJob $job) {
        $data = unserialize($job->workload());
        echo "-- Crawling " . $data['url'] . "\n";
        passthru('./crawl.php ' . escapeshellarg($data['url']));
    }
);
$gmworker->addFunction(
    'phinde_index',
    function(\GearmanJob $job) {
        $data = unserialize($job->workload());
        echo "-- Indexing " . $data['url'] . "\n";
        passthru('./index.php ' . escapeshellarg($data['url']));
        //exit();
    }
);

while ($gmworker->work()) {
    if ($gmworker->returnCode() != GEARMAN_SUCCESS) {
        echo 'Error running job: ' . $gmworker->returnCode() . "\n";
        break;
    }
}
?>
