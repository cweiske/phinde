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
        Log::info(
            "-- Processing " . $data['url']
            . ' (' . implode(',', $data['actions']) . ')'
        );
        passthru(
            './process.php ' . escapeshellarg($data['url'])
            . ' ' . implode(' ', $data['actions'])
        );
    }
);

$gmworker->addFunction(
    $GLOBALS['phinde']['queuePrefix'] . 'phinde_quit',
    function(\GearmanJob $job) {
        Log::info('Got exit job');
        $job->sendComplete('');
        exit(0);
    }
);

while ($gmworker->work()) {
    if ($gmworker->returnCode() != GEARMAN_SUCCESS) {
        Log::error('Error running job: ' . $gmworker->returnCode());
        break;
    }
}
?>
