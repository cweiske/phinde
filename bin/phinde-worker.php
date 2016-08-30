#!/usr/bin/env php
<?php
namespace phinde;

chdir(dirname($argv[0]));

require_once __DIR__ . '/../src/init.php';

$cc = new \Console_CommandLine();
$cc->description = 'phinde queue worker';
$cc->version = '0.0.1';
$cc->addArgument(
    'queues',
    array(
        'description' => 'Queue(s) to process',
        'multiple'    => true,
        'default'     => array('crawl', 'index'),
        'choices'     => array('crawl', 'index'),
        'optional'    => true,
    )
);
try {
    $res = $cc->parse();
} catch (\Exception $e) {
    $cc->displayError($e->getMessage());
}

$queues = array_flip(array_unique($res->args['queues']));

$gmworker = new \GearmanWorker();
$gmworker->addServer('127.0.0.1');

if (isset($queues['crawl'])) {
    $gmworker->addFunction(
        $GLOBALS['phinde']['queuePrefix'] . 'phinde_crawl',
        function(\GearmanJob $job) {
            $data = unserialize($job->workload());
            echo "-- Crawling " . $data['url'] . "\n";
            passthru('./crawl.php ' . escapeshellarg($data['url']));
        }
    );
}
if (isset($queues['index'])) {
    $gmworker->addFunction(
        $GLOBALS['phinde']['queuePrefix'] . 'phinde_index',
        function(\GearmanJob $job) {
            $data = unserialize($job->workload());
            echo "-- Indexing " . $data['url'] . "\n";
            passthru('./index.php ' . escapeshellarg($data['url']));
            //exit();
        }
    );
}

while ($gmworker->work()) {
    if ($gmworker->returnCode() != GEARMAN_SUCCESS) {
        echo 'Error running job: ' . $gmworker->returnCode() . "\n";
        break;
    }
}
?>
